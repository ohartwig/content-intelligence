<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

enum ScoreThreshold: string
{
    case Critical = 'critical';
    case NeedsImprovement = 'needs_improvement';
    case AiReady = 'ai_ready';

    public static function fromScore(float $score): self
    {
        return match (true) {
            $score < 50.0 => self::Critical,
            $score < 80.0 => self::NeedsImprovement,
            default => self::AiReady,
        };
    }
}
