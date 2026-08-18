<?php

namespace App\Enums;

enum ContentEvidenceSourceType: string
{
    case KnowledgeChunk = 'knowledge_chunk';
    case UrlImport = 'url_import';
    case Manual = 'manual';
    case SerpResearch = 'serp_research';
}
