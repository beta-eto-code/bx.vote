<?php

declare(strict_types=1);

namespace Bx\Vote\Entities;

use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Vote\EventAnswerTable;

class ExtendedEventAnswerTable extends EventAnswerTable
{
    public static function getMap()
    {
        $map = parent::getMap();
        $map['ANSWER'] = new Reference('ANSWER', ExtendedAnswerTable::class, Join::on("this.ANSWER_ID", "ref.ID"));
        $map['EVENT_QUESTION'] = new Reference('EVENT_QUESTION', ExtendedEventQuestionTable::class, Join::on("this.EVENT_QUESTION_ID", "ref.ID"));

        return $map;
    }
}
