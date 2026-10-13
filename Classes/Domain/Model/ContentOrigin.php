<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

enum ContentOrigin: string
{
    case Human = 'human';
    case AiGenerated = 'ai_generated';
    case AiAssisted = 'ai_assisted';
}
