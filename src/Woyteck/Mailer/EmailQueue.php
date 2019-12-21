<?php
declare(strict_types=1);

namespace Woyteck\Mailer;

use Woyteck\Exception;
use Woyteck\Db\ModelFactory;
use Woyteck\Mailer\Model\Email as EmailModel;
use Woyteck\Mailer\Model\EmailPart as EmailPartModel;
use Woyteck\Mailer\Mailer as EmailSend;

class EmailQueue
{
    const DEFAULT_ENCODING = 'UTF-8';

    private ModelFactory $dbFactory;

    /**
     * @var EmailModel[]
     */
    private array $tasks = [];

    public function __construct(ModelFactory $dbFactory)
    {
        $this->dbFactory = $dbFactory;
    }

    /**
     * @param Email $email
     *
     * @return EmailQueue
     * @throws Exception
     */
    public function add(Email $email)
    {
        $email->validate();

        /** @var EmailModel $task */
        $task = $this->dbFactory->create(EmailModel::class);
        $task->datetime_save = time();

        $task->authors = json_encode($email->getAuthors());
        $recipients = [];
        if (count($email->getTo()) > 0) {
            $recipients[EmailSend::STRING_TO] = $email->getTo();
        }
        if (count($email->getCc()) > 0) {
            $recipients[EmailSend::STRING_CC] = $email->getCc();
        }
        if (count($email->getBcc()) > 0) {
            $recipients[EmailSend::STRING_BCC] = $email->getBcc();
        }
        $task->recipients = json_encode($recipients);

        if (count($email->getReplyTo()) > 0) {
            $task->reply_to = json_encode($email->getReplyTo());
        }

        $task->subject = $email->getSubject();
        if ($email->getEncoding() !== null) {
            $task->encoding = $email->getEncoding();
        } else {
            $task->encoding = self::DEFAULT_ENCODING;
        }

        if ($email->getContext() !== null) {
            $task->context = $email->getContext();
        }

        if ($email->getContextIdentifier() !== null) {
            $task->context_identifier = $email->getContextIdentifier();
        }

        $task->save();

        if ($email->getContentsPlain() !== null) {
            /** @var EmailPartModel $part */
            $part = $this->dbFactory->create(EmailPartModel::class);
            $part->email_id = $task->id;
            $part->mime_type = MimeType::TEXT_PLAIN;
            $part->charset = $email->getEncoding() !== null ? $email->getEncoding() : 'UTF-8';
            $part->contents = $email->getContentsPlain();
            $part->save();
        }

        if ($email->getContentsHtml() !== null) {
            /** @var EmailPartModel $part */
            $part = $this->dbFactory->create(EmailPartModel::class);
            $part->email_id = $task->id;
            $part->mime_type = MimeType::TEXT_HTML;
            $part->charset = $email->getEncoding() !== null ? $email->getEncoding() : 'UTF-8';
            $part->contents = $email->getContentsHtml();
            $part->save();
        }

        foreach ($email->getAttachments() as $attachment) {
            /** @var EmailPartModel $part */
            $part = $this->dbFactory->create(EmailPartModel::class);
            $part->email_id = $task->id;
            $part->mime_type = $attachment->mimeType;
            if ($attachment->encoding !== null) {
                $part->encoding = $attachment->encoding;
            }
            if ($attachment->charset !== null) {
                $part->charset = $attachment->charset;
            }
            if ($attachment->disposition) {
                $part->disposition = $attachment->disposition;
            }
            if ($attachment->filename) {
                $part->filename = $attachment->filename;
            }
            if ($attachment->contentId) {
                $part->content_id = $attachment->contentId;
            }
            $part->contents = $attachment->contents;

            $part->save();
        }

        $this->tasks[$task->id] = $task;

        return $this;
    }
}
