<?php

namespace App\Contracts;

use App\Models\ApprovalInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Optional lifecycle hooks for financial documents whose status is not approval state. */
interface SeparateApprovalState
{
    public function isPending(Model $document): bool;

    public function canResubmit(Model $document, ApprovalInstance $previous): bool;

    /** Called only after source lock, scope and submit authorization, inside the transaction. */
    public function requesterId(Model $document, User $actor): int;

    public function excludedApproverIds(Model $document): array;
}
