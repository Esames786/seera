<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\OfflineSyncHandler;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\OfflineSyncReceipt;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\Supplier;
use App\Services\Hr\MobileAttendanceService;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use App\Services\Sync\AttendanceSyncHandler;
use App\Services\Sync\OfflineSyncService;
use App\Services\Sync\SiteExpenseSyncHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Offline Mobile Sync, Phase 1. One transport envelope, domain-specific
 * handlers, bounded batches, per-item results. The page lists the device
 * queue (client side) and the user's recent server receipts.
 */
class OfflineSyncController extends Controller
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(private readonly OfflineSyncService $sync, private readonly MobileAttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.mobile-sync.index', [
            'receipts' => OfflineSyncReceipt::where('user_id', $user->id)->latest('id')->limit(30)->get(),
            'canAttendance' => $user->hasPermission('Attendance', 'mobile'),
            'canExpenses' => $user->hasPermission('Site Expenses', 'create'),
        ]);
    }

    /** Lightweight session probe: a 401 here tells the queue to ask for sign-in; a fresh token lets it resume without a reload. */
    public function session(Request $request): JsonResponse
    {
        return response()->json(['user_id' => $request->user()->id, 'name' => $request->user()->name, 'csrf_token' => csrf_token(), 'server_time' => now()->toIso8601String()]);
    }

    /** Operational reference data for the offline forms; every id is revalidated at sync. */
    public function reference(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $user->hasPermission('Attendance', 'mobile') ? $this->attendance->context($user) : null;
        $site = $context['site'] ?? null;
        $expenses = null;
        if ($user->hasPermission('Site Expenses', 'create')) {
            $finance = $user->hasPermission('Site Expenses', 'post');
            $expenses = [
                'projects' => Project::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
                'sites' => Site::orderBy('name')->get(['id', 'name', 'project_id'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'project_id' => $s->project_id])->values(),
                'categories' => ExpenseCategory::where('status', 'active')->when($user->mobile_access && ! $finance, fn ($q) => $q->where('mobile_visible', true))->orderBy('name')->get()
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'vat_rate' => $c->vat_treatment === 'VAT 15%' ? 15 : 0, 'payment_type' => $c->payment_type, 'invoice_photo_required' => (bool) $c->invoice_photo_required])->values(),
                'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                'accounts' => SiteExpenseAccountingService::paymentAccounts('Cash')->get()->merge(SiteExpenseAccountingService::paymentAccounts('Bank')->get())
                    ->map(fn ($a) => ['id' => $a->id, 'name' => $a->account_name, 'channel' => $a->account_code === '1110' ? 'Cash' : 'Bank'])->values(),
                'payment_types' => SiteExpense::PAYMENT_TYPES,
                'can_override_vat' => $finance,
                'employee_linked' => Employee::withoutGlobalScopes()->where('user_id', $user->id)->exists(),
                'default_project_id' => $user->project_id,
                'default_site_id' => $user->site_id,
            ];
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'user' => ['id' => $user->id, 'name' => $user->name],
            'csrf_token' => csrf_token(),
            'policy' => ['batch_size' => (int) config('seera.offline_sync.batch_size'), 'batch_size_with_files' => (int) config('seera.offline_sync.batch_size_with_files'),
                'attendance_max_age_hours' => (int) config('seera.offline_sync.attendance_max_age_hours'), 'site_expense_max_age_days' => (int) config('seera.offline_sync.site_expense_max_age_days')],
            'attendance' => $context ? [
                'eligible' => $context['eligible'], 'reason' => $context['reason'], 'state' => $context['state'],
                'employee' => $context['employee'] ? ['id' => $context['employee']->id, 'code' => $context['employee']->employee_code, 'name' => $context['employee']->name] : null,
                'project' => $context['employee']?->project ? ['id' => $context['employee']->project->id, 'name' => $context['employee']->project->name] : null,
                'site' => $site ? ['id' => $site->id, 'name' => $site->name, 'latitude' => $site->latitude !== null ? (float) $site->latitude : null, 'longitude' => $site->longitude !== null ? (float) $site->longitude : null,
                    'geofence_radius' => (int) $site->geofence_radius, 'geofence_enabled' => (bool) $site->geofence_enabled, 'attendance_inside_only' => (bool) $site->attendance_inside_only] : null,
                'today' => $context['today'] ? ['date' => $context['today']->attendance_date->toDateString(), 'check_in' => $context['today']->check_in, 'check_out' => $context['today']->check_out, 'source' => $context['today']->source] : null,
            ] : null,
            'site_expense' => $expenses,
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->batch($request, app(AttendanceSyncHandler::class), (int) config('seera.offline_sync.batch_size', 20), withFiles: false);
    }

    public function siteExpenses(Request $request): JsonResponse
    {
        return $this->batch($request, app(SiteExpenseSyncHandler::class), (int) config('seera.offline_sync.batch_size_with_files', 5), withFiles: true);
    }

    private function batch(Request $request, OfflineSyncHandler $handler, int $max, bool $withFiles): JsonResponse
    {
        $data = $request->validate([
            'device_time' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:'.$max],
            'items.*.client_uuid' => ['required', 'string', 'regex:'.self::UUID],
            'items.*.action' => ['required', Rule::in($handler->actions())],
            'items.*.captured_at' => ['required', 'date'],
            'items.*.payload' => ['required', 'array'],
        ]);
        if (! empty($data['device_time'])) {
            $request->attributes->set('sync.clock_skew_seconds', Carbon::parse($data['device_time'])->diffInSeconds(now(), false));
        }
        $items = [];
        foreach ($data['items'] as $index => $item) {
            $items[] = [
                'client_uuid' => strtolower($item['client_uuid']),
                'action' => $item['action'],
                'payload' => $item['payload'],
                'captured_at' => Carbon::parse($item['captured_at'])->setTimezone(config('app.timezone')),
                'file' => $withFiles ? $request->file('items.'.$index.'.receipt') : null,
            ];
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'user_id' => $request->user()->id,
            'results' => $this->sync->process($request, $handler, $items),
        ]);
    }
}
