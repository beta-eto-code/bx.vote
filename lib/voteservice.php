<?php

declare(strict_types=1);

namespace Bx\Vote;

use Base\Vote\Interfaces\VoteResultInterface;
use Base\Vote\Interfaces\VoteResultServiceInterface;
use Base\Vote\Interfaces\VoteSchemaInterface;
use Base\Vote\Interfaces\VoteServiceInterface;
use Bitrix\Main\Result;
use Bx\Model\Interfaces\CollectionInterface;

class VoteService implements VoteServiceInterface, VoteResultServiceInterface
{
    /**
     * @var VoteServiceInterface
     */
    private $voteService;
    /**
     * @var VoteResultServiceInterface
     */
    private $voteResultService;

    public function __construct()
    {
        $this->voteService = new BitrixVoteService();
        $this->voteResultService = new BitrixVoteResultService();
    }

    /**
     * @param integer $id
     * @return VoteSchemaInterface|null
     */
    public function getVoteSchemaById(int $id): ?VoteSchemaInterface
    {
        return $this->voteService->getVoteSchemaById($id);
    }

    /**
     * @param array $criteria
     * @return integer
     */
    public function getVoteSchemaCount(array $criteria): int
    {
        return $this->voteService->getVoteSchemaCount($criteria);
    }

    /**
     * @param array $criteria
     * @param integer|null $limit
     * @param integer|null $offset
     * @return VoteSchemaInterface[]|CollectionInterface
     */
    public function getVoteSchemasByCriteria(array $criteria, int $limit = null, int $offset = null): CollectionInterface
    {
        return $this->voteService->getVoteSchemasByCriteria($criteria, $limit, $offset);
    }

    /**
     * @param VoteSchemaInterface $voteSchema
     * @return Result
     */
    public function saveVote(VoteSchemaInterface $voteSchema): Result
    {
        return $this->voteService->saveVote($voteSchema);
    }

    /**
     * @param VoteResultInterface $voteResultInterface
     * @return Result
     */
    public function saveVoteResult(VoteResultInterface $voteResult): Result
    {
        return $this->voteResultService->saveVoteResult($voteResult);
    }

    /**
     * @param VoteSchemaInterface $voteSchema
     * @param integer $limit
     * @return VoteResultInterface[]|CollectionInterface
     */
    public function getVoteResultList(VoteSchemaInterface $voteSchema, array $params = []): CollectionInterface
    {
        return $this->voteResultService->getVoteResultList($voteSchema, $params);
    }

    /**
     * @param VoteSchemaInterface $voteSchemaInterface
     * @param integer $userId
     * @return VoteResultInterface|null
     */
    public function getVoteResultByUser(VoteSchemaInterface $voteSchema, int $userId): ?VoteResultInterface
    {
        return $this->voteResultService->getVoteResultByUser($voteSchema, $userId);
    }
}
