<?php

declare(strict_types=1);

namespace Bx\Vote;

use Base\Vote\Interfaces\VoteResultInterface;
use Base\Vote\Interfaces\QuestionInterface;
use Base\Vote\Interfaces\AnswerResultInterface;
use Base\Vote\VoteSchema;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;
use Bitrix\Vote\EventAnswerTable;
use Bitrix\Vote\EventQuestionTable;
use Bitrix\Vote\EventTable;
use Bitrix\Vote\User;
use Exception;
use Throwable;


class BitrixVoteResultAltService extends BaseBitrixVoteResultService
{
    /**
     * @param VoteResultInterface $voteResultInterface
     * @return Result
     */
    public function saveVoteResult(VoteResultInterface $voteResult, int $userId = null): Result
    {
        $result = new Result();
        /**
         * @var Connection $connection
         */
        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            $voteResultId = (int)$voteResult->getProp('id');
            $vote = $voteResult->getVoteSchema();
            $voteId = $vote instanceof VoteSchema ? (int)$vote->getProp('id') : 0;

            if (!$voteId) {
                throw new Exception('Invalid vote!');
            }
    
            $voteUserId = (int)($userId ? 
                User::loadFromId($userId)->getId() : 
                ($voteResult->getProp('user_id') ?? User::getCurrent()->getId())
            );
            if (!$voteUserId) {
                throw new Exception('User not found!');
            }
    
            $context = Context::getCurrent();
            $dataVoteValue = $voteResult->getProp('date_vote');
            $voteData = [
                'VOTE_ID' => $voteId,
                'VOTE_USER_ID' => $voteUserId,
                'DATE_VOTE' => $dataVoteValue instanceof DateTime ? $dataVoteValue : new DateTime(),
                'STAT_SESSION_ID' => $voteResult->getProp('stat_session_id') ?? bitrix_sessid(),
                'IP' => $voteResult->getProp('ip') ?? $context->getServer()->getRemoteAddr(),
                'VALID' => $voteResult->getProp('valid') ?? 'Y',
                'VISIBLE' => $voteResult->getProp('visible') ?? 'Y',
            ];
    
            $voteSaveResult = null;
            if ($voteResultId > 0) {
                $voteSaveResult = EventTable::update($voteId, $voteData);
            } else {
                $voteSaveResult = EventTable::add($voteData);
            }
    
            if (!$voteSaveResult->isSuccess()) {
                throw new Exception(implode(', ', $voteSaveResult->getErrorMessages()));
            } elseif (!$voteResultId) {
                $voteResultId = (int)$voteSaveResult->getId();
                $voteResult->setProp('id', $voteResultId);
            }
    
            $this->deleteAnswerResults($voteResult);
            foreach($vote->getQuestions() as $question) {
                $this->saveQuestionAnswerResults($voteResult, $question);
            }

            $connection->queryExecute("
                update `b_vote` 
                set COUNTER = (
                    select count(ID) 
                    from `b_vote_event` 
                    where VOTE_ID = {$voteId} and VALID = 'Y'
                ) where ID = {$voteId}"
            );

            $connection->queryExecute("
                update `b_vote_user` 
                set COUNTER = (
                    select count(ID) 
                    from `b_vote_event` 
                    where VOTE_USER_ID = {$voteUserId} and VALID = 'Y'
                ) where ID = {$voteUserId}"
            );
        } catch(Throwable $e) {
            $connection->rollbackTransaction();
            return $result->addError(new Error($e->getMessage()));
        }

        $connection->commitTransaction();

        return $result;
    }

    /**
     * @param VoteResultInterface $voteResult
     * @return void
     */
    private function deleteAnswerResults(VoteResultInterface $voteResult)
    {
        foreach($voteResult->getAnswerResults('delete') as $answerResult) {
            /**
             * @var AnswerResultInterface $answerResult
             */
            $answerResultId = (int)$answerResult->getProp('id');
            if ($answerResultId > 0) {
                EventAnswerTable::delete($answerResultId);
            }

            $voteResult->removeAnswerResult($answerResult, true);
        }
    }

    /**
     * @param VoteResultInterface $voteResult
     * @param QuestionInterface $question
     * @return void
     */
    private function saveQuestionAnswerResults(VoteResultInterface $voteResult, QuestionInterface $question)
    {
        $vote = $voteResult->getVoteSchema();
        $voteId = $vote instanceof VoteSchema ? (int)$vote->getProp('id') : 0;
        $voteResultId = (int)$voteResult->getProp('id');
        $questionId = (int)$question->getProp('id');
        if (!$questionId) {
            throw new Exception('Invalid question!');
        }

        $questionResultId = null;
        foreach($voteResult->getAnswerResultsByQuestion($question) as $answerResult){
            /**
             * @var AnswerResultInterface $answerResult
             */
            $answerVariant = $answerResult->getAnswerVariant();
            $answerVariantId = (int)$answerVariant->getProp('id');
            $answerResultId = (int)$answerResult->getProp('id');
            $questionResultId = $questionResultId ?? (int)$answerResult->getProp('question_result_id');
            if (!$questionResultId) {
                $questionResultId = $this->saveQuestionResult($voteResultId, $questionId);
            }
            $answerResultData = [
                'EVENT_QUESTION_ID' => $questionResultId,
                'ANSWER_ID' => $answerVariantId,
                'MESSAGE' => $answerResult->getMessage(),
            ];
            $resultAnswer = null;
            if ($answerResultId > 0) {
                $resultAnswer = EventAnswerTable::update($answerResultId, $answerResultData);
            } else {
                $resultAnswer = EventAnswerTable::add($answerResultData);
            }
            if (!$resultAnswer->isSuccess()) {
                throw new Exception(implode(', ', $resultAnswer->getErrorMessages()));
            } elseif (!$answerResultId) {
                $answerResultId = (int)$resultAnswer->getId();
            }
        }
    }

    /**
     * @param integer $voteResultId
     * @param integer $questionId
     * @return integer
     */
    private function saveQuestionResult(int $voteResultId, int $questionId): int
    {
        $questionResultData = [
            'EVENT_ID' => $voteResultId,
            'QUESTION_ID' => $questionId,
        ];

        $result = EventQuestionTable::add($questionResultData);
        if (!$result->isSuccess()) {
            throw new Exception(implode(', ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }
}
