<?php

declare(strict_types=1);

use Tenryuubito\WizMonitor\External\ExternalDataClient;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][ExternalDataClient::CACHE_IDENTIFIER] ??= [
    'frontend' => VariableFrontend::class,
    'backend' => Typo3DatabaseBackend::class,
    'groups' => ['system'],
];

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][1761300000] = 'EXT:wiz_monitor/Resources/Private/Templates/Email/';
