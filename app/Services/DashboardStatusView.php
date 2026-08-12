<?php

namespace App\Services;

use App\Enums\EventRevisionStatus;
use App\Enums\EventStatus;

final class DashboardStatusView
{
    public function __construct(
        public readonly EventStatus $label,
        public readonly ?string $reviewerFeedback,
        public readonly bool $hasPendingEdit,
        public readonly ?EventRevisionStatus $pendingEditStatus = null,
    ) {}
}
