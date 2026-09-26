<?php

namespace App\Support\Workspace;

use App\Models\User;

/**
 * One related section of a connected workspace: what it is called, which
 * permission module guards it, and which action (if any) lets the user write
 * through it. Reading never implies writing.
 */
final class PanelDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $module,
        public readonly string $viewAction = 'view',
        public readonly ?string $writeAction = null,
    ) {}

    public function canView(User $user): bool
    {
        return $user->hasPermission($this->module, $this->viewAction);
    }

    public function canWrite(User $user): bool
    {
        return $this->writeAction !== null && $user->hasPermission($this->module, $this->writeAction);
    }
}
