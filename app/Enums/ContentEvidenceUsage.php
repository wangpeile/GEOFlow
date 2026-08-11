<?php

namespace App\Enums;

enum ContentEvidenceUsage: string
{
    case MustCite = 'must_cite';
    case ReferenceOnly = 'reference_only';
    case Disabled = 'disabled';
}
