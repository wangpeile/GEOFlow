<?php

namespace App\Enums;

enum ContentProductionMode: string
{
    case Guided = 'guided';
    case Standard = 'standard';
    case Quick = 'quick';
    case Batch = 'batch';
    case EditorAssistant = 'editor_assistant';
}
