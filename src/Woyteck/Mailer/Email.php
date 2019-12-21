<?php
declare(strict_types=1);

namespace Woyteck\Mailer;

use Woyteck\Exception;

class Email
{
    const ADDRESS = 'address';
    const LABEL = 'label';

    private array $authors = [];
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private array $replyTo = [];
    private ?string $subject = null;
    private ?string $encoding = null;
    private ?string $contentsPlain = null;
    private ?string $contentsHtml = null;

    /**
     * @var Attachment[]
     */
    private array $attachments = [];
    private ?string $context;
    private ?int $contextIdentifier;

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function addAuthor(string $emailAddress, string $label = null): self
    {
        $author = [
            self::ADDRESS => $emailAddress,
        ];
        if ($label !== null) {
            $author[self::LABEL] = $label;
        }
        $this->authors[] = $author;

        return $this;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function addTo(string $emailAddress, string $label = null): self
    {
        $recipient = [
            self::ADDRESS => $emailAddress,
        ];
        if ($label !== null) {
            $recipient[self::LABEL] = $label;
        }
        $this->to[] = $recipient;

        return $this;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function addCc(string $emailAddress, string $label = null): self
    {
        $recipient = [
            self::ADDRESS => $emailAddress,
        ];
        if ($label !== null) {
            $recipient[self::LABEL] = $label;
        }
        $this->cc[] = $recipient;

        return $this;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function addBcc(string $emailAddress, string $label = null): self
    {
        $recipient = [
            self::ADDRESS => $emailAddress,
        ];
        if ($label !== null) {
            $recipient[self::LABEL] = $label;
        }
        $this->bcc[] = $recipient;

        return $this;
    }

    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function addReplyTo(string $emailAddress, string $label = null): self
    {
        $replyTo = [
            self::ADDRESS => $emailAddress,
        ];
        if ($label !== null) {
            $replyTo[self::LABEL] = $label;
        }
        $this->replyTo[] = $replyTo;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    public function setEncoding(string $encoding): self
    {
        $this->encoding = $encoding;

        return $this;
    }

    public function getContentsPlain(): ?string
    {
        return $this->contentsPlain;
    }

    public function setContentsPlain(string $contentsPlain): self
    {
        $this->contentsPlain = $contentsPlain;

        return $this;
    }

    public function getContentsHtml(): ?string
    {
        return $this->contentsHtml;
    }

    public function setContentsHtml(string $contentsHtml): self
    {
        $this->contentsHtml = $contentsHtml;

        return $this;
    }

    /**
     * @return Attachment[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * @param Attachment[] $attachment
     *
     * @return self
     */
    public function setAttachments(array $attachment): self
    {
        $this->attachments = $attachment;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getContextIdentifier(): ?int
    {
        return $this->contextIdentifier;
    }

    public function setContextIdentifier(int $contextIdentifier): self
    {
        $this->contextIdentifier = $contextIdentifier;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function validate(): void
    {
        if (!is_string($this->subject) || strlen($this->subject) === 0) {
            throw new Exception('Subject not set');
        }

        if ($this->contentsPlain === null && $this->contentsHtml === null) {
            throw new Exception('Contents not set');
        }

        if (count($this->authors) === 0) {
            throw new Exception('Authors not set');
        }

        if (count($this->to) === 0) {
            throw new Exception('Recipients not set');
        }

        if ($this->contextIdentifier !== null && $this->context === null) {
            throw new Exception('Can\'t set context identifier without setting context');
        }

        foreach ($this->attachments as $attachment) {
            if ($attachment->contents === null) {
                throw new Exception('Attachment\'s contents not set');
            }

            if ($attachment->mimeType === null) {
                throw new Exception('Attachment\'s mime type not set');
            }

            if ($attachment->disposition === null) {
                throw new Exception('Attachment\'s disposition not set');
            }
        }
    }
}
