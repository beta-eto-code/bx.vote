<?php

declare(strict_types=1);

namespace Bx\Vote;

use Base\Vote\Interfaces\VoteResultServiceInterface;
use Bx\Vote\Entities\ExtendedEventTable;
use Base\Vote\Interfaces\VoteResultInterface;
use Base\Vote\Interfaces\VoteSchemaInterface;
use Base\Vote\Interfaces\QuestionInterface;
use Base\Vote\Interfaces\AnswerVariantInterface;
use Base\Vote\VoteResult;
use Bitrix\Vote\EO_EventAnswer_Collection;
use Bitrix\Vote\EO_EventQuestion_Collection;
use Bitrix\Vote\User;
use Bx\Model\Collection;
use Bx\Model\Interfaces\CollectionInterface;

abstract class BaseBitrixVoteResultService implements VoteResultServiceInterface
{
    /**
     * @param VoteSchemaInterface $voteSchema
     * @param array $params
     * @return VoteResultInterface[]|CollectionInterface
     */
    public function getVoteResultList(VoteSchemaInterface $voteSchema, array $params = []): CollectionInterface
    {
        $resultCollection = new Collection();
        $voteId = (int)$voteSchema->getProp('id');
        if (!$voteId) {
            return $resultCollection;
        }

        $params['select'] = [
            '*',
            'EVENT_QUESTIONS',
            'EVENT_QUESTIONS.EVENT_ANSWERS',
        ];

        unset($params['filter']['VOTE_ID']);
        $params['filter']['=VOTE_ID'] = $voteId;
        $collection = ExtendedEventTable::getList($params)->fetchCollection();
        foreach ($collection as $voteResultItem) {
            $result = new VoteResult($voteSchema, [
                'props' => [
                    'id' => $voteResultItem->getId(),
                    'user_id' => $voteResultItem->getVoteUserId(),
                    'stat_session_id' => $voteResultItem->getStatSessionId(),
                    'ip' => $voteResultItem->getIp(),
                    'valid' => $voteResultItem->getValid(),
                    'visible' => $voteResultItem->getVisible(),
                ],
            ]);

            /**
             * @var EO_EventQuestion_Collection $questions
             */
            $questions = $voteResultItem->getEventQuestions();
            foreach ($questions as $question) {
                /**
                 * @var EO_EventAnswer_Collection $answers
                 */
                $answers = $question->getEventAnswers();
                foreach ($answers as $answer) {
                    $answerVariantId = $answer->getAnswerId();
                    $answerVariant = $this->getAnswerVariantById($voteSchema, $answerVariantId);
                    if (!($answerVariant instanceof AnswerVariantInterface)) {
                        continue;
                    }

                    $resultAnswer = $result->cerateAnswerResult($answerVariant, $answer->getMessage());
                    $resultAnswer->setProp('id', $answer->getId());
                }
            }

            $resultCollection->append($result);
        }

        return $resultCollection;
    }

    /**
     * @param VoteSchemaInterface $voteSchema
     * @param integer $answerVariantId
     * @return AnswerVariantInterface|null
     */
    private function getAnswerVariantById(
        VoteSchemaInterface $voteSchema,
        int $answerVariantId
    ): ?AnswerVariantInterface {
        foreach ($voteSchema->getQuestions() as $question) {
            /**
             * @var QuestionInterface $question
             */
            foreach ($question->getAnswerVariants() as $answerVariant) {
                /**
                 * @var AnswerVariantInterface $answerVariant
                 */
                if ($answerVariantId === (int)$answerVariant->getProp('id')) {
                    return $answerVariant;
                }
            }
        }

        return null;
    }

    /**
     * @param VoteSchemaInterface $voteSchema
     * @param integer $userId
     * @return VoteResultInterface|null
     */
    public function getVoteResultByUser(VoteSchemaInterface $voteSchema, int $userId): ?VoteResultInterface
    {
        $voteUserId = User::loadFromId($userId)->getId();
        $list = $this->getVoteResultList($voteSchema, [
            'filter' => [
                '=VOTE_USER_ID' => $voteUserId,
            ],
        ]);
        $voteResult = current($list);

        return $voteResult instanceof VoteResultInterface ? $voteResult : null;
    }
}
