<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LinkedIdentityNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecordFileController extends Controller
{
    public function customer(Request $request, Customer $customer, int $document)
    {
        return $this->file($request, $customer->documents()->findOrFail($document));
    }

    public function supplier(Request $request, Supplier $supplier, int $document)
    {
        return $this->file($request, $supplier->documents()->findOrFail($document));
    }

    public function photo(Request $request, string $kind, int $id)
    {
        $actor = $request->user();
        if ($kind === 'employee') {
            abort_unless($actor->hasPermission('HR', 'view') || $actor->hasPermission('HR', 'edit'), 403);
            $owner = Employee::findOrFail($id); // Existing HR global project/site scope applies.
        } else {
            abort_unless($kind === 'user', 404);
            $owner = User::findOrFail($id);
            $navigation = app(LinkedIdentityNavigation::class);
            abort_unless($actor->id === $owner->id || $navigation->canUser($actor, $owner, 'view') || $navigation->canUser($actor, $owner, 'edit'), 403);
        }
        abort_unless($owner->profile_photo_path && Storage::disk('local')->exists($owner->profile_photo_path), 404);

        return Storage::disk('local')->response($owner->profile_photo_path, 'profile-photo', [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }

    private function file(Request $request, $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($document->original_filename));
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $document->mime_type, 'Content-Security-Policy' => "default-src 'none'; sandbox"];

        return $request->boolean('download')
            ? Storage::disk('local')->download($document->path, $name, $headers)
            : Storage::disk('local')->response($document->path, $name, $headers, 'inline');
    }
}
