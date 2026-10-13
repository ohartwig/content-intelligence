<?php

declare(strict_types=1);

use Koh\ContentIntelligence\Presentation\Controller\DashboardController;

return [
    'content_intelligence' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'iconIdentifier' => 'content-intelligence-module',
        'labels' => 'LLL:EXT:content_intelligence/Resources/Private/Language/locallang.xlf',
        'extensionName' => 'ContentIntelligence',
        'controllerActions' => [
            DashboardController::class => ['index', 'pageDetail', 'auditLog'],
        ],
    ],
];
