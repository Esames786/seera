<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only evidence for an owner-approved keep/remove list. No deletion or automatic demo detection. */
class EntryAudit extends Command
{
    protected $signature = 'seera:entry-audit {--json : Print the private review inventory as JSON} {--limit=100 : Maximum displayed rows per master list}';

    protected $description = 'Read-only inventory of retained accounts, setup and entry records; never deletes or resets data';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 1000.');

            return self::INVALID;
        }
        $tables = array_unique(array_merge(GoLiveReset::PROTECTED, GoLiveReset::TRANSACTIONS, ...array_values(GoLiveReset::ENTRY_GROUPS)));
        $counts = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }
        $users = DB::table('users')->orderBy('id')->limit($limit)->get(['id', 'name', 'email', 'username', 'employee_id', 'status', 'created_at'])->map(function ($user) {
            $user->roles = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('user_roles.user_id', $user->id)->pluck('roles.code')->all();
            $user->linked_employee_ids = DB::table('employees')->where('user_id', $user->id)->pluck('id')->all();
            $user->linked_employee_codes = DB::table('employees')->where('user_id', $user->id)->pluck('employee_code')->all();
            $user->link_review_required = count($user->linked_employee_ids) > 1 || (count($user->linked_employee_codes) === 1 && $user->employee_id !== $user->linked_employee_codes[0]);
            $user->decision = 'OWNER REVIEW REQUIRED — do not infer demo status from a name or creation date';

            return $user;
        });
        $records = [];
        foreach (['customers', 'suppliers', 'projects', 'sites', 'warehouses', 'cost_centers'] as $table) {
            $columns = array_values(array_intersect(['id', 'code', 'name', 'status', 'type', 'linked_id', 'project_id', 'site_id', 'created_at'], Schema::getColumnListing($table)));
            $records[$table] = DB::table($table)->orderBy('id')->limit($limit)->get($columns)->all();
        }
        $danglingCenters = [];
        foreach (['project' => 'projects', 'site' => 'sites', 'warehouse' => 'warehouses', 'branch' => 'branches', 'department' => 'departments'] as $type => $table) {
            $danglingCenters = array_merge($danglingCenters, DB::table('cost_centers')->where('type', $type)->whereNotNull('linked_id')->whereNotIn('linked_id', DB::table($table)->select('id'))->get(['id', 'code', 'type', 'linked_id'])->all());
        }
        $duplicateCodes = DB::table('users')->whereNotNull('employee_id')->where('employee_id', '!=', '')->select('employee_id')->groupBy('employee_id')->havingRaw('COUNT(*) > 1')->pluck('employee_id');
        $duplicates = $duplicateCodes->map(fn ($code) => ['displayed_employee_code' => $code,
            'user_ids' => DB::table('users')->where('employee_id', $code)->orderBy('id')->pluck('id')->all(),
            'action' => 'Review actual employee.user_id links; never automatically relink or delete'])->all();
        $report = ['read_only' => true, 'database' => DB::connection()->getDatabaseName(), 'row_limit' => $limit,
            'warning' => 'Private inventory, not an approved delete list. Protect exact owner/admin IDs and take a verified backup before any separate cleanup operation.',
            'counts' => $counts, 'users' => $users->all(), 'duplicate_displayed_employee_codes' => $duplicates,
            'masters' => $records, 'cost_centers_with_missing_linked_record' => $danglingCenters];
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
