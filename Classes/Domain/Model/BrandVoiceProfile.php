<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

final class BrandVoiceProfile
{
    /** @var string[] */
    private array $toneAttributes = [];

    private string $addressForm = '';

    /** @var string[] */
    private array $forbiddenTerms = [];

    /** @var string[] */
    private array $requiredTerms = [];

    private ?int $maxSentenceLength = null;

    private bool $allowHashtags = true;

    private bool $allowTargetAudienceNaming = true;

    private string $language = 'de';

    private function __construct(
        private readonly string $identifier,
    ) {}

    public static function create(string $identifier): self
    {
        return new self($identifier);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string[] $attributes
     */
    public function tone(array $attributes): self
    {
        $this->toneAttributes = $attributes;

        return $this;
    }

    public function addressForm(string $form): self
    {
        $this->addressForm = $form;

        return $this;
    }

    /**
     * @param string[] $terms
     */
    public function forbiddenTerms(array $terms): self
    {
        $this->forbiddenTerms = $terms;

        return $this;
    }

    /**
     * @param string[] $terms
     */
    public function requiredTerms(array $terms): self
    {
        $this->requiredTerms = $terms;

        return $this;
    }

    public function maxSentenceLength(int $length): self
    {
        $this->maxSentenceLength = $length;

        return $this;
    }

    public function noHashtags(): self
    {
        $this->allowHashtags = false;

        return $this;
    }

    public function noTargetAudienceNaming(): self
    {
        $this->allowTargetAudienceNaming = false;

        return $this;
    }

    public function language(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return array{
     *     identifier: string,
     *     toneAttributes: string[],
     *     addressForm: string,
     *     forbiddenTerms: string[],
     *     requiredTerms: string[],
     *     maxSentenceLength: int|null,
     *     allowHashtags: bool,
     *     allowTargetAudienceNaming: bool,
     *     language: string
     * }
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'toneAttributes' => $this->toneAttributes,
            'addressForm' => $this->addressForm,
            'forbiddenTerms' => $this->forbiddenTerms,
            'requiredTerms' => $this->requiredTerms,
            'maxSentenceLength' => $this->maxSentenceLength,
            'allowHashtags' => $this->allowHashtags,
            'allowTargetAudienceNaming' => $this->allowTargetAudienceNaming,
            'language' => $this->language,
        ];
    }

    /** @return string[] */
    public function getToneAttributes(): array
    {
        return $this->toneAttributes;
    }

    public function getAddressForm(): string
    {
        return $this->addressForm;
    }

    /** @return string[] */
    public function getForbiddenTerms(): array
    {
        return $this->forbiddenTerms;
    }

    /** @return string[] */
    public function getRequiredTerms(): array
    {
        return $this->requiredTerms;
    }

    public function getMaxSentenceLength(): ?int
    {
        return $this->maxSentenceLength;
    }

    public function getAllowHashtags(): bool
    {
        return $this->allowHashtags;
    }

    public function getAllowTargetAudienceNaming(): bool
    {
        return $this->allowTargetAudienceNaming;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }
}
