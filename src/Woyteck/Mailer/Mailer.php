<?php
declare(strict_types=1);

namespace Woyteck\Mailer;

use Psr\Log\LoggerInterface;
use Woyteck\Db\ModelCollection;
use Woyteck\Db\ModelFactory;
use Woyteck\Exception;
use Woyteck\Mailer\Model\Email;
use Woyteck\Mailer\Model\EmailPart;
use Zend\Mail\Message;
use Zend\Mail\Transport\TransportInterface;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

class Mailer
{
    const EMAILS_COUNT_LIMIT = 10;
    const MAX_RETRY_COUNT = 3;

    const STRING_ADDRESS = 'address';
    const STRING_LABEL = 'label';
    const STRING_TO = 'to';
    const STRING_CC = 'cc';
    const STRING_BCC = 'bcc';

    const TESTING_ADDRESSES = [
        'w.hoffmann@gmail.com',
    ];

    private ModelFactory $modelFactory;
    private TransportInterface $transport;
    private LoggerInterface $logger;

    public function __construct(TransportInterface $transport, ModelFactory $modelFactory, LoggerInterface $logger)
    {
        $this->modelFactory = $modelFactory;
        $this->transport = $transport;
        $this->logger = $logger;
    }

    public function sendQueuedEmails(): bool
    {
        $limit = self::EMAILS_COUNT_LIMIT;

        /** @var Email[] $emails */
        try {
            $emails = $this->modelFactory->getMany(Email::class, [
                'status' => [Email::STATUS_WAITING, Email::STATUS_ERROR],
                'lower_retry_count' => self::MAX_RETRY_COUNT,
            ], [
                'order' => 'priority ASC',
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            $this->logger->error(Exception::format($e));

            return false;
        }

        foreach ($emails as $email) {
            try {
                $this->handleTask($email);
                $email->status = Email::STATUS_SENT;
                $email->datetime_sent = time();
                $email->save();
            } catch (\Exception $e) {
                $email->retry_count = (int) $email->retry_count + 1;
                $email->status = Email::STATUS_ERROR;
                $email->save();
                $this->logger->error(Exception::format($e));
                echo "\n" . $e->getMessage() . "\n";
            }
        }

        return true;
    }

    public function purgeQueue(): void
    {
        /** @var \PDO $dbAdapter */
        $dbAdapter = $this->modelFactory->getAdapter();

        $daysAgo = 120;
        $query = "SELECT GROUP_CONCAT(id) AS ids "
            . "FROM email "
            . "WHERE datetime_sent < :time AND status != 'waiting' "
            . "ORDER BY priority ASC "
            . "LIMIT 10";

        $statement = $dbAdapter->prepare($query);
        $statement->execute([
            'time' => strtotime("-{$daysAgo} days"),
        ]);
        $row = $statement->fetch();

        if (empty($row['ids'])) {
            return;
        }

        $idsImploded = $row['ids'];

        try {
            $dbAdapter->beginTransaction();
            $query = "DELETE from email_part WHERE task_id IN ({$idsImploded})";
            $dbAdapter->query($query)->execute();
            $query = "DELETE from email WHERE id IN ({$idsImploded})";
            $dbAdapter->query($query)->execute();
            $dbAdapter->commit();
        } catch (\Exception $e) {
            $dbAdapter->rollBack();
        }
    }

    /**
     * @param Email $email
     * @throws Exception
     * @throws \Woyteck\Db\Exception
     */
    private function handleTask(Email $email): void
    {
        $message = new Message();
        $message->setEncoding($email->encoding);

        $authors = json_decode($email->authors, true);
        foreach ($authors as $author) {
            if (!isset($author['address'])) {
                throw new Exception('author address is missing');
            }

            $message->addFrom($author['address'], isset($author['label']) ? $author['label'] : null);
        }

        if ($email->reply_to !== null) {
            $replyTo = json_decode($email->reply_to, true);
            if (isset($replyTo[self::STRING_ADDRESS])) {
                $message->setReplyTo($replyTo[self::STRING_ADDRESS], isset($replyTo[self::STRING_LABEL]) ? $replyTo[self::STRING_LABEL] : null);
            }
        }

        $recipients = json_decode($email->recipients, true);
        if (isset($recipients[self::STRING_TO])) {
            foreach ($recipients[self::STRING_TO] as $recipient) {
                if (!isset($recipient[self::STRING_ADDRESS])) {
                    continue;
                }

                if (APPLICATION_ENV == APPLICATION_ENV_PROD
                    || !in_array($recipient[self::STRING_ADDRESS], self::TESTING_ADDRESSES)
                ) {
                    $message->addTo($recipient[self::STRING_ADDRESS], isset($recipient[self::STRING_LABEL]) ? $recipient[self::STRING_LABEL] : null);
                }
            }
        }

        if (isset($recipients[self::STRING_CC])) {
            foreach ($recipients[self::STRING_CC] as $recipient) {
                if (!isset($recipient[self::STRING_ADDRESS])) {
                    continue;
                }

                $message->addCc($recipient[self::STRING_ADDRESS], isset($recipient[self::STRING_LABEL]) ? $recipient[self::STRING_LABEL] : null);
            }
        }

        if (isset($recipients[self::STRING_BCC])) {
            foreach ($recipients[self::STRING_BCC] as $recipient) {
                if (!isset($recipient[self::STRING_ADDRESS])) {
                    continue;
                }

                $message->addBcc($recipient[self::STRING_ADDRESS], isset($recipient[self::STRING_LABEL]) ? $recipient[self::STRING_LABEL] : null);
            }
        }

        $message->setSubject($email->subject);

        /** @var EmailPart[] $dbParts */
        $dbParts = $this->modelFactory->getMany(EmailPart::class, [
            'email_id' => $email->id,
        ]);

        $message->setBody($this->constructBody($dbParts));

        //send
        $this->transport->send($message);
    }

    /**
     * This method creates specific multipart structure which depends on:
     * - existence of inline images (creates multipart/related part wrapper)
     * - existence of plain & html parts (creates multipart/alternative wrapper)
     * - existence of regular attachments
     * If the mail is a simple plain or html contents without attachments it will just create one part.
     *
     * @param ModelCollection|EmailPart[] $dbParts
     *
     * @return MimeMessage
     */
    private function constructBody(ModelCollection $dbParts): MimeMessage
    {
        $hasAttachment = false;
        $hasPlain = false;
        $hasHtml = false;
        $hasInline = false;
        foreach ($dbParts as $dbPart) {
            if ($dbPart->mime_type == MimeType::TEXT_PLAIN) {
                $hasPlain = true;
            }

            if ($dbPart->mime_type == MimeType::TEXT_HTML) {
                $hasHtml = true;
            }

            if ($dbPart->disposition == 'attachment') {
                $hasAttachment = true;
            }

            if ($dbPart->disposition == 'inline') {
                $hasInline = true;
            }
        }

        $body = new MimeMessage();
        if ($hasPlain === true && $hasHtml === false) {
            foreach ($this->getContentParts($dbParts, [MimeType::TEXT_PLAIN]) as $part) {
                $body->addPart($part);
            }
        } elseif ($hasPlain === false && $hasHtml === true && $hasInline === false) {
            foreach ($this->getContentParts($dbParts, [MimeType::TEXT_HTML]) as $part) {
                $body->addPart($part);
            }
        } elseif ($hasPlain === true && $hasHtml === true && $hasInline === false) {
            $alternatives = new MimeMessage();
            foreach ($this->getContentParts($dbParts, [MimeType::TEXT_PLAIN, MimeType::TEXT_HTML]) as $part) {
                $alternatives->addPart($part);
                $alternativesPart = new MimePart($alternatives->generateMessage());
                $alternativesPart->setType('multipart/alternative');
                $alternativesPart->setBoundary($alternatives->getMime()->boundary());
                $body->addPart($alternativesPart);
            }
        } elseif ($hasPlain === false && $hasHtml === true && $hasInline === true) {
            $related = new MimeMessage();
            foreach ($this->getContentParts($dbParts, [MimeType::TEXT_HTML]) as $part) {
                $related->addPart($part);
            }
            foreach ($this->getInlineAttachments($dbParts) as $part) {
                $related->addPart($part);
            }
            $relatedPart = new MimePart($related->generateMessage());
            $relatedPart->setType('multipart/related');
            $relatedPart->setBoundary($related->getMime()->boundary());
            $body->addPart($relatedPart);
        } elseif ($hasPlain === true && $hasHtml === true && $hasInline === true) {
            $alternatives = new MimeMessage();
            foreach ($this->getContentParts($dbParts, [MimeType::TEXT_PLAIN]) as $part) {
                $alternatives->addPart($part);
            }
            $related = new MimeMessage();
            foreach ($this->getContentParts($dbParts, [MimeType::TEXT_HTML]) as $part) {
                $related->addPart($part);
            }
            foreach ($this->getInlineAttachments($dbParts) as $part) {
                $related->addPart($part);
            }
            $relatedPart = new MimePart($related->generateMessage());
            $relatedPart->setType('multipart/related');
            $relatedPart->setBoundary($related->getMime()->boundary());
            $alternatives->addPart($relatedPart);

            $alternativesPart = new MimePart($alternatives->generateMessage());
            $alternativesPart->setType('multipart/alternative');
            $alternativesPart->setBoundary($alternatives->getMime()->boundary());
            $body->addPart($alternativesPart);
        }

        if ($hasAttachment === true) {
            foreach ($this->getAttachments($dbParts) as $part) {
                $body->addPart($part);
            }
        }

        return $body;
    }

    /**
     * @param ModelCollection|EmailPart[] $dbParts
     *
     * @return array
     */
    private function getInlineAttachments(ModelCollection $dbParts): array
    {
        $parts = [];
        foreach ($dbParts as $dbPart) {
            if ($dbPart->disposition == 'inline') {
                $part = new MimePart($dbPart->contents);
                $part->setType($dbPart->mime_type);
                $part->setContent($dbPart->contents);
                if ($dbPart->encoding != '') {
                    $part->setEncoding($dbPart->encoding);
                }
                if ($dbPart->disposition != '') {
                    $part->setDisposition($dbPart->disposition);
                }
                if ($dbPart->filename != '') {
                    $part->setFileName($dbPart->filename);
                }
                if ($dbPart->content_id != '') {
                    $part->setId($dbPart->content_id);
                }
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * @param ModelCollection|EmailPart[] $dbParts
     * @param array $mimeTypes
     *
     * @return array
     */
    private function getContentParts(ModelCollection $dbParts, $mimeTypes = [MimeType::TEXT_PLAIN, MimeType::TEXT_HTML]): array
    {
        $parts = [];
        foreach ($dbParts as $dbPart) {
            if (in_array($dbPart->mime_type, $mimeTypes)) {
                $part = new MimePart($dbPart->contents);
                $part->setType($dbPart->mime_type);
                $part->setContent($dbPart->contents);
                if ($dbPart->charset != '') {
                    $part->setCharset($dbPart->charset);
                }
                if ($dbPart->encoding != '') {
                    $part->setEncoding($dbPart->encoding);
                }
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * @param EmailPart[] $dbParts
     *
     * @return array
     */
    private function getAttachments(array $dbParts): array
    {
        $parts = [];
        foreach ($dbParts as $dbPart) {
            if ($dbPart->disposition == 'attachment') {
                $part = new MimePart($dbPart->contents);
                $part->setType($dbPart->mime_type);
                $part->setContent($dbPart->contents);
                if ($dbPart->encoding != '') {
                    $part->setEncoding($dbPart->encoding);
                }
                if ($dbPart->disposition != '') {
                    $part->setDisposition($dbPart->disposition);
                }
                if ($dbPart->filename != '') {
                    $part->setFileName($dbPart->filename);
                }
                $parts[] = $part;
            }
        }

        return $parts;
    }
}
