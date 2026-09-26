<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Concerns\ServesWorkspacePanels;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Site;
use App\Support\Workspace\CustomerWorkspacePanels as Panels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Related panels of the Customer workspace. The customer in the URL is the
 * authority: contacts and notes are read and written only through the
 * customer's own relations, so an id belonging to another customer is not
 * found. Invoices, receipts, journals and ZATCA records are never written
 * from here; those remain explicit actions on their own pages.
 */
class CustomerWorkspaceController extends Controller
{
    use ServesWorkspacePanels;

    public function panel(Request $request, Customer $customer, string $panel): JsonResponse
    {
        $this->authorizePanel($request, Panels::class, $panel, 'view');
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'record' => ['nullable', 'integer']]);

        $data = Panels::data($customer, $panel, $request->user(), false, 10, $request->integer('page') ?: null);

        // Editing a contact in place: it must belong to this customer.
        if ($panel === 'contacts' && $request->filled('record')) {
            abort_unless($request->user()->hasPermission('Customers', 'edit'), 403);
            $data['editing'] = $customer->contacts()->findOrFail($request->integer('record'));
        }

        return $this->panelHtml('admin.master.customers._workspace-panel', $data);
    }

    /** Contacts: add or edit one contact. Notes: add one note. */
    public function save(Request $request, Customer $customer, string $panel): JsonResponse
    {
        abort_unless(in_array($panel, ['contacts', 'notes'], true), 404);
        abort_if($request->filled('customer_id') && $request->integer('customer_id') !== $customer->id, 403);

        return DB::transaction(function () use ($request, $customer, $panel) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($panel === 'notes') {
                $this->authorizePanel($request, Panels::class, 'notes', 'create');
                $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
                $customer->notes()->create(['user_id' => $request->user()->id, 'note' => trim($data['note'])]);
                ActivityLog::record($request, 'Customers', 'Added customer note', $customer->name);
            } else {
                $record = $request->filled('record_id') ? $customer->contacts()->lockForUpdate()->find($request->integer('record_id')) : null;
                abort_if($request->filled('record_id') && ! $record, 404);
                $this->authorizePanel($request, Panels::class, 'contacts', $record ? 'edit' : 'create');

                $data = $request->validate([
                    'location_type' => ['required', Rule::in(CustomerContact::LOCATION_TYPES)],
                    'site_id' => ['nullable', 'integer'],
                    'name' => ['required', 'string', 'max:255'],
                    'title' => ['nullable', 'string', 'max:100'],
                    'phone' => ['nullable', 'string', 'max:30'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'address' => ['nullable', 'string', 'max:255'],
                ]);
                if ($data['location_type'] === 'office') {
                    $data['site_id'] = null;
                }
                // A site contact must sit on a site of one of this customer's (visible) projects.
                if (filled($data['site_id'] ?? null) && ! Site::whereKey($data['site_id'])->whereIn('project_id', $customer->projects()->select('projects.id'))->exists()) {
                    throw ValidationException::withMessages(['site_id' => 'Select a permitted site belonging to this customer.']);
                }

                if ($record) {
                    $record->update($data);
                    ActivityLog::record($request, 'Customers', 'Updated customer contact', $customer->name.': '.$record->name);
                } else {
                    $contact = $customer->contacts()->create($data);
                    ActivityLog::record($request, 'Customers', 'Added customer contact', $customer->name.': '.$contact->name);
                }
            }

            return response()->json([
                'message' => __('Saved successfully. This section is up to date.'),
                'panel_url' => route('admin.master.customers.workspace.panel', [$customer, $panel]),
            ]);
        });
    }

    /** Contacts and notes: remove one record that belongs to this customer. */
    public function action(Request $request, Customer $customer, string $panel, int $record, string $action): JsonResponse
    {
        abort_unless(in_array($panel, ['contacts', 'notes'], true) && $action === 'remove', 404);
        $this->authorizePanel($request, Panels::class, $panel, 'delete');

        DB::transaction(function () use ($request, $customer, $panel, $record) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $model = ($panel === 'contacts' ? $customer->contacts() : $customer->notes())->whereKey($record)->first();
            abort_unless($model, 404);

            $label = $panel === 'contacts' ? $customer->name.': '.$model->name : $customer->name;
            $model->delete();
            ActivityLog::record($request, 'Customers', $panel === 'contacts' ? 'Removed customer contact' : 'Removed customer note', $label);
        });

        return response()->json(['message' => __('Action completed.')]);
    }
}
