<?php

namespace App\Support;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;

/**
 * The company logo as a URL for customer-facing print views, or null when
 * none is configured or the file is missing. Looks in the public disk first,
 * then under public/ for legacy paths. Never throws.
 */
final class CompanyLogo
{
    public static function url(): ?string
    {
        $path = CompanyProfile::query()->value('logo_path');
        if (! $path) {
            return null;
        }
        $path = ltrim((string) $path, '/');
        try {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->url($path);
            }
        } catch (\Throwable) {
            // fall through to the legacy public path
        }
        if (is_file(public_path($path))) {
            return asset($path);
        }
        if (str_starts_with($path, 'storage/') && is_file(public_path($path))) {
            return asset($path);
        }

        return null;
    }
}
