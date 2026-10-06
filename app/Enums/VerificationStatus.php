<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Verified = 'verified';
    case StronglySupported = 'strongly_supported';
    case PartiallySupported = 'partially_supported';
    case Unverified = 'unverified';
    case InsufficientEvidence = 'insufficient_evidence';
    case Contradicted = 'contradicted';
    case ConflictingOfficialAccounts = 'conflicting_official_accounts';
}
