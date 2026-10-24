# Wiz Monitor

TYPO3 v14 backend module that monitors TYPO3 installations running [tenryuubito/wiz-monitor-client](https://github.com/Tenryuubito/wiz-monitor-client).

## Features

- **Availability:** HTTP status, response time, expected keyword and TLS certificate expiry of configurable URLs. A single failure is a warning, two consecutive failures are critical.
- **Security:** installed packages are matched against the Packagist security advisory database (TYPO3 core and extension advisories included), missing TYPO3 security and patch releases, TYPO3 and PHP end of life, Install Tool, `displayErrors`, `trustedHostsPattern` and failed logins.
- **Errors:** grouped errors of the monitored installation, including content element and plugin rendering errors.
- **System:** status endpoint reachability, scheduler tasks, disk space, writable directories and application context.
- **Notifications:** one e-mail per project and run when a category changes its state (new problem, changed severity, resolved) or new error types appear.

Every project has its own secret. It is shown once when generated and stored encrypted with a key derived from the TYPO3 `encryptionKey`. Changing the `encryptionKey` invalidates all stored secrets.

## Installation

```bash
composer require tenryuubito/wiz-monitor
vendor/bin/typo3 extension:setup
```

Configure the extension in `config/system/settings.php` or in the Install Tool:

```php
'EXTENSIONS' => [
    'wiz_monitor' => [
        'notificationRecipient' => 'monitoring@example.com',
        'notificationLanguage' => 'en',
        'requestTimeout' => '10',
        'slowResponseThreshold' => '3000',
        'retentionDays' => '30',
    ],
],
```

Run the checks every five minutes, either with a scheduler task of type "Execute console commands" for `wizmonitor:run` or with a cron job:

```bash
*/5 * * * * vendor/bin/typo3 wizmonitor:run
```

## Usage

1. Open **System > Wiz Monitor** and create a project with its base URL.
2. Open the project, click **Generate secret** and add the shown line to the `.env` file of the monitored installation.
3. Click **Check now** or wait for the next scheduled run.

## License

GPL-2.0-or-later
