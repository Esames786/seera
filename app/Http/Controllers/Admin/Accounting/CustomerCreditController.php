<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAdjustmentNote;
use App\Models\CustomerCreditAllocation;
use App\Models\CustomerCreditRefund;
use App\Models\CustomerInvoice;
use App\Services\Accounting\CustomerCreditResolutionService;
use App\Services\Accounting\PostingService;
use App\Support\SaveAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer credit resolution: the Customer Credits register and the explicit
 * actions on a posted credit note (apply credit to another invoice, refund,
 * and their reversals). Allocation needs Accounts Receivable — process;
 * refunds and every reversal need Accounts Receivable — approve (see the
 * permission map). All amounts and relations are validated in the service.
 */
class CustomerCreditController extends Controller
{
    public function __construct(private readonly CustomerCreditResolutionService $credits, private readonly PostingService $posting) {}

    public function index(Request $request): View
    {
        $notes = CustomerAdjustmentNote::query()->where('note_type', CustomerAdjustmentNote::TYPE_CREDIT)->where('status', CustomerAdjustmentNote::STATUS_POSTED)
            ->where('unapplied_amount', '>', 0)->with(['customer', 'invoice'])
            ->when($request->filled('customer'), fn ($q) => $q->where('customer_id', $request->integer('customer')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($qq) => $qq->where('note_number', 'like', '%'.$request->string('search').'%')
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$request->string('search').'%'))))
            ->orderByDesc('note_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $rows = $notes->getCollection()->map(fn (CustomerAdjustmentNote $note) => ['note' => $note] + $this->credits->summary($note));
        if ($request->filled('status')) {
            $rows = $rows->filter(fn ($row) => $row['status'] === $request->string('status')->toString())->values();
        }
        $totals = ['available' => round($rows->sum('available'), 2), 'applied' => round($rows->sum('applied'), 2), 'refunded' => round($rows->sum('refunded'), 2)];

        return view('admin.accounting.customer-notes.credits', [
            'notes' => $notes, 'rows' => $rows, 'totals' => $totals,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Apply credit to another open invoice of the same customer (Accounts Receivable — process). */
    public function allocate(Request $request, CustomerAdjustmentNote $note): RedirectResponse
    {
        $data = $request->validate([
            'customer_invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'allocation_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);
        $target = CustomerInvoice::findOrFail((int) $data['customer_invoice_id']); // scoped: a foreign invoice is a 404
        [$allocation, $replayed] = $this->credits->allocate($note, $target, $data, $request->user());

        return redirect()->to($this->back($request, $note))->with('status', $replayed
            ? __('customer_notes.flash_allocation_replayed', ['amount' => number_format((float) $allocation->amount, 2)])
            : __('customer_notes.flash_allocated', ['amount' => number_format((float) $allocation->amount, 2), 'invoice' => $target->invoice_number]));
    }

    public function reverseAllocation(Request $request, CustomerAdjustmentNote $note, CustomerCreditAllocation $allocation): RedirectResponse
    {
        abort_unless($allocation->customer_adjustment_note_id === $note->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => __('customer_notes.err_reason_required')]);
        $this->credits->reverseAllocation($allocation, $data['reason'], $request->user());

        return redirect()->to($this->back($request, $note))->with('status', __('customer_notes.flash_allocation_reversed'));
    }

    /** Refund available credit from a bank / cash account (Accounts Receivable — approve). */
    public function refund(Request $request, CustomerAdjustmentNote $note): RedirectResponse
    {
        $data = $request->validate([
            'payment_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->whereIn('account_code', [PostingService::CASH, PostingService::BANK])->where('status', 'active'))],
            'payment_method' => ['nullable', Rule::in(CustomerCreditRefund::METHODS)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ], ['payment_account_id.exists' => __('customer_notes.err_payment_account')]);
        [$refund, $replayed] = $this->credits->refund($note, $data, $request->user());

        return redirect()->to($this->back($request, $note))->with('status', $replayed
            ? __('customer_notes.flash_refund_replayed', ['amount' => number_format((float) $refund->amount, 2)])
            : __('customer_notes.flash_refunded', ['amount' => number_format((float) $refund->amount, 2), 'journal' => $refund->journalEntry ? $this->posting->describe($refund->journalEntry) : '-']));
    }

    public function reverseRefund(Request $request, CustomerAdjustmentNote $note, CustomerCreditRefund $refund): RedirectResponse
    {
        abort_unless($refund->customer_adjustment_note_id === $note->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => __('customer_notes.err_reason_required')]);
        $refund = $this->credits->reverseRefund($refund, $data['reason'], $request->user());

        return redirect()->to($this->back($request, $note))->with('status', __('customer_notes.flash_refund_reversed', ['journal' => $refund->reversalJournal?->journal_number ?? '-']));
    }

    private function back(Request $request, CustomerAdjustmentNote $note): string
    {
        return SaveAction::returnTo($request) ?? route('admin.accounting.accounts-receivable.notes.show', $note).'#resolution';
    }
}
