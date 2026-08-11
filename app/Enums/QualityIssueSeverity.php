<?php

namespace App\Enums;

enum QualityIssueSeverity: string
{
    case Blocker = 'blocker';
    case Warning = 'warning';
}
