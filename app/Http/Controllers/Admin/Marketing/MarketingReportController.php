<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingLead;
use App\Models\MarketingVisit;
use App\Models\User;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manager's visit report (client change request NR-16): client, location, visit
 * time, person met, follow-up and remarks per visit, filtered by quick date
 * range, staff member and outcome, exportable as CSV with the same rows.
 */
class MarketingReportController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        $period = ReportPeriod::fromRequest($request);

        $visits = MarketingVisit::with(['lead.assignee', 'lead.customer', 'user'])
            ->whereHas('lead', fn ($q) => $q->visibleTo($user))
            ->when($period->from, fn ($q) => $q->whereDate('visit_date', '>=', $period->from->toDateString()))
            ->when($period->to, fn ($q) => $q->whereDate('visit_date', '<=', $period->to->toDateString()))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->string('outcome')))
            ->orderByDesc('visit_date')->orderByDesc('visit_time')->orderByDesc('id')
            ->get();

        if ($request->query('export') === 'csv') {
            abort_unless($user->hasPermission('Marketing', 'export'), 403, 'You do not have permission to export the visit report.');

            $rows = $visits->map(fn (MarketingVisit $visit) => [
                $visit->visit_date->toDateString(), $visit->timeLabel() ?? '', $visit->lead->lead_code, $visit->lead->company_name,
                $visit->location ?? '', $visit->person_met ?? '', $visit->person_title ?? '', $visit->user?->name ?? '',
                $visit->outcomeLabel(), $visit->next_follow_up_date?->toDateString() ?? '', $visit->next_action ?? '', $visit->remarks ?? '',
            ])->all();

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Date', 'Time', 'Lead', 'Client', 'Location', 'Person Met', 'Title', 'Visited By', 'Outcome', 'Next Follow-up', 'Next Action', 'Remarks']);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, 'marketing-visits-'.$period->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('admin.marketing.report', [
            'visits' => $visits,
            'period' => $period,
            'presets' => ReportPeriod::PRESETS,
            'outcomes' => MarketingVisit::OUTCOMES,
            'staff' => User::where('status', 'active')
                ->whereHas('roles.permissions', fn ($q) => $q->where('module', 'Marketing')->where('action', 'view'))
                ->orderBy('name')->get(),
            'leadsVisited' => $visits->pluck('marketing_lead_id')->unique()->count(),
            'followUps' => $visits->whereNotNull('next_follow_up_date')->count(),
            'won' => $visits->where('outcome', 'won')->count(),
            'openLeads' => MarketingLead::visibleTo($user)->whereIn('status', MarketingLead::OPEN_STATUSES)->count(),
        ]);
    }
}
