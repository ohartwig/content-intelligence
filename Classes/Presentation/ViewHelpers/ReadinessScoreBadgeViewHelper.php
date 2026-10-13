<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Presentation\ViewHelpers;

use Koh\ContentIntelligence\Domain\Model\ScoreThreshold;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class ReadinessScoreBadgeViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('score', 'float', 'The readiness score (0-100)', true);
    }

    public function render(): string
    {
        assert($this->renderingContext instanceof RenderingContextInterface);

        return self::renderStatic(
            $this->arguments,
            $this->buildRenderChildrenClosure(),
            $this->renderingContext,
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext,
    ): string {
        $score = (float) $arguments['score'];
        $threshold = ScoreThreshold::fromScore($score);

        $cssClass = match ($threshold) {
            ScoreThreshold::Critical => 'bg-danger',
            ScoreThreshold::NeedsImprovement => 'bg-warning text-dark',
            ScoreThreshold::AiReady => 'bg-success',
        };

        $label = match ($threshold) {
            ScoreThreshold::Critical => 'Critical',
            ScoreThreshold::NeedsImprovement => 'Needs Improvement',
            ScoreThreshold::AiReady => 'AI-Ready',
        };

        return sprintf(
            '<span class="badge %s">%s (%.0f%%)</span>',
            htmlspecialchars($cssClass),
            htmlspecialchars($label),
            $score,
        );
    }
}
