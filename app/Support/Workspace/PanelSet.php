<?php

namespace App\Support\Workspace;

use App\Models\User;

/**
 * Shared helpers for any WorkspacePanels implementation: which panels a user
 * may see, and which panel follows another for "Save & next".
 */
trait PanelSet
{
    public static function definition(string $key): ?PanelDefinition
    {
        return static::panels()[$key] ?? null;
    }

    /** @return array<int, string> panel keys the user may view, in display order */
    public static function visible(User $user): array
    {
        return array_values(array_keys(array_filter(static::panels(), fn (PanelDefinition $panel) => $panel->canView($user))));
    }

    /** @return array<string, PanelDefinition> */
    public static function visibleDefinitions(User $user): array
    {
        return array_filter(static::panels(), fn (PanelDefinition $panel) => $panel->canView($user));
    }

    public static function hasNext(User $user, string $key): bool
    {
        $visible = static::visible($user);
        $index = array_search($key, $visible, true);

        return $index !== false && $index < count($visible) - 1;
    }
}
