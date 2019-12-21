<?php
declare(strict_types=1);

namespace Woyteck\Mailer;

class Attachment
{
    const ENCODING_BASE64 = 'base64';
    const DISPOSITION_ATTACHMENT = 'attachment';
    const DISPOSITION_INLINE = 'inline';

    public ?string $mimeType = null;
    public ?string $charset = null;
    public ?string $encoding = self::ENCODING_BASE64;
    public ?string $disposition = self::DISPOSITION_ATTACHMENT;
    public ?string $filename = null;
    public ?string $contentId = null;
    public ?string $contents = null;
}
