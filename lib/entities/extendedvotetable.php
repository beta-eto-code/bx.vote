<?php

declare(strict_types=1);

namespace Bx\Vote\Entities;

use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Vote\VoteTable;

class ExtendedVoteTable extends VoteTable
{
    public static function getMap()
    {
        $map = parent::getMap();
        $map['QUESTIONS'] = (new OneToMany('QUESTIONS', ExtendedQuestionTable::class, 'VOTE'))
            ->configureJoinType('LEFT');
        $map['RESULTS'] = (new OneToMany('RESULTS', ExtendedEventTable::class, 'VOTE'))
            ->configureJoinType('LEFT');

        return $map;
    }
}
