<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\Configuration;

use Koh\ContentIntelligence\Infrastructure\Configuration\BrandVoiceConfigurationProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Settings\Settings;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * This provider turns site settings into a BrandVoiceProfile, and every field
 * it reads is optional. That makes the interesting cases the ones where a
 * setting is absent, empty or of the wrong shape — the profile still has to
 * come back usable, because the analyzers run against it unconditionally.
 */
final class BrandVoiceConfigurationProviderTest extends TestCase
{
    /**
     * @param array<string, mixed> $brandVoice
     */
    private function providerForSettings(array $brandVoice, int $rootPageId = 1): BrandVoiceConfigurationProvider
    {
        $settings = SiteSettings::create(new Settings(['contentIntelligence.brandVoice' => $brandVoice]));

        $site = $this->createMock(Site::class);
        $site->method('getRootPageId')->willReturn($rootPageId);
        $site->method('getSettings')->willReturn($settings);

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn(['main' => $site]);

        return new BrandVoiceConfigurationProvider($siteFinder);
    }

    #[Test]
    public function everyConfiguredFieldEndsUpOnTheProfile(): void
    {
        $provider = $this->providerForSettings([
            'identifier' => 'acme',
            'language' => 'en',
            'addressForm' => 'du',
            'toneAttributes' => ['direct', 'warm'],
            'forbiddenTerms' => ['synergy'],
            'requiredTerms' => ['open source'],
            'maxSentenceLength' => 18,
            'noHashtags' => true,
            'noTargetAudienceNaming' => true,
        ]);

        $profile = $provider->getProfile(1);

        self::assertSame('acme', $profile->getIdentifier());
        self::assertSame('en', $profile->getLanguage());
        self::assertSame('du', $profile->getAddressForm());
        self::assertSame(['direct', 'warm'], $profile->getToneAttributes());
        self::assertSame(['synergy'], $profile->getForbiddenTerms());
        self::assertSame(['open source'], $profile->getRequiredTerms());
        self::assertSame(18, $profile->getMaxSentenceLength());
    }

    /**
     * With no brand voice configured at all the provider still has to return a
     * profile — the analyzers would otherwise fail on a site that simply never
     * configured one.
     */
    #[Test]
    public function emptySettingsStillYieldADefaultProfile(): void
    {
        $profile = $this->providerForSettings([])->getProfile(1);

        self::assertSame('default', $profile->getIdentifier());
        self::assertSame('de', $profile->getLanguage());
        self::assertSame('Sie', $profile->getAddressForm());
    }

    /**
     * The list fields are only applied when they really are lists. A scalar in
     * the site configuration is a config mistake, and it must not end up as a
     * malformed profile that the analyzers then choke on.
     */
    #[Test]
    public function scalarValuesInListFieldsAreIgnored(): void
    {
        $profile = $this->providerForSettings([
            'toneAttributes' => 'direct',
            'forbiddenTerms' => 'synergy',
            'requiredTerms' => 'open source',
        ])->getProfile(1);

        self::assertSame([], $profile->getToneAttributes());
        self::assertSame([], $profile->getForbiddenTerms());
        self::assertSame([], $profile->getRequiredTerms());
    }

    /**
     * The switches are opt-in prohibitions and invert on the way in: the
     * setting is named "noHashtags", the profile stores "allowHashtags". Only a
     * truthy setting flips it, so a present-but-false entry has to leave the
     * permission untouched, exactly like an absent one.
     */
    #[Test]
    public function prohibitionsOnlyApplyWhenTheSettingIsTruthy(): void
    {
        $off = $this->providerForSettings([
            'noHashtags' => false,
            'noTargetAudienceNaming' => false,
        ])->getProfile(1);

        self::assertTrue($off->getAllowHashtags());
        self::assertTrue($off->getAllowTargetAudienceNaming());

        $on = $this->providerForSettings([
            'noHashtags' => true,
            'noTargetAudienceNaming' => true,
        ])->getProfile(1);

        self::assertFalse($on->getAllowHashtags());
        self::assertFalse($on->getAllowTargetAudienceNaming());
    }

    /**
     * When a root page is named, settings of other sites must not bleed in.
     * The loop skips non-matching sites, so a uid that matches nothing falls
     * through to the defaults rather than picking up the first site it saw.
     */
    #[Test]
    public function settingsOfOtherSitesAreNotUsedWhenARootPageIsNamed(): void
    {
        $provider = $this->providerForSettings(['identifier' => 'acme'], rootPageId: 1);

        self::assertSame('default', $provider->getProfile(99)->getIdentifier());
    }

    /**
     * Without a root page the first site wins — that is the "any site will do"
     * path used when the caller has no page context.
     */
    #[Test]
    public function firstSiteIsUsedWhenNoRootPageIsGiven(): void
    {
        $provider = $this->providerForSettings(['identifier' => 'acme']);

        self::assertSame('acme', $provider->getProfile()->getIdentifier());
    }

    /**
     * Site resolution can throw — during early boot or with a broken site
     * configuration. The provider swallows that on purpose and falls back to
     * defaults instead of taking the whole analysis down with it.
     */
    #[Test]
    public function aFailingSiteFinderFallsBackToDefaults(): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willThrowException(new \RuntimeException('no site configuration'));

        $profile = new BrandVoiceConfigurationProvider($siteFinder)->getProfile(1);

        self::assertSame('default', $profile->getIdentifier());
    }
}
