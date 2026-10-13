<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') or exit;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['content_intelligence'] ??= [];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['content_intelligence']['frontend'] ??= VariableFrontend::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['content_intelligence']['backend'] ??= SimpleFileBackend::class;

$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['content_intelligence']
    = 'EXT:content_intelligence/Resources/Public/Css/';
