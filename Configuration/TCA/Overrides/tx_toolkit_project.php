<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Toolkit: Monitoring wird direkt im Projekt eingerichtet; der Monitor-Datensatz folgt per DataHandler-Hook
$ll = 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:';
$GLOBALS['TCA']['tx_toolkit_project']['columns'] += [
    'monitor_enabled' => [
        'label' => $ll . 'tx_toolkit_project.monitor_enabled',
        'description' => $ll . 'tx_toolkit_project.monitor_enabled.description',
        'onChange' => 'reload',
        'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
    ],
    'monitor_check_pages' => [
        'label' => $ll . 'tx_wizmonitor_project.check_pages',
        'description' => $ll . 'tx_wizmonitor_project.check_pages.description',
        'displayCond' => 'FIELD:monitor_enabled:REQ:true',
        'config' => ['type' => 'input', 'size' => 40, 'max' => 255, 'eval' => 'trim', 'searchable' => false],
    ],
    'monitor_expected_keyword' => [
        'label' => $ll . 'tx_wizmonitor_project.expected_keyword',
        'description' => $ll . 'tx_wizmonitor_project.expected_keyword.description',
        'displayCond' => 'FIELD:monitor_enabled:REQ:true',
        'config' => ['type' => 'input', 'size' => 40, 'max' => 255, 'eval' => 'trim', 'searchable' => false],
    ],
];
$GLOBALS['TCA']['tx_toolkit_project']['palettes']['toolkit_monitor'] = [
    'label' => $ll . 'tx_toolkit_project.palette.monitor',
    'showitem' => 'monitor_enabled, --linebreak--, monitor_check_pages, monitor_expected_keyword',
];
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'tx_toolkit_project',
    '--palette--;;toolkit_monitor',
    '',
    'after:check_urls',
);
