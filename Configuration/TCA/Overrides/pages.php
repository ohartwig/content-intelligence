<?php

declare(strict_types=1);

defined('TYPO3') or exit;

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', [
    'tx_contentintelligence_readiness_score' => [
        'label' => 'LLL:EXT:content_intelligence/Resources/Private/Language/locallang.xlf:pages.readiness_score',
        'config' => [
            'type' => 'number',
            'format' => 'decimal',
            'readOnly' => true,
        ],
    ],
    'tx_contentintelligence_last_analysis' => [
        'label' => 'LLL:EXT:content_intelligence/Resources/Private/Language/locallang.xlf:pages.last_analysis',
        'config' => [
            'type' => 'datetime',
            'readOnly' => true,
        ],
    ],
    'tx_contentintelligence_last_review' => [
        'label' => 'LLL:EXT:content_intelligence/Resources/Private/Language/locallang.xlf:pages.last_review',
        'config' => [
            'type' => 'datetime',
            'readOnly' => true,
        ],
    ],
]);

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--div--;LLL:EXT:content_intelligence/Resources/Private/Language/locallang.xlf:pages.tab,tx_contentintelligence_readiness_score,tx_contentintelligence_last_analysis,tx_contentintelligence_last_review',
);
