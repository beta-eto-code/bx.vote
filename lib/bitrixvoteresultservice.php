<?php

declare(strict_types=1);

namespace Bx\Vote;

use Base\Vote\Interfaces\AnswerResultInterface;
use Base\Vote\Interfaces\AnswerVariantInterface;
use Base\Vote\Interfaces\AnswerVariantType;
use Base\Vote\Interfaces\VoteResultInterface;
use Base\Vote\Interfaces\VoteSchemaInterface;
use Bitrix\Main\Result;
use Bitrix\Vote\Vote;

class BitrixVoteResultService extends BaseBitrixVoteResultService
{
    /**
     * @param VoteResultInterface $voteResultInterface
     * @return Result
     */
    public function saveVoteResult(VoteResultInterface $voteResult): Result
    {
        $result = new Result();
        $vote = $voteResult->getVoteSchema();
        $voteId = $vote instanceof VoteSchemaInterface ? (int)$vote->getProp('id') : 0;

        /**
         * @var Vote $bxVote
         */
        $bxVote = Vote::loadFromId($voteId);

        $requestVote = ['EXTRA' => ['HIDDEN' => 'Y']];
        foreach($voteResult->getAnswerResults() as $answerResult) {
            /**
             * @var AnswerResultInterface $answerResult
             */
            $this->prepareAnswerForSaveResult($answerResult, $requestVote);
        }

        $isSuccess = (bool)$bxVote->voteFor($requestVote);
        if (!$isSuccess) {
            $result->addErrors($bxVote->getErrors());
        }

        return $result;
    }

    /**
     * @param AnswerResultInterface $answerResult
     * @param [type] $resultData
     * @return void
     */
    private function prepareAnswerForSaveResult(AnswerResultInterface $answerResult, &$resultData)
    {
        /**
         * @var AnswerResultInterface $answerResult
         */
        $answerVariant = $answerResult->getAnswerVariant();
        if (!($answerVariant instanceof AnswerVariantInterface)) {
            return;
        }

        $answerVariantId = (int)$answerVariant->getProp('id');
        $question = $answerVariant->getQueston();
        $questionId = (int)$question->getProp('id');
        $answerVariantType = (int)$answerResult->getAnswerVariant()->getType();

        switch($answerVariantType) {
            case AnswerVariantType::RADIO:
                $fieldName = "vote_radio_{$questionId}";
                $resultData[$fieldName] = $answerVariantId;
                break;
            case AnswerVariantType::DROPDOWN:
                $fieldName = "vote_dropdown_{$questionId}";
                $resultData[$fieldName] = $answerVariantId;
                break;
            case AnswerVariantType::MULTISELECT:
                $fieldName = "vote_multiselect_{$questionId}";
                $resultData[$fieldName][$answerVariantId] = $answerVariantId;
                break;
            case AnswerVariantType::CHECKBOX:
                $fieldName = "vote_checkbox_{$questionId}";
                $resultData[$fieldName][$answerVariantId] = $answerVariantId;
                break;
            case AnswerVariantType::TEXT:
                $fieldName = "vote_field_{$answerVariantId}";
                $resultData[$fieldName] = $answerResult->getMessage();
                break;
            case AnswerVariantType::TEXTAREA:
                $fieldName = "vote_memo_{$answerVariantId}";
                $resultData[$fieldName] = $answerResult->getMessage();
                break;
        }
    }
}