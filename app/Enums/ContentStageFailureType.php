<?php

namespace App\Enums;

enum ContentStageFailureType: string
{
    case Validation = 'validation';
    case DataUnavailable = 'data_unavailable';
    case ProviderUnavailable = 'provider_unavailable';
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case UnsafeContent = 'unsafe_content';
    case QualityGate = 'quality_gate';
    case Publication = 'publication';
    case Cancelled = 'cancelled';
    case Unexpected = 'unexpected';
}
