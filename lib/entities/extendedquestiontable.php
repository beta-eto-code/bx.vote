<?php

declare(strict_types=1);

namespace Bx\Vote\Entities;

use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Vote\QuestionTable;

class ExtendedQuestionTable extends QuestionTable
{
    public static function getMap()
    {
        $map = parent::getMap();
        $map['ANSWERS'] = (new OneToMany('ANSWERS', ExtendedAnswerTable::class, 'QUESTION'))->configureJoinType('LEFT');

        return $map;
    }
}
