<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\ContentOrigin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentOriginTest extends TestCase
{
    #[Test]
    public function humanCaseHasCorrectValue(): void
    {
        self::assertSame('human', ContentOrigin::Human->value);
    }

    #[Test]
    public function aiGeneratedCaseHasCorrectValue(): void
    {
        self::assertSame('ai_generated', ContentOrigin::AiGenerated->value);
    }

    #[Test]
    public function aiAssistedCaseHasCorrectValue(): void
    {
        self::assertSame('ai_assisted', ContentOrigin::AiAssisted->value);
    }

    #[Test]
    public function allCasesArePresent(): void
    {
        $cases = ContentOrigin::cases();
        self::assertCount(3, $cases);
        self::assertContains(ContentOrigin::Human, $cases);
        self::assertContains(ContentOrigin::AiGenerated, $cases);
        self::assertContains(ContentOrigin::AiAssisted, $cases);
    }

    #[Test]
    public function canBeCreatedFromStringValue(): void
    {
        self::assertSame(ContentOrigin::Human, ContentOrigin::from('human'));
        self::assertSame(ContentOrigin::AiGenerated, ContentOrigin::from('ai_generated'));
        self::assertSame(ContentOrigin::AiAssisted, ContentOrigin::from('ai_assisted'));
    }
}
