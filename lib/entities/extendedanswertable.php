<?php

declare(strict_types=1);

namespace Bx\Vote\Entities;

use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Vote\AnswerTable;
use Bitrix\Vote\QuestionTable;

class ExtendedAnswerTable extends AnswerTable
{
    public static function getMap()
    {
        $map = parent::getMap();
        $map['QUESTION'] = new Reference('QUESTION', QuestionTable::class, Join::on("this.QUESTION_ID", "ref.ID"));

        return $map;
    }
}
