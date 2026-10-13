<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Application\Service;

use Koh\ContentIntelligence\Application\Event\BeforeAnalysisEvent;
use Koh\ContentIntelligence\Application\Event\ContentAnalyzedEvent;
use Koh\ContentIntelligence\Application\Event\ReadinessScoreCalculatedEvent;
use Koh\ContentIntelligence\Application\Service\AnalysisOrchestrator;
use Koh\ContentIntelligence\Application\Service\ScoreCalculationService;
use Koh\ContentIntelligence\Domain\Contract\AnalysisResultRepositoryInterface;
use Koh\ContentIntelligence\Domain\Contract\AnalyzerInterface;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Domain\Model\ScoreThreshold;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

final class AnalysisOrchestratorTest extends TestCase
{
    private AnalysisResultRepositoryInterface&MockObject $resultRepository;
    private ScoreCalculationService $scoreCalculationService;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private LoggerInterface&MockObject $logger;

    private ContentSnapshot $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resultRepository = $this->createMock(AnalysisResultRepositoryInterface::class);
        $this->scoreCalculationService = new ScoreCalculationService();
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->snapshot = new ContentSnapshot(
            pageUid: 42,
            languageUid: 0,
            title: 'Test Page',
            description: 'A test page',
            bodyTexts: ['Hello world'],
            headings: ['Main heading'],
            metaTags: ['title' => 'Test Page'],
            schemaOrgData: [],
            lastModified: new \DateTimeImmutable(),
        );

    }

    private function createOrchestrator(array $analyzers): AnalysisOrchestrator
    {
        return new AnalysisOrchestrator(
            $analyzers,
            $this->resultRepository,
            $this->scoreCalculationService,
            $this->eventDispatcher,
            $this->logger,
        );
    }

    private function createAnalyzerMock(string $identifier, float $weight, AnalysisResult $result): AnalyzerInterface&MockObject
    {
        $analyzer = $this->createMock(AnalyzerInterface::class);
        $analyzer->method('getIdentifier')->willReturn($identifier);
        $analyzer->method('getWeight')->willReturn($weight);
        $analyzer->method('analyze')->willReturn($result);

        return $analyzer;
    }

    #[Test]
    public function analyzeRunsAllAnalyzersAndReturnsReadinessScore(): void
    {
        $resultA = new AnalysisResult('metadata', 80.0, [], []);
        $resultB = new AnalysisResult('structure', 70.0, [], []);

        $analyzerA = $this->createAnalyzerMock('metadata', 0.6, $resultA);
        $analyzerB = $this->createAnalyzerMock('structure', 0.4, $resultB);

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(fn(object $event) => $event);

        $orchestrator = $this->createOrchestrator([$analyzerA, $analyzerB]);
        $result = $orchestrator->analyze($this->snapshot);

        // Real ScoreCalculationService: (80*0.6 + 70*0.4) / (0.6+0.4) = 76.0
        self::assertSame(42, $result->pageUid);
        self::assertSame(0, $result->languageUid);
        self::assertSame(76.0, $result->overallScore);
        self::assertSame(['metadata' => 80.0, 'structure' => 70.0], $result->analyzerScores);
    }

    #[Test]
    public function analyzePersistsResultsToRepository(): void
    {
        $analysisResult = new AnalysisResult('metadata', 80.0, [], []);
        $analyzer = $this->createAnalyzerMock('metadata', 1.0, $analysisResult);

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(fn(object $event) => $event);

        $this->resultRepository
            ->expects(self::once())
            ->method('saveForPage')
            ->with(42, 0, [$analysisResult]);

        $orchestrator = $this->createOrchestrator([$analyzer]);
        $orchestrator->analyze($this->snapshot);
    }

    #[Test]
    public function analyzeDispatchesAllExpectedEvents(): void
    {
        $analysisResult = new AnalysisResult('metadata', 80.0, [], []);
        $analyzer = $this->createAnalyzerMock('metadata', 1.0, $analysisResult);

        $dispatchedEvents = [];
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event::class;

                return $event;
            });

        $orchestrator = $this->createOrchestrator([$analyzer]);
        $orchestrator->analyze($this->snapshot);

        self::assertSame(
            [
                BeforeAnalysisEvent::class,
                ContentAnalyzedEvent::class,
                ReadinessScoreCalculatedEvent::class,
            ],
            $dispatchedEvents,
        );
    }

    #[Test]
    public function analyzeHandlesAnalyzerFailureGracefully(): void
    {
        $failingAnalyzer = $this->createMock(AnalyzerInterface::class);
        $failingAnalyzer->method('getIdentifier')->willReturn('failing');
        $failingAnalyzer->method('getWeight')->willReturn(1.0);
        $failingAnalyzer->method('analyze')
            ->willThrowException(new \RuntimeException('AI provider unavailable'));

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(fn(object $event) => $event);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Analyzer failed', self::callback(function (array $context): bool {
                return 'failing' === $context['analyzer']
                    && 42 === $context['pageUid']
                    && 'AI provider unavailable' === $context['error'];
            }));

        $orchestrator = $this->createOrchestrator([$failingAnalyzer]);
        $result = $orchestrator->analyze($this->snapshot);

        // Real ScoreCalculationService with score 0.0 and weight 1.0: overall = 0.0
        self::assertSame(42, $result->pageUid);
        self::assertSame(0.0, $result->overallScore);
        self::assertSame(['failing' => 0.0], $result->analyzerScores);
    }

    #[Test]
    public function analyzeReturnsEmptyScoreWhenBeforeAnalysisEventIsCancelled(): void
    {
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function (object $event) {
                if ($event instanceof BeforeAnalysisEvent) {
                    $event->cancel();
                }

                return $event;
            });

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Analysis cancelled via event', ['pageUid' => 42]);

        $this->resultRepository
            ->expects(self::never())
            ->method('saveForPage');

        $analyzer = $this->createMock(AnalyzerInterface::class);
        $analyzer->expects(self::never())->method('analyze');

        $orchestrator = $this->createOrchestrator([$analyzer]);
        $result = $orchestrator->analyze($this->snapshot);

        // Real ScoreCalculationService with empty results: overall = 0.0
        self::assertSame(42, $result->pageUid);
        self::assertSame(0, $result->languageUid);
        self::assertSame(0.0, $result->overallScore);
        self::assertSame([], $result->analyzerScores);
        self::assertSame(ScoreThreshold::Critical, $result->threshold);
    }
}
