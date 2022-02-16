<?php

declare(strict_types=1);

namespace Bx\Vote;

use Base\Vote\AnswerVariant;
use Bx\Vote\Entities\ExtendedQuestionTable;
use Bx\Vote\Entities\ExtendedVoteTable;
use Bx\Vote\Entities\ExtendedAnswerTable;
use Bx\Vote\Entities\ExtendedEventTable;
use Base\Vote\Interfaces\VoteSchemaInterface;
use Base\Vote\Interfaces\VoteServiceInterface;
use Base\Vote\Interfaces\QuestionInterface;
use Base\Vote\Interfaces\AnswerVariantInterface;
use Base\Vote\Interfaces\QuestionType;
use Base\Vote\Question;
use Base\Vote\VoteSchema;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;
use Bitrix\Vote\EO_Question;
use Bitrix\Vote\EO_Answer;
use Bitrix\Vote\EO_Vote;
use Bitrix\Vote\UserTable;
use Bx\Model\Collection;
use Bx\Model\Interfaces\CollectionInterface;
use Bx\Model\Interfaces\QueryInterface;
use Exception;
use Throwable;

class BitrixVoteService implements VoteServiceInterface
{
    /**
     * @param integer $id
     * @return VoteSchemaInterface|null
     */
    public function getVoteSchemaById(int $id): ?VoteSchemaInterface
    {
        $voteSchema = $this->getVoteSchemasByCriteria([
            '=ID' => $id,
        ], 1)->first();

        return $voteSchema instanceof VoteSchemaInterface ? $voteSchema : null;
    }

    /**
     * Дополнительные параметры для фильтрации:
     * - ACTUAL_FOR - указываем идентификатор пользователя, будут выбраны опросы которые пользователь еще не проходил
     * - IS_SINGLE - возможные значения: Y (в опросе только один вопрос), N (в опросе более одного вопроса).
     *
     * @param array $criteria
     * @param integer|null $limit
     * @param integer|null $offset
     * @return VoteSchemaInterface[]|CollectionInterface
     */
    public function getVoteSchemasByCriteria(
        array $criteria,
        int $limit = null,
        int $offset = null
    ): CollectionInterface {
        Loader::includeModule('vote');

        $collection = new Collection();
        $idToSelect = $this->getFilteredIdList($criteria, $limit, $offset);
        if (empty($idToSelect)) {
            return $collection;
        }

        $criteria = [
            '=ID' => $idToSelect,
        ];

        $params = [
            'filter' => $criteria,
            'order' => [
                'C_SORT' => 'asc',
                'ID' => 'asc',
            ],
            'select' => [
                '*',
                'QUESTIONS',
                'QUESTIONS.ANSWERS',
            ],
        ];

        $voteCollection = ExtendedVoteTable::getList($params)->fetchCollection();
        foreach ($voteCollection as $voteElement) {
            $collection->append($this->buildVoteSchema($voteElement));
        }

        return $collection;
    }

    /**
     * Дополнительные параметры для фильтрации:
     * - ACTUAL_FOR - указываем идентификатор пользователя, будут выбраны опросы которые пользователь еще не проходил
     * - IS_SINGLE - возможные значения: Y (в опросе только один вопрос), N (в опросе более одного вопроса).
     *
     * @param QueryInterface $query
     * @return CollectionInterface
     */
    public function getVoteSchemasByQuery(QueryInterface $query): CollectionInterface
    {
        $params = [];

        $collection = new Collection();
        $idToSelect = $this->getFilteredIdList(
            $query->getFilter(),
            $query->getLimit(),
            $query->getOffset()
        );
        if (empty($idToSelect)) {
            return $collection;
        }

        $params['filter'] = [
            '=ID' => $idToSelect,
        ];

        $defaultSort = [
            'C_SORT' => 'asc',
            'ID' => 'asc',
        ];
        $params['order'] = $query->hasSort() ? $query->getSort() : $defaultSort;

        $requiredSelect = [
            'QUESTIONS',
            'QUESTIONS.ANSWERS'
        ];
        $params['select'] = $query->hasSelect() ?
            array_merge($requiredSelect, $query->getSelect()) :
            array_merge($requiredSelect, ['*']);

        $voteCollection = ExtendedVoteTable::getList($params)->fetchCollection();
        foreach ($voteCollection as $voteElement) {
            $collection->append($this->buildVoteSchema($voteElement));
        }

        return $collection;
    }

