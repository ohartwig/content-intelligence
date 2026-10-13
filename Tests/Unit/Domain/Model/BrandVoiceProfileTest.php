<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\BrandVoiceProfile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrandVoiceProfileTest extends TestCase
{
    #[Test]
    public function createReturnsInstanceWithIdentifier(): void
    {
        $profile = BrandVoiceProfile::create('corporate');

        self::assertSame('corporate', $profile->getIdentifier());
    }

    #[Test]
    public function defaultValuesAreCorrect(): void
    {
        $profile = BrandVoiceProfile::create('default');

        self::assertSame([], $profile->getToneAttributes());
        self::assertSame('', $profile->getAddressForm());
        self::assertSame([], $profile->getForbiddenTerms());
        self::assertSame([], $profile->getRequiredTerms());
        self::assertNull($profile->getMaxSentenceLength());
        self::assertTrue($profile->getAllowHashtags());
        self::assertTrue($profile->getAllowTargetAudienceNaming());
        self::assertSame('de', $profile->getLanguage());
    }

    #[Test]
    public function toneReturnsSelfForFluency(): void
    {
        $profile = BrandVoiceProfile::create('test');
        $result = $profile->tone(['professional', 'friendly']);

        self::assertSame($profile, $result);
    }

    #[Test]
    public function toneSetsToneAttributes(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->tone(['professional', 'friendly']);

        self::assertSame(['professional', 'friendly'], $profile->getToneAttributes());
    }

    #[Test]
    public function addressFormSetsValue(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->addressForm('Sie');

        self::assertSame('Sie', $profile->getAddressForm());
    }

    #[Test]
    public function addressFormReturnsSelfForFluency(): void
    {
        $profile = BrandVoiceProfile::create('test');
        $result = $profile->addressForm('Du');

        self::assertSame($profile, $result);
    }

    #[Test]
    public function forbiddenTermsSetsValue(): void
    {
        $terms = ['billig', 'gratis', 'umsonst'];
        $profile = BrandVoiceProfile::create('test')
            ->forbiddenTerms($terms);

        self::assertSame($terms, $profile->getForbiddenTerms());
    }

    #[Test]
    public function requiredTermsSetsValue(): void
    {
        $terms = ['Premium', 'Qualitaet'];
        $profile = BrandVoiceProfile::create('test')
            ->requiredTerms($terms);

        self::assertSame($terms, $profile->getRequiredTerms());
    }

    #[Test]
    public function maxSentenceLengthSetsValue(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->maxSentenceLength(25);

        self::assertSame(25, $profile->getMaxSentenceLength());
    }

    #[Test]
    public function noHashtagsDisablesHashtags(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->noHashtags();

        self::assertFalse($profile->getAllowHashtags());
    }

    #[Test]
    public function noHashtagsReturnsSelfForFluency(): void
    {
        $profile = BrandVoiceProfile::create('test');
        $result = $profile->noHashtags();

        self::assertSame($profile, $result);
    }

    #[Test]
    public function noTargetAudienceNamingDisablesTargetAudienceNaming(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->noTargetAudienceNaming();

        self::assertFalse($profile->getAllowTargetAudienceNaming());
    }

    #[Test]
    public function noTargetAudienceNamingReturnsSelfForFluency(): void
    {
        $profile = BrandVoiceProfile::create('test');
        $result = $profile->noTargetAudienceNaming();

        self::assertSame($profile, $result);
    }

    #[Test]
    public function languageSetsValue(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->language('en');

        self::assertSame('en', $profile->getLanguage());
    }

    #[Test]
    public function languageReturnsSelfForFluency(): void
    {
        $profile = BrandVoiceProfile::create('test');
        $result = $profile->language('fr');

        self::assertSame($profile, $result);
    }

    #[Test]
    public function fluentBuilderChainWorksCorrectly(): void
    {
        $profile = BrandVoiceProfile::create('corporate')
            ->tone(['professional', 'authoritative'])
            ->addressForm('Sie')
            ->forbiddenTerms(['billig', 'gratis'])
            ->requiredTerms(['Premium'])
            ->maxSentenceLength(20)
            ->noHashtags()
            ->noTargetAudienceNaming()
            ->language('en');

        self::assertSame('corporate', $profile->getIdentifier());
        self::assertSame(['professional', 'authoritative'], $profile->getToneAttributes());
        self::assertSame('Sie', $profile->getAddressForm());
        self::assertSame(['billig', 'gratis'], $profile->getForbiddenTerms());
        self::assertSame(['Premium'], $profile->getRequiredTerms());
        self::assertSame(20, $profile->getMaxSentenceLength());
        self::assertFalse($profile->getAllowHashtags());
        self::assertFalse($profile->getAllowTargetAudienceNaming());
        self::assertSame('en', $profile->getLanguage());
    }

    #[Test]
    public function toArrayReturnsCompleteRepresentation(): void
    {
        $profile = BrandVoiceProfile::create('corporate')
            ->tone(['professional'])
            ->addressForm('Sie')
            ->forbiddenTerms(['billig'])
            ->requiredTerms(['Premium'])
            ->maxSentenceLength(30)
            ->noHashtags()
            ->noTargetAudienceNaming()
            ->language('en');

        $expected = [
            'identifier' => 'corporate',
            'toneAttributes' => ['professional'],
            'addressForm' => 'Sie',
            'forbiddenTerms' => ['billig'],
            'requiredTerms' => ['Premium'],
            'maxSentenceLength' => 30,
            'allowHashtags' => false,
            'allowTargetAudienceNaming' => false,
            'language' => 'en',
        ];

        self::assertSame($expected, $profile->toArray());
    }

    #[Test]
    public function toArrayReturnsDefaultValues(): void
    {
        $profile = BrandVoiceProfile::create('minimal');

        $array = $profile->toArray();

        self::assertSame('minimal', $array['identifier']);
        self::assertSame([], $array['toneAttributes']);
        self::assertSame('', $array['addressForm']);
        self::assertSame([], $array['forbiddenTerms']);
        self::assertSame([], $array['requiredTerms']);
        self::assertNull($array['maxSentenceLength']);
        self::assertTrue($array['allowHashtags']);
        self::assertTrue($array['allowTargetAudienceNaming']);
        self::assertSame('de', $array['language']);
    }
}
