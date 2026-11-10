<?php

namespace App\Console\Commands;

use App\Services\Notifications\CustomerOverdueReminders as Reminders;
use Illuminate\Console\Command;

class CustomerOverdueReminders extends Command
{
    protected $signature = 'seera:overdue-reminders {--send : Deliver the approved in-app reminders; otherwise report only}';

    protected $description = 'Daily overdue customer invoice alerts; dry-run by default, no email or background worker';

    public function handle(Reminders $reminders): int
    {
        if (! $this->option('send')) {
            $this->line('Dry run: '.$reminders->due()->count().' overdue posted invoices; no notifications sent.');

            return self::SUCCESS;
        }
        if (! config('seera.overdue_reminders.enabled')) {
            $this->error('Overdue reminders are disabled. Enable the approved rule before scheduling delivery.');

            return self::FAILURE;
        }
        $this->line('In-app reminders created: '.$reminders->publish());

        return self::SUCCESS;
    }
}
