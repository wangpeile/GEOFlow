<?php

namespace App\Enums;

enum TaskPipelineMode: string
{
    case Legacy = 'legacy';
    case ContentProduction = 'content_production';
}
