<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\BrandVoiceViolation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrandVoiceViolationTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithAllProperties(): void
    {
        $violation = new BrandVoiceViolation(
            text: 'Das ist billig',
            rule: 'forbidden_term',
            suggestion: 'Verwenden Sie stattdessen "preiswert"',
        );

        self::assertSame('Das ist billig', $violation->text);
        self::assertSame('forbidden_term', $violation->rule);
        self::assertSame('Verwenden Sie stattdessen "preiswert"', $violation->suggestion);
    }

    #[Test]
    public function canBeConstructedWithEmptyStrings(): void
    {
        $violation = new BrandVoiceViolation(
            text: '',
            rule: '',
            suggestion: '',
        );

        self::assertSame('', $violation->text);
        self::assertSame('', $violation->rule);
        self::assertSame('', $violation->suggestion);
    }
}
