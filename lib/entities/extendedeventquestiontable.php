<?php

declare(strict_types=1);

namespace Bx\Vote\Entities;

use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Vote\EventQuestionTable;

class ExtendedEventQuestionTable extends EventQuestionTable
{
    public static function getMap()
    {
        $map = parent::getMap();
        $map['EVENT_ANSWERS'] = (new OneToMany('EVENT_ANSWERS', ExtendedEventAnswerTable::class, 'EVENT_QUESTION'))->configureJoinType('LEFT');

        return $map;
    }
}
