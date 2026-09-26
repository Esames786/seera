<?php

namespace App\Support\Workspace;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\JournalEntry;
use App\Models\Site;
use App\Models\User;
use App\Models\ZatcaInvoiceRecord;
use App\Support\AgeingBuckets;
use Illuminate\Database\Eloquent\Builder;

/**
 * Customer connected workspace. Every related query starts from a model that
 * carries the user's access scope, so invoices, receipts, journals and ZATCA
 * records outside the user's scope are neither listed nor counted; the header,
 * balance and ageing figures come from the same scoped invoice query.
 */
class CustomerWorkspacePanels implements WorkspacePanels
{
    use PanelSet;

    public const OPEN_INVOICE_STATUSES = ['unpaid', 'partially_paid'];

    public const APPROVED_INVOICE_STATUSES = ['unpaid', 'partially_paid', 'paid'];

    public static function panels(): array
    {
        return [
            'contacts' => new PanelDefinition('contacts', 'Contacts', 'Customers', 'view', 'edit'),
            'notes' => new PanelDefinition('notes', 'Notes', 'Customers', 'view', 'create'),
            'projects' => new PanelDefinition('projects', 'Projects', 'Projects'),
            'invoices' => new PanelDefinition('invoices', 'Invoices', 'Accounts Receivable'),
            'receipts' => new PanelDefinition('receipts', 'Receipts & Balance', 'Accounts Receivable'),
            'ageing' => new PanelDefinition('ageing', 'Ageing', 'Accounts Receivable'),
            'accounting' => new PanelDefinition('accounting', 'Accounting', 'Journal Entries'),
            'zatca' => new PanelDefinition('zatca', 'Local ZATCA Records', 'ZATCA Invoicing'),
            'activity' => new PanelDefinition('activity', 'Activity', 'Activity Logs'),
        ];
    }

    public static function query(Customer $customer, string $panel, User $user): Builder
    {
        return match ($panel) {
            'contacts' => $customer->contacts()->with('site')->getQuery(),
            'notes' => $customer->notes()->with('user')->getQuery(),
            'projects' => $customer->projects()->with('manager')->orderBy('name')->getQuery(),
            'invoices', 'ageing' => self::invoices($customer)->with(['project', 'zatcaRecord'])->latest('invoice_date')->latest('id'),
            'receipts' => CustomerReceipt::query()->where('customer_id', $customer->id)
                ->with(['invoice', 'receiptAccount', 'journalEntry'])->latest('receipt_date')->latest('id'),
            'accounting' => JournalEntry::query()
                ->where(fn ($q) => $q
                    ->where(fn ($inv) => $inv->where('source_module', 'Customer Invoice')->whereIn('source_id', self::invoices($customer)->select('id')))
                    ->orWhere(fn ($rcpt) => $rcpt->where('source_module', 'Customer Receipt')
                        ->whereIn('source_id', CustomerReceipt::query()->where('customer_id', $customer->id)->select('id'))))
                ->latest('journal_date')->latest('id'),
            'zatca' => ZatcaInvoiceRecord::query()->whereIn('customer_invoice_id', self::invoices($customer)->select('id'))
                ->with('customerInvoice')->latest('id'),
            'activity' => self::activityQuery($customer, $user),
        };
    }

    /** Scoped invoices of this customer. */
    public static function invoices(Customer $customer): Builder
    {
        return CustomerInvoice::query()->where('customer_id', $customer->id);
    }

