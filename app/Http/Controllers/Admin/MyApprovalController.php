<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\Project;
use App\Services\Approvals\ApprovalRuntimeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class MyApprovalController extends Controller
{
    public function index(Request $request, ApprovalRuntimeService $runtime): View
    {
        $filters = $request->validate([
            'module' => ['nullable', 'in:Purchase Requests'], 'status' => ['nullable', 'in:pending'],
            'project' => ['nullable', 'integer'], 'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $actor = $request->user();
        $subject = $runtime->subject('purchase_request');
        $documents = $subject->queryFor($actor)->where('status', 'pending')
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $filters['project']));
        $query = ApprovalInstance::where('source_type', $subject->type())->where('status', 'pending')
            ->whereIn('source_id', $documents->select('purchase_requests.id'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('requested_at', '>=', $filters['from']))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('requested_at', '<=', $filters['to']))
            ->with('steps')->orderBy('id');
        $tasks = collect();
        // Eligibility is evaluated before pagination: no blank pages or leaked tasks.
        // Chunking bounds query memory; only this actor's actionable rows are retained.
        $query->chunkById(100, function ($instances) use ($actor, $runtime, $subject, $tasks) {
            foreach ($instances as $instance) {
                $step = $instance->currentStep();
                if (! $step || ! in_array($actor->id, $step->eligible_user_ids, true)) {
                    continue;
                }
                $document = $subject->queryFor($actor)->with('project', 'site')->find($instance->source_id);
                if (! $document) {
                    continue;
                }
                $approve = $runtime->eligible($subject, $document, $instance, $step, $actor, 'approve');
                $reject = $runtime->eligible($subject, $document, $instance, $step, $actor, 'reject');
                if ($approve || $reject) {
                    $tasks->push(compact('instance', 'step', 'document', 'approve', 'reject'));
                }
            }
        });
        $page = max(1, $request->integer('page', 1));

        return view('admin.approvals.index', [
            'tasks' => new LengthAwarePaginator($tasks->forPage($page, 15)->values(), $tasks->count(), 15, $page,
                ['path' => $request->url(), 'query' => $request->query()]),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }
}
