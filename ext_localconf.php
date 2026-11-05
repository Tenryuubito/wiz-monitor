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

// Toolkit: Monitor-Datensätze mit Toolkit-Projekten synchron halten
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['wiz_monitor_toolkit']
    = \Tenryuubito\WizMonitor\Toolkit\ToolkitProjectHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['wiz_monitor_toolkit']
    = \Tenryuubito\WizMonitor\Toolkit\ToolkitProjectHook::class;
