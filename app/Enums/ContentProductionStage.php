<?php

namespace App\Enums;

enum ContentProductionStage: string
{
    case Initialize = 'initialize';
    case DuplicateCheck = 'duplicate_check';
    case Research = 'research';
    case Brief = 'brief';
    case Title = 'title';
    case Outline = 'outline';
    case SectionWriting = 'section_writing';
    case Assembly = 'assembly';
    case QualityGate = 'quality_gate';
    case TargetedRepair = 'targeted_repair';
    case Review = 'review';
    case WordPressPublish = 'wordpress_publish';
    case PlatformRewrite = 'platform_rewrite';
}
