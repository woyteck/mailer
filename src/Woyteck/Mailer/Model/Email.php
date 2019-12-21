<?php
declare(strict_types=1);

namespace Woyteck\Mailer\Model;

use Woyteck\Db\ModelAbstract;

/**
 * Class Email
 * @package Woyteck\Model
 *
 * @property int $id
 * @property string $status
 * @property int $priority
 * @property int $datetime_save
 * @property int $datetime_sent
 * @property string $authors
 * @property string $recipients
 * @property string $reply_to
 * @property string $subject
 * @property string $encoding
 * @property string $context
 * @property string $context_identifier
 */
class Email extends ModelAbstract
{
    const STATUS_WAITING = 'waiting';
    const STATUS_SENT = 'sent';
    const STATUS_ERROR = 'error';

    /**
     * @var string
     */
    public static $tableName = 'email';

    /**
     * @var string
     */
    public static $primaryKey = 'id';

    /**
     * @var string
     */
    public static $tableAlias = 'e';
}
