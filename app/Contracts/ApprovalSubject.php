<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Explicit adapter registry: never instantiate a class supplied by a browser. */
interface ApprovalSubject
{
    public function type(): string;

    public function module(): string;

    public function workflowModule(): string;

    public function trigger(): string;

    public function queryFor(User $actor): Builder;

    /** Internal mutex only. Caller MUST authorize scoped access before returning or changing data. */
    public function lockForApproval(int $id): Model;

    public function canSubmit(Model $document, User $actor): bool;

    public function snapshot(Model $document): array;

    public function submitted(Model $document): void;

    public function completed(Model $document, User $actor): void;

    public function rejected(Model $document, string $reason): void;

    public function url(Model $document): string;
}
