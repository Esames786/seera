<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LookupValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "+ New" endpoint for extensible dropdown values (NR-02). The value itself is
 * the option, so the JSON answer uses it as both id and label.
 */
class LookupValueController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(LookupValue::TYPES))],
            'value' => ['required', 'string', 'max:100'],
        ]);

        // The route is open to any signed-in user; the right to add depends on what the list is for.
        $module = in_array($data['type'], ['nationality', 'document_type'], true) ? 'HR' : 'Suppliers';
        abort_unless($request->user()->hasPermission($module, 'create'), 403, 'You do not have permission to add values to this list.');

        $value = trim($data['value']);

        $existing = LookupValue::where('type', $data['type'])->where('value', $value)->first();

        if ($existing && $existing->status === 'active') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => 'That value already exists; pick it from the list.',
            ]);
        }

        $lookup = $existing
            ? tap($existing)->update(['status' => 'active'])
            : LookupValue::create(['type' => $data['type'], 'value' => $value, 'sort_order' => 50, 'status' => 'active']);

        ActivityLog::record($request, 'Master Setup', 'Added '.LookupValue::TYPES[$data['type']], $lookup->value);

        if ($request->wantsJson()) {
            return response()->json(['id' => $lookup->value, 'label' => $lookup->value], 201);
        }

        return back()->with('status', LookupValue::TYPES[$data['type']].' "'.$lookup->value.'" added.');
    }
}
