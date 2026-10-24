<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Enum;

enum HealthCategory: string
{
    case Availability = 'availability';
    case Security = 'security';
    case Errors = 'errors';
    case System = 'system';
}
