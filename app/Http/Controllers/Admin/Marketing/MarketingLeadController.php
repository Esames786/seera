<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\MarketingLead;
use App\Models\MarketingVisit;
use App\Models\User;
use App\Support\CodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Marketing leads and visits (client change request NR-16).
 *
 * A manager creates a lead and assigns it; the assigned staff member visits the
 * prospect and records who they met, the outcome and the next follow-up. Each
 * visit moves the lead's status along until it is won (converted to a customer)
 * or lost. Managers see every lead, staff only their own.
 */
class MarketingLeadController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $visible = MarketingLead::visibleTo($user);

        $leads = (clone $visible)->with(['assignee', 'customer'])->withCount('visits')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($w) => $w->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('lead_code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%"));
            })
            ->when($request->boolean('due'), fn ($q) => $q->whereIn('status', MarketingLead::OPEN_STATUSES)->whereDate('next_follow_up_date', '<=', today()))
            ->orderByRaw("CASE WHEN status IN ('won', 'lost') THEN 1 ELSE 0 END")
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.marketing.leads.index', [
            'leads' => $leads,
            'statuses' => MarketingLead::STATUSES,
            'staff' => $this->staff(),
            'isManager' => $this->isManager($user),
            'openCount' => (clone $visible)->whereIn('status', MarketingLead::OPEN_STATUSES)->count(),
            'dueCount' => (clone $visible)->whereIn('status', MarketingLead::OPEN_STATUSES)->whereDate('next_follow_up_date', '<=', today())->count(),
            'visitsThisMonth' => MarketingVisit::whereHas('lead', fn ($q) => $q->visibleTo($user))
                ->whereBetween('visit_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->count(),
            'wonCount' => (clone $visible)->where('status', 'won')->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.marketing.leads.create', $this->formOptions($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        if (! $this->isManager($user)) {
            // Staff may log their own prospects; those stay assigned to themselves.
            $data['assigned_to'] = $user->id;
        }

        $lead = MarketingLead::create($data + [
            'lead_code' => CodeGenerator::sequential('marketing_leads', 'lead_code', 'LD-'),
            'created_by' => $user->id,
            'status' => ! empty($data['assigned_to']) ? 'assigned' : 'new',
        ]);

        ActivityLog::record($request, 'Marketing', 'Created lead', $lead->lead_code.' '.$lead->company_name);

        return redirect()->route('admin.marketing.leads.show', $lead)
            ->with('status', 'Lead '.$lead->lead_code.' created'.($lead->assignee ? ' and assigned to '.$lead->assignee->name : '').'.');
    }

    public function show(Request $request, MarketingLead $lead): View
    {
        $this->ensureVisible($request, $lead);
        $lead->load(['assignee', 'creator', 'customer', 'visits.user']);

        return view('admin.marketing.leads.show', [
            'lead' => $lead,
            'outcomes' => MarketingVisit::OUTCOMES,
            'isManager' => $this->isManager($request->user()),
            'canRecordVisit' => $lead->isOpen() && $request->user()->hasPermission('Marketing', 'create'),
        ]);
    }

    public function edit(Request $request, MarketingLead $lead): View
    {
        $this->ensureVisible($request, $lead);

        return view('admin.marketing.leads.edit', ['lead' => $lead] + $this->formOptions($request));
    }

    public function update(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->ensureVisible($request, $lead);
        $data = $this->validated($request, $lead);
        $user = $request->user();

        if (! $this->isManager($user)) {
            // Only a manager reassigns or closes a lead from the form; staff move it through visits.
            unset($data['assigned_to'], $data['status']);
        } elseif (empty($data['status'])) {
            unset($data['status']);
        }

        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] && $lead->status === 'new') {
            $data['status'] = $data['status'] ?? 'assigned';
        }

        $lead->update($data);

        ActivityLog::record($request, 'Marketing', 'Updated lead', $lead->lead_code.' '.$lead->company_name);

        return redirect()->route('admin.marketing.leads.show', $lead)->with('status', 'Lead '.$lead->lead_code.' updated.');
    }

    public function destroy(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->ensureVisible($request, $lead);
        abort_unless($this->isManager($request->user()), 403, 'Only a marketing manager can delete a lead.');

        $lead->delete();

        ActivityLog::record($request, 'Marketing', 'Deleted lead', $lead->lead_code.' '.$lead->company_name);

        return redirect()->route('admin.marketing.leads.index')->with('status', 'Lead '.$lead->lead_code.' deleted.');
    }

    /**
     * Record a visit. The outcome drives the lead status: a won deal closes it,
     * "not interested" loses it, anything else keeps it open with the next follow-up.
     */
    public function storeVisit(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->ensureVisible($request, $lead);

        if (! $lead->isOpen()) {
            return back()->withErrors(['visit' => 'This lead is closed ('.$lead->statusLabel().'). Reopen it from the edit form before recording another visit.']);
        }

        $data = $request->validate([
            'visit_date' => ['required', 'date', 'before_or_equal:today'],
            'visit_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'person_met' => ['required', 'string', 'max:255'],
            'person_title' => ['nullable', 'string', 'max:255'],
            'outcome' => ['required', Rule::in(array_keys(MarketingVisit::OUTCOMES))],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'next_follow_up_date' => ['nullable', 'date', 'after_or_equal:visit_date'],
            'next_action' => ['nullable', 'string', 'max:255'],
        ], [
            'visit_date.before_or_equal' => 'A visit is recorded after it happened; the date cannot be in the future.',
            'person_met.required' => 'Record who you met, even if only a role such as "Site engineer".',
        ]);

        DB::transaction(function () use ($lead, $data, $request) {
            $lead->visits()->create($data + [
                'user_id' => $request->user()->id,
                'location' => $data['location'] ?? $lead->location,
            ]);

            $status = match ($data['outcome']) {
                'won' => 'won',
                'not_interested' => 'lost',
                default => ! empty($data['next_follow_up_date']) ? 'follow_up' : 'visited',
            };

            $lead->update([
                'status' => $status,
                'next_follow_up_date' => in_array($status, ['won', 'lost'], true) ? null : ($data['next_follow_up_date'] ?? null),
                'assigned_to' => $lead->assigned_to ?? $request->user()->id,
            ]);
        });

        ActivityLog::record($request, 'Marketing', 'Recorded visit', $lead->lead_code.' '.$lead->company_name.' — '.MarketingVisit::OUTCOMES[$data['outcome']]);

        return redirect()->route('admin.marketing.leads.show', $lead)
            ->with('status', 'Visit recorded. Lead is now "'.$lead->fresh()->statusLabel().'".');
    }

    /**
     * Turn a lead into a customer record so invoicing and projects can start.
     */
    public function convert(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->ensureVisible($request, $lead);

        if ($lead->customer_id) {
            return redirect()->route('admin.master.customers.show', $lead->customer_id)->with('status', 'This lead is already linked to a customer.');
        }

        $customer = DB::transaction(function () use ($lead) {
            $customer = Customer::create([
                'name' => $lead->company_name,
                'code' => CodeGenerator::sequential('customers', 'code', 'CUST-'),
                'type' => 'Company',
                'contact_person' => $lead->contact_name,
                'phone' => $lead->contact_phone,
                'email' => $lead->contact_email,
                'billing_address' => trim(implode(', ', array_filter([$lead->location, $lead->city]))) ?: null,
                'status' => 'active',
            ]);

            $lead->update(['customer_id' => $customer->id, 'status' => 'won', 'next_follow_up_date' => null]);

            return $customer;
        });

        ActivityLog::record($request, 'Marketing', 'Converted lead to customer', $lead->lead_code.' → '.$customer->code);

        return redirect()->route('admin.master.customers.show', $customer)
            ->with('status', 'Customer '.$customer->code.' created from lead '.$lead->lead_code.'. Add VAT/CR numbers and billing details here.');
    }

    /** @return array{0: array<string, mixed>} */
    private function validated(Request $request, ?MarketingLead $lead = null): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_title' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(MarketingLead::SOURCES)],
            'requirement' => ['nullable', 'string', 'max:255'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('status', 'active')],
            'status' => ['nullable', Rule::in(array_keys(MarketingLead::STATUSES))],
            'next_follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function formOptions(Request $request): array
    {
        return [
            'staff' => $this->staff(),
            'sources' => MarketingLead::SOURCES,
            'statuses' => MarketingLead::STATUSES,
            'isManager' => $this->isManager($request->user()),
        ];
    }

    /** Active users who may work marketing leads (any role with Marketing view). */
    private function staff()
    {
        return User::where('status', 'active')
            ->whereHas('roles.permissions', fn ($q) => $q->where('module', 'Marketing')->where('action', 'view'))
            ->orderBy('name')
            ->get();
    }

    private function isManager(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('Marketing', 'approve');
    }

    private function ensureVisible(Request $request, MarketingLead $lead): void
    {
        abort_unless(MarketingLead::visibleTo($request->user())->whereKey($lead->id)->exists(), 404);
    }
}
