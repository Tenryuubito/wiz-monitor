<?php

declare(strict_types=1);

use Tenryuubito\WizMonitor\Controller\MonitorModuleController;

return [
    'wiz_monitor' => [
        'parent' => 'system',
        'access' => 'admin',
        'path' => '/module/system/wiz-monitor',
        'workspaces' => 'live',
        'iconIdentifier' => 'module-reports',
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
        ],
    ],
];
