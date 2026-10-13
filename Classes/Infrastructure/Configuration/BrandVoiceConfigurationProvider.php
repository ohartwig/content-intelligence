<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Configuration;

use Koh\ContentIntelligence\Domain\Contract\BrandVoiceConfigurationProviderInterface;
use Koh\ContentIntelligence\Domain\Model\BrandVoiceProfile;
use TYPO3\CMS\Core\Site\SiteFinder;

final readonly class BrandVoiceConfigurationProvider implements BrandVoiceConfigurationProviderInterface
{
    public function __construct(
        private SiteFinder $siteFinder,
    ) {}

    public function getProfile(?int $rootPageUid = null): BrandVoiceProfile
    {
        $settings = $this->getSettings($rootPageUid);

        $profile = BrandVoiceProfile::create($settings['identifier'] ?? 'default')
            ->language($settings['language'] ?? 'de')
            ->addressForm($settings['addressForm'] ?? 'Sie');

        if (isset($settings['toneAttributes']) && is_array($settings['toneAttributes'])) {
            $profile->tone($settings['toneAttributes']);
        }

        if (isset($settings['forbiddenTerms']) && is_array($settings['forbiddenTerms'])) {
            $profile->forbiddenTerms($settings['forbiddenTerms']);
        }

        if (isset($settings['requiredTerms']) && is_array($settings['requiredTerms'])) {
            $profile->requiredTerms($settings['requiredTerms']);
        }

        if (isset($settings['maxSentenceLength'])) {
            $profile->maxSentenceLength((int) $settings['maxSentenceLength']);
        }

        if (isset($settings['noHashtags']) && (bool) $settings['noHashtags']) {
            $profile->noHashtags();
        }

        if (isset($settings['noTargetAudienceNaming']) && (bool) $settings['noTargetAudienceNaming']) {
            $profile->noTargetAudienceNaming();
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(?int $rootPageUid): array
    {
        try {
            $sites = $this->siteFinder->getAllSites();
            foreach ($sites as $site) {
                if (null !== $rootPageUid && $site->getRootPageId() !== $rootPageUid) {
                    continue;
                }

                $settings = $site->getSettings();
                $brandVoice = $settings->get('contentIntelligence.brandVoice');
                if (is_array($brandVoice)) {
                    return $brandVoice;
                }
                break;
            }
        } catch (\Throwable) {
            // Fallback to defaults
        }

        return [];
    }
}
