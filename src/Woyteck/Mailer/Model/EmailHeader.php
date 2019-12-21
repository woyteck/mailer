<?php

namespace Woyteck\Mailer\Model;

use Woyteck\Db\ModelAbstract;

/**
 * Class EmailHeader
 * @package Woyteck\Model
 *
 * @property int $id
 * @property int $email_id
 * @property string $name
 * @property string $value
 */
class EmailHeader extends ModelAbstract
{
    /**
     * @var string
     */
    public static $tableName = 'email_header';

    /**
     * @var string
     */
    public static $primaryKey = 'id';

    /**
     * @var string
     */
    public static $tableAlias = 'eh';
}
