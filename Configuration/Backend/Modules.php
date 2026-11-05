<?php

declare(strict_types=1);

use Tenryuubito\WizMonitor\Controller\MonitorModuleController;

return [
    'wiz_monitor' => [
        // Teil des Toolkits (vorher: parent "system", access "admin")
        'parent' => 'toolkit',
        'access' => 'user',
        'path' => '/module/toolkit/monitor',
        'workspaces' => 'live',
        'iconIdentifier' => 'toolkit-monitor',
        'labels' => 'wiz_monitor.module',
        'routes' => [
            '_default' => [
                'target' => MonitorModuleController::class . '::overviewAction',
            ],
            'detail' => [
                'target' => MonitorModuleController::class . '::detailAction',
            ],
            'run' => [
                'target' => MonitorModuleController::class . '::runAction',
                'methods' => ['POST'],
            ],
            'generateSecret' => [
                'target' => MonitorModuleController::class . '::generateSecretAction',
                'methods' => ['POST'],
            ],
            'enable' => [
                'target' => MonitorModuleController::class . '::enableAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
