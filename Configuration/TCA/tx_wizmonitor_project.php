<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project',
        'label' => 'title',
        'label_alt' => 'base_url',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'title',
        'rootLevel' => 1,
        'adminOnly' => true,
        'searchFields' => 'title,base_url',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'typeicon_classes' => [
            'default' => 'actions-heart',
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'title, base_url, check_urls, expected_keyword, --div--;core.form.tabs:access, hidden',
        ],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.title',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'base_url' => [
            'label' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.base_url',
            'description' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.base_url.description',
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url'],
                'required' => true,
                'appearance' => [
                    'browserTitle' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.base_url',
                ],
            ],
        ],
        'check_urls' => [
            'label' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.check_urls',
            'description' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.check_urls.description',
            'config' => [
                'type' => 'text',
                'rows' => 5,
                'cols' => 60,
                'eval' => 'trim',
            ],
        ],
        'expected_keyword' => [
            'label' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.expected_keyword',
            'description' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.expected_keyword.description',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'hidden' => [
            'label' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
    ],
];
