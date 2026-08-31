<?php

namespace App\Enums;

enum ArticleVersionKind: string
{
    case Assembled = 'assembled';
    case Manual = 'manual';
    case Repair = 'repair';
    case AiRevision = 'ai_revision';
}
