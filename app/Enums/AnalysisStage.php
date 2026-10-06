<?php

namespace App\Enums;

enum AnalysisStage: string
{
    case Queued = 'queued';
    case ValidatingUrl = 'validating_url';
    case FetchingContent = 'fetching_content';
    case ExtractingContent = 'extracting_content';
    case SearchingEvidence = 'searching_evidence';
    case EvaluatingEvidence = 'evaluating_evidence';
    case GeneratingReport = 'generating_report';
    case Complete = 'complete';
}