    /**
     * @param array $criteria
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    private function getParamsForFilter(array $criteria, int $limit = null, int $offset = null): array
    {
        $userId = (int)($criteria['=ACTUAL_FOR'] ?? ($criteria['ACTUAL_FOR'] ?? 0));
        unset($criteria['ACTUAL_FOR']);
        unset($criteria['=ACTUAL_FOR']);

        $voteUserId = [0];
        if ($userId > 0) {
            $voteUserId = [];
            if (!$voteUserId) {
                $voteUserQuery = UserTable::getList([
                    'filter' => [
                        '=AUTH_USER_ID' => $userId,
                    ],
                    'select' => [
                        'ID'
                    ]
                ]);
                while ($voteUserData = $voteUserQuery->fetch()) {
                    $voteUserId[] = (int)$voteUserData['ID'];
                }
            }

            if (empty($voteUserId)) {
                $voteUserId = [0];
            }
        }

        $params = [
            'filter' => $criteria,
            'order' => [
                'C_SORT' => 'asc',
                'ID' => 'asc',
            ],
            'runtime' => [
                'IS_SINGLE' => new ExpressionField('IS_SINGLE', 'IF(COUNT(%s) <= 1, "Y", "N")', 'QUESTIONS.ID'),
                'RESULT' => (new Reference(
                    'RESULT',
                    ExtendedEventTable::class,
                    Join::on("this.ID", "ref.VOTE_ID")->whereIn('ref.VOTE_USER_ID', $voteUserId)
                ))->configureJoinType('LEFT')
            ],
            'group' => [
                'ID',
            ],
            'select' => [
                'ID',
                'RESULT'
            ],
        ];

        if ($limit > 0) {
            $params['limit'] = $limit;
        }

        if ($offset > 0) {
            $params['offset'] = $offset;
        }

        if ($voteUserId > 0) {
            $params['filter']['=RESULT.ID'] = null;
        }

        return $params;
    }

    /**
     * @param array $criteria
     * @param integer|null $limit
     * @param integer|null $offset
     * @return array
     */
    private function getFilteredIdList(array $criteria, int $limit = null, int $offset = null): array
    {
        $result = [];
        $params = $this->getParamsForFilter($criteria, $limit, $offset);
        $query = ExtendedVoteTable::getList($params);
        while ($item = $query->fetch()) {
            $id = (int)$item['ID'];
            if ($id > 0) {
                $result[] = (int)$item['ID'];
            }
        }

        return $result;
    }

    /**
     * @param array $criteria
     * @return integer
     */
    public function getVoteSchemaCount(array $criteria): int
    {
        $params = $this->getParamsForFilter($criteria);
        $params['count_total'] = true;

        return ExtendedVoteTable::getList($params)->getCount();
    }

