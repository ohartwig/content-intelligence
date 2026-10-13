<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Contract;

use Koh\ContentIntelligence\Domain\Model\AnalysisResult;

interface AnalysisResultRepositoryInterface
{
    /**
     * @param AnalysisResult[] $results
     */
    public function saveForPage(int $pageUid, int $languageUid, array $results): void;

    /**
     * @return AnalysisResult[]
     */
    public function findByPage(int $pageUid, int $languageUid): array;

    public function deleteByPage(int $pageUid, int $languageUid): void;
}
