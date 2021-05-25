<?php

declare(strict_types=1);

namespace Bx\Vote\Entities;

use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Vote\EventTable;

class ExtendedEventTable extends EventTable
{
    public static function getMap()
    {
        $map = parent::getMap();
        $map['EVENT_QUESTIONS'] = (new OneToMany('EVENT_QUESTIONS', ExtendedEventQuestionTable::class, 'VOTE'))->configureJoinType('LEFT');
        $map['VOTE'] = new Reference('VOTE', ExtendedVoteTable::class, Join::on("this.VOTE_ID", "ref.ID"));

        return $map;
    }
}
