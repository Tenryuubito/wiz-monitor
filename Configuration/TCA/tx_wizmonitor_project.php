<?php

declare(strict_types=1);

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project',
        'label' => 'title',
        'label_alt' => 'base_url',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'title',
        'rootLevel' => 1,
        // Teil des Toolkits: auch für Nicht-Admins der Gruppe „Toolkit“ bearbeitbar
        'security' => ['ignoreRootLevelRestriction' => true],
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'typeicon_classes' => [
            'default' => 'actions-heart',
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'toolkit_project, title, base_url, check_urls, check_pages, expected_keyword, --div--;core.form.tabs:access, hidden',
        ],
    ],
    'columns' => [
        'toolkit_project' => [
            'label' => 'Toolkit-Projekt',
            'description' => 'Titel, Basis-URL, Prüfpfade, Prüfseiten, Schlüsselwort und An/Aus werden im Projekt gepflegt (Reiter „Website & Qualität“) und bei jeder Änderung übernommen.',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_toolkit_project',
                'foreign_table_where' => 'ORDER BY tx_toolkit_project.title',
                'items' => [['label' => '– ohne Toolkit-Projekt –', 'value' => 0]],
                'default' => 0,
            ],
        ],
        'title' => [
            'displayCond' => 'FIELD:toolkit_project:=:0',
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
            'displayCond' => 'FIELD:toolkit_project:=:0',
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
            'displayCond' => 'FIELD:toolkit_project:=:0',
            'label' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.check_urls',
            'description' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.check_urls.description',
            'config' => [
                'type' => 'text',
                'rows' => 5,
                'cols' => 60,
                'eval' => 'trim',
            ],
        ],
        'check_pages' => [
            'displayCond' => 'FIELD:toolkit_project:=:0',
            'label' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.check_pages',
            'description' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.check_pages.description',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'expected_keyword' => [
            'displayCond' => 'FIELD:toolkit_project:=:0',
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
            'displayCond' => 'FIELD:toolkit_project:=:0',
            'label' => 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_db.xlf:tx_wizmonitor_project.hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
    ],
];

// Volltextsuche im Backend nur über die bisherigen Suchfelder (ehemals ctrl.searchFields)
foreach (['check_urls', 'check_pages', 'expected_keyword'] as $field) {
    $tca['columns'][$field]['config']['searchable'] = false;
}

return $tca;
