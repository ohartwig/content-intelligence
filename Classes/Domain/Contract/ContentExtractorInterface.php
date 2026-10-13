<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Contract;

use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;

interface ContentExtractorInterface
{
    public function extractForPage(int $pageUid, int $languageUid = 0): ContentSnapshot;
}
