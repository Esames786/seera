<?php

namespace App\Support\Workspace;

/**
 * A connected workspace declares its related panels once; the controller,
 * the navigation and the tests read the same list.
 */
interface WorkspacePanels
{
    /** @return array<string, PanelDefinition> keyed by panel key, in display order */
    public static function panels(): array;
}