    /**
     * Header and balance figures from the invoices the user may see; null
     * without Accounts Receivable view so no money leaks through the header.
     *
     * @return array<string, mixed>|null
     */
    public static function summary(Customer $customer, User $user): ?array
    {
        if (! $user->hasPermission('Accounts Receivable', 'view')) {
            return null;
        }

        $invoices = self::invoices($customer);
        $open = (clone $invoices)->whereIn('payment_status', self::OPEN_INVOICE_STATUSES);

        return [
            'approved' => round((float) (clone $invoices)->whereIn('payment_status', self::APPROVED_INVOICE_STATUSES)->sum('total_amount'), 2),
            'received' => round((float) (clone $invoices)->whereIn('payment_status', self::APPROVED_INVOICE_STATUSES)->sum('received_amount'), 2),
            'outstanding' => round((float) (clone $open)->sum('balance_amount'), 2),
            'open_invoices' => (clone $open)->count(),
            'draft_invoices' => (clone $invoices)->where('payment_status', 'draft')->count(),
            'ageing' => AgeingBuckets::fromRows((clone $open)->get(['id', 'due_date', 'balance_amount'])),
            'last_receipt' => CustomerReceipt::query()->where('customer_id', $customer->id)->latest('receipt_date')->latest('id')->first(),
        ];
    }

    /** @return array<string, mixed> */
    public static function data(Customer $customer, string $panel, User $user, bool $readonly = false, int $perPage = 10, ?int $page = null): array
    {
        $definition = self::definition($panel);
        $query = self::query($customer, $panel, $user);
        if ($panel === 'ageing') {
            $query->whereIn('payment_status', self::OPEN_INVOICE_STATUSES)->orderBy('due_date');
        }
        $rows = $query->paginate($perPage, ['*'], $readonly ? 'page_'.$panel : 'page', $page);

        $data = [
            'customer' => $customer,
            'panel' => $panel,
            'title' => $definition->title,
            'module' => $definition->module,
            'rows' => $rows,
            'readonly' => $readonly,
            'hasNext' => ! $readonly && self::hasNext($user, $panel),
            'viewAll' => self::viewAllUrl($customer, $panel),
        ];

        return $data + match ($panel) {
            'contacts' => [
                'canAdd' => ! $readonly && $user->hasPermission('Customers', 'create'),
                'canEdit' => ! $readonly && $user->hasPermission('Customers', 'edit'),
                'canRemove' => ! $readonly && $user->hasPermission('Customers', 'delete'),
                'sites' => self::sitesFor($customer),
            ],
            'notes' => [
                'canAdd' => ! $readonly && $user->hasPermission('Customers', 'create'),
                'canRemove' => ! $readonly && $user->hasPermission('Customers', 'delete'),
            ],
            'projects' => ['canViewProjects' => $user->hasPermission('Projects', 'view')],
            'invoices' => [
                'canEditInvoice' => $user->hasPermission('Accounts Receivable', 'edit'),
                'canReceive' => $user->hasPermission('Accounts Receivable', 'process'),
                'canViewZatca' => $user->hasPermission('ZATCA Invoicing', 'view'),
            ],
            'receipts', 'ageing' => ['summary' => self::summary($customer, $user)],
            'accounting' => ['linkedAccount' => $customer->linked_account],
            default => [],
        };
    }

    /** Sites of this customer's (scoped) projects, for site contacts. */
    public static function sitesFor(Customer $customer)
    {
        return Site::whereIn('project_id', $customer->projects()->select('projects.id'))->orderBy('name')->get(['id', 'name', 'project_id']);
    }

    public static function viewAllUrl(Customer $customer, string $panel): ?string
    {
        return match ($panel) {
            'invoices', 'receipts', 'ageing' => route('admin.accounting.accounts-receivable.index', ['customer' => $customer->id]),
            'accounting' => route('admin.accounting.journal-entries.index', ['search' => $customer->name]),
            'zatca' => route('admin.accounting.zatca.index'),
            'activity' => route('admin.activity-logs.index', ['search' => $customer->name]),
            default => null,
        };
    }

    private static function activityQuery(Customer $customer, User $user): Builder
    {
        $references = collect([$customer->name])
            ->merge(self::invoices($customer)->latest('id')->limit(20)->pluck('invoice_number'))
            ->filter()->unique()->values();

        return ActivityLog::query()->visibleTo($user)
            ->whereIn('module', ['Customers', 'Accounting'])
            ->where(function ($q) use ($references) {
                foreach ($references as $reference) {
                    $q->orWhere('description', 'like', '%'.addcslashes($reference, '%_').'%');
                }
            })
            ->latest('created_at')->latest('id');
    }
}
