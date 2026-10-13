<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Application\Service;

use Koh\ContentIntelligence\Application\Event\BeforeAnalysisEvent;
use Koh\ContentIntelligence\Application\Event\ContentAnalyzedEvent;
use Koh\ContentIntelligence\Application\Event\ReadinessScoreCalculatedEvent;
use Koh\ContentIntelligence\Domain\Contract\AnalysisResultRepositoryInterface;
use Koh\ContentIntelligence\Domain\Contract\AnalyzerInterface;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Domain\Model\ReadinessScore;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

final readonly class AnalysisOrchestrator
{
    /** @var AnalyzerInterface[] */
    private array $analyzers;

    /**
     * @param iterable<AnalyzerInterface> $analyzers
     */
    public function __construct(
        iterable $analyzers,
        private AnalysisResultRepositoryInterface $resultRepository,
        private ScoreCalculationService $scoreCalculationService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
        $analyzerArray = [];
        foreach ($analyzers as $analyzer) {
            $analyzerArray[] = $analyzer;
        }
        $this->analyzers = $analyzerArray;
    }

    public function analyze(ContentSnapshot $snapshot): ReadinessScore
    {
        $event = $this->eventDispatcher->dispatch(new BeforeAnalysisEvent($snapshot));
        if ($event instanceof BeforeAnalysisEvent && $event->isCancelled()) {
            $this->logger->info('Analysis cancelled via event', ['pageUid' => $snapshot->pageUid]);

            return $this->scoreCalculationService->calculate(
                $snapshot->pageUid,
                $snapshot->languageUid,
                [],
                [],
            );
        }

        $results = [];
        $weights = [];

        foreach ($this->analyzers as $analyzer) {
            try {
                $result = $analyzer->analyze($snapshot);
                $results[] = $result;
                $weights[$analyzer->getIdentifier()] = $analyzer->getWeight();
            } catch (\Throwable $e) {
                $this->logger->error('Analyzer failed', [
                    'analyzer' => $analyzer->getIdentifier(),
                    'pageUid' => $snapshot->pageUid,
                    'error' => $e->getMessage(),
                ]);
                $results[] = new AnalysisResult(
                    analyzerIdentifier: $analyzer->getIdentifier(),
                    score: 0.0,
                    details: ['error' => $e->getMessage()],
                    suggestions: ['Analyzer execution failed. Please retry.'],
                );
                $weights[$analyzer->getIdentifier()] = $analyzer->getWeight();
            }
        }

        $this->resultRepository->saveForPage($snapshot->pageUid, $snapshot->languageUid, $results);

        $this->eventDispatcher->dispatch(new ContentAnalyzedEvent($snapshot, $results));

        $readinessScore = $this->scoreCalculationService->calculate(
            $snapshot->pageUid,
            $snapshot->languageUid,
            $results,
            $weights,
        );

        $this->eventDispatcher->dispatch(new ReadinessScoreCalculatedEvent($readinessScore));

        return $readinessScore;
    }
}
