<?php

namespace App\Services\Notifications;

use App\Models\CustomerInvoice;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CustomerOverdueNotification;
use App\Services\UserAccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class CustomerOverdueReminders
{
    public function due(): Builder
    {
        $days = max(1, (int) config('seera.overdue_reminders.minimum_days', 1));

        return CustomerInvoice::withoutGlobalScope('user_access')
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->where('balance_amount', '>', 0)
            ->where('due_date', '<=', today()->subDays($days)->endOfDay()->format('Y-m-d H:i:s'))
            ->whereHas('journalEntry', fn ($query) => $query->withoutGlobalScope('user_access')->where('status', 'posted'));
    }

    public function canReceive(User $actor): bool
    {
        if ($actor->status !== 'active' || ! $actor->hasPermission('Accounts Receivable', 'view')) {
            return false;
        }
        if ($actor->isSuperAdmin()) {
            return true;
        }
        $roles = Role::whereIn('code', config('seera.overdue_reminders.finance_roles', []))->where('status', 'active')->pluck('id');

        return $roles->contains(fn ($id) => $actor->hasEffectiveRole((int) $id));
    }

    public function accessible(User $actor, int $invoiceId): bool
    {
        if (! $this->canReceive($actor)) {
            return false;
        }
        $query = $this->due();
        (new UserAccessScopeService)->apply($query, $query->getModel(), $actor);

        return $query->whereKey($invoiceId)->exists();
    }

    /** Recheck each invoice under its settlement lock; a daily unique ID prevents duplicate notices. */
    public function publish(): int
    {
        if (! config('seera.overdue_reminders.enabled')) {
            return 0;
        }
        $sent = 0;
        $this->due()->select('customer_invoices.id')->chunkById(100, function ($invoices) use (&$sent) {
            foreach ($invoices as $row) {
                $sent += DB::transaction(function () use ($row) {
                    $invoice = $this->due()->whereKey($row->id)->lockForUpdate()->first();
                    if (! $invoice) {
                        return 0;
                    }
                    $delivered = 0;
                    User::where('status', 'active')->chunkById(100, function ($users) use ($invoice, &$delivered) {
                        foreach ($users as $actor) {
                            if (! $this->accessible($actor, (int) $invoice->id)) {
                                continue;
                            }
                            $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'seera:customer-overdue:'.$actor->getMorphClass().':'.$actor->id.':'.$invoice->id.':'.today()->toDateString())->toString();
                            $notice = $actor->notifications()->firstOrCreate(['id' => $id], [
                                'type' => CustomerOverdueNotification::class,
                                'data' => [
                                    'event' => 'overdue', 'module' => 'Accounts Receivable',
                                    'source_type' => 'customer_invoice', 'source_id' => (int) $invoice->id,
                                    'reference' => $invoice->invoice_number, 'reminder_date' => today()->toDateString(),
                                ],
                            ]);
                            $delivered += (int) $notice->wasRecentlyCreated;
                        }
                    });

                    return $delivered;
                }, 3);
            }
        });

        return $sent;
    }
}