    /**
     * @param EO_Vote $voteElement
     * @return VoteSchemaInterface
     */
    private function buildVoteSchema($voteElement): VoteSchemaInterface
    {
        $voteSchema = new VoteSchema([
            'title' => $voteElement->getTitle(),
            'description' => $voteElement->getDescription(),
        ]);
        $voteSchema->setProp('id', $voteElement->getId());
        $voteSchema->setProp('sort', $voteElement->getCSort());
        $voteSchema->setProp('active', $voteElement->getActive());
        $voteSchema->setProp('anonymity', $voteElement->getAnonymity());
        $voteSchema->setProp('notify', $voteElement->getNotify());
        $voteSchema->setProp('date_start', $voteElement->getDateStart());
        $voteSchema->setProp('date_end', $voteElement->getDateEnd());
        $voteSchema->setProp('url', $voteElement->getUrl());
        $voteSchema->setProp('event1', $voteElement->getEvent1());
        $voteSchema->setProp('event2', $voteElement->getEvent2());
        $voteSchema->setProp('event3', $voteElement->getEvent3());
        $voteSchema->setProp('unique_type', $voteElement->getUniqueType());
        $voteSchema->setProp('keep_ip_sec', $voteElement->getKeepIpSec());
        $voteSchema->setProp('options', $voteElement->getOptions());
        $voteSchema->setProp('description_type', $voteElement->getDescriptionType());
        $voteSchema->setProp('channel_id', $voteElement->getChannelId());

        $questionColleciton = $voteElement->getQuestions() ?? [];
        foreach ($questionColleciton as $questionElement) {
            /**
             * @var EO_Question $questionElement
             */
            $answerCollection = $questionElement->getAnswers() ?? [];
            $type = (int)$questionElement->getFieldType();
            $isMultiple = in_array($type, [
                QuestionType::CHECKBOX,
                QuestionType::MULTISELECT,
                QuestionType::MIXED_TYPE
            ]);
            $question = new Question([
                'title' => $questionElement->getQuestion(),
                'type' => $type,
                'is_required' => (bool)$questionElement->getRequired(),
                'is_multiple' => $isMultiple,
            ]);
            $question->setProp('id', $questionElement->getId());
            $voteSchema->addQuestion($question);

            foreach ($answerCollection as $answerElement) {
                /**
                 * @var EO_Answer $answerElement
                 */
                $answerVariant = new AnswerVariant([
                    'title' => $answerElement->getMessage(),
                    'type' => (int)$answerElement->getFieldType(),
                ]);
                $answerVariant->setProp('id', $answerElement->getId());
                $question->addAnswerVariant($answerVariant);
            }
        }

        return $voteSchema;
    }

    /**
     * @param VoteSchemaInterface $voteSchema
     * @return Result
     */
    public function saveVote(VoteSchemaInterface $voteSchema): Result
    {
        /**
         * @var Connection $connection
         */
        $connection = Application::getConnection();
        $connection->startTransaction();
        $result = new Result();
        try {
            $voteId = (int)$voteSchema->getProp('id');
            $channelId = (int)($voteSchema->getProp('channel_id') ?? 1);

            $activeValue = $voteSchema->getProp('active');
            $isActive = $activeValue === null || (bool)$activeValue;
            $dateStartValue = $voteSchema->getProp('date_start');
            $dateEndValue = $voteSchema->getProp('date_end');
            $dateStart = $dateStartValue instanceof DateTime ? $dateStartValue : new DateTime();
            $dateEnd = $dateEndValue instanceof DateTime ? $dateEndValue : (clone $dateStart)->add('+30 days');
            $voteData = [
                'CHANNEL_ID' => $channelId,
                'TITLE' => $voteSchema->getTitle(),
                'DESCRIPTION' => $voteSchema->getDescription(),
                'TIMESTAMP_X' => new DateTime(),
                'C_SORT' => $voteSchema->getProp('sort') ?? 100,
                'ACTIVE' => $isActive ? 'Y' : 'N',
                'ANONYMITY' => (int)$voteSchema->getProp('anonymity'),
                'NOTIFY' => (int)$voteSchema->getProp('notify'),
                'DATE_START' => $dateStart,
                'DATE_END' => $dateEnd,
                'URL' => $voteSchema->getProp('url') ?? '',
                'DESCRIPTION_TYPE' => $voteSchema->getProp('description_type') ?? 'text',
                'EVENT1' => $voteSchema->getProp('event1') ?? 'vote',
                'EVENT2' => $voteSchema->getProp('event2'),
                'EVENT3' => $voteSchema->getProp('event3'),
                'UNIQUE_TYPE' => (int)($voteSchema->getProp('unique_type') ?? 6),
                'KEEP_IP_SEC' => (int)($voteSchema->getProp('keep_ip_sec') ?? 0),
                'OPTIONS' => (int)($voteSchema->getProp('options') ?? 1),
            ];

            $saveResult = null;
            if ($voteId > 0) {
                $saveResult = ExtendedVoteTable::update($voteId, $voteData);
            } else {
                $saveResult = ExtendedVoteTable::add($voteData);
            }

            if (!$saveResult->isSuccess()) {
                throw new Exception(implode(', ', $saveResult->getErrorMessages()));
            } elseif (!$voteId) {
                $voteId = (int)$saveResult->getId();
                $voteSchema->setProp('id', $voteId);
            }

            $this->saveQuestions($voteSchema);
        } catch (Throwable $e) {
            $connection->rollbackTransaction();
            return $result->addError(new Error($e->getMessage()));
        }

        $connection->commitTransaction();

        return $result;
    }

