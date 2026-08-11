<?php

namespace App\Enums;

enum QualityReportStatus: string
{
    case Passed = 'passed';
    case Warning = 'warning';
    case Blocked = 'blocked';
}
