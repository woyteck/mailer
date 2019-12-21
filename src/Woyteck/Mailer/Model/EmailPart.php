<?php

namespace Woyteck\Mailer\Model;

use Woyteck\Db\ModelAbstract;

/**
 * Class EmailPart
 * @package Woyteck\Model
 *
 * @property int $id
 * @property int $email_id
 * @property string $mime_type
 * @property string $encoding
 * @property string $charset
 * @property string $disposition
 * @property string $filename
 * @property string $content_id
 * @property string $contents
 */
class EmailPart extends ModelAbstract
{
    const DISPOSITION_ATTACHMENT = 'attachment';
    const DISPOSITION_INLINE = 'inline';

    /**
     * @var string
     */
    public static $tableName = 'email_part';

    /**
     * @var string
     */
    public static $primaryKey = 'id';

    /**
     * @var string
     */
    public static $tableAlias = 'ep';
}