    /**
     * @param VoteSchemaInterface $voteSchema
     * @return void
     * @throws Exception
     */
    private function deleteQuestions(VoteSchemaInterface $voteSchema)
    {
        foreach ($voteSchema->getQuestions('delete') as $question) {
            /**
             * @var QuestionInterface $question
             */
            $questionId = (int)$question->getProp('id');
            if (!$questionId) {
                $voteSchema->removeQuestion($question, true);
                continue;
            }

            $result = ExtendedQuestionTable::delete($questionId);
            if (!$result->isSuccess()) {
                throw new Exception(implode(', ', $result->getErrorMessages()));
            }

            $voteSchema->removeQuestion($question, true);
        }
    }

    /**
     * @param VoteSchemaInterface $voteSchema
     * @return void
     * @throws Exception
     */
    private function saveQuestions(VoteSchemaInterface $voteSchema)
    {
        $voteId = (int)$voteSchema->getProp('id');
        if (!$voteId) {
            throw new Exception('Invalid vote!');
        }

        $this->deleteQuestions($voteSchema);
        foreach ($voteSchema->getQuestions() as $question) {
            /**
             * @var QuestionInterface $question
             */
            $questionId = (int)$question->getProp('id');
            $questionData = [
                'VOTE_ID' => $voteId,
                'QUESTION' => $question->getTitle(),
                'REQUIRED' => $question->isRequired() ? 'Y' : 'N',
                'FIELD_TYPE' => $question->getType(),
                'TIMESTAMP_X' => new DateTime(),
            ];

            $saveResult = null;
            if ($questionId > 0) {
                $saveResult = ExtendedQuestionTable::update($questionId, $questionData);
            } else {
                $saveResult = ExtendedQuestionTable::add($questionData);
            }

            if (!$saveResult->isSuccess()) {
                throw new Exception(implode(', ', $saveResult->getErrorMessages()));
            } elseif (!$questionId) {
                $questionId = (int)$saveResult->getId();
                $question->setProp('id', $questionId);
            }

            $this->saveAnswerVariants($question);
        }
    }

    /**
     * @param QuestionInterface $question
     * @return void
     * @throws Exception
     */
    private function deleteAnswerVariants(QuestionInterface $question)
    {
        foreach ($question->getAnswerVariants('delete') as $answerVariant) {
            /**
             * @var QuestionInterface $question
             */
            $answerVariantId = (int)$answerVariant->getProp('id');
            if (!$answerVariantId) {
                $question->removeAnswerVariant($answerVariant, true);
                continue;
            }

            $result = ExtendedAnswerTable::delete($answerVariantId);
            if (!$result->isSuccess()) {
                throw new Exception(implode(', ', $result->getErrorMessages()));
            }

            $question->removeAnswerVariant($answerVariant, true);
        }
    }

    /**
     * @param QuestionInterface $question
     * @return void
     * @throws Exception
     */
    private function saveAnswerVariants(QuestionInterface $question)
    {
        $questionId = (int)$question->getProp('id');
        if (!$questionId) {
            throw new Exception('Invalid question!');
        }

        $this->deleteAnswerVariants($question);
        foreach ($question->getAnswerVariants() as $answerVariant) {
            /**
             * @var AnswerVariantInterface $answerVariant
             */
            $answerVariantId = (int)$answerVariant->getProp('id');
            $answerVariantData = [
                'QUESTION_ID' => $questionId,
                'MESSAGE' => $answerVariant->getTitle(),
                'FIELD_TYPE' => $answerVariant->getType(),
                'TIMESTAMP_X' => new DateTime(),
            ];

            $saveResult = null;
            if ($answerVariantId > 0) {
                $saveResult = ExtendedAnswerTable::update($answerVariantId, $answerVariantData);
            } else {
                $saveResult = ExtendedAnswerTable::add($answerVariantData);
            }

            if (!$saveResult->isSuccess()) {
                throw new Exception(implode(', ', $saveResult->getErrorMessages()));
            } elseif (!$answerVariantId) {
                $answerVariantId = (int)$saveResult->getId();
                $answerVariant->setProp('id', $answerVariantId);
            }
        }
    }
}
