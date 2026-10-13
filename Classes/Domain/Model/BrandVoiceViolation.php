<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

final readonly class BrandVoiceViolation
{
    public function __construct(
        public string $text,
        public string $rule,
        public string $suggestion,
    ) {}
}
