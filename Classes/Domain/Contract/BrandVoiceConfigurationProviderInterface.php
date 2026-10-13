<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Contract;

use Koh\ContentIntelligence\Domain\Model\BrandVoiceProfile;

interface BrandVoiceConfigurationProviderInterface
{
    public function getProfile(?int $rootPageUid = null): BrandVoiceProfile;
}
