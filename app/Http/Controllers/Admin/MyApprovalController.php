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
            'module' => ['nullable', 'in:Purchase Requests,Site Expenses'], 'status' => ['nullable', 'in:pending'],
            'project' => ['nullable', 'integer'], 'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $actor = $request->user();
        $tasks = collect();
        foreach (['purchase_request', 'site_expense'] as $type) {
            $subject = $runtime->subject($type);
            if (! $actor->hasPermission($subject->module(), 'view') || ($request->filled('module') && $request->input('module') !== $subject->module())) {
                continue;
            }
            $documents = $subject->queryFor($actor)->where('status', 'pending')
                ->when($request->filled('project'), fn ($q) => $q->where('project_id', $filters['project']));
            $query = ApprovalInstance::where('source_type', $subject->type())->where('status', 'pending')
                ->whereIn('source_id', $documents->select($documents->getModel()->getTable().'.id'))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('requested_at', '>=', $filters['from']))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('requested_at', '<=', $filters['to']))
                ->with('steps')->orderBy('id');
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
                        $url = $subject->url($document);
                        $reference = $instance->snapshot['reference'] ?? ($document->expense_number ?? $document->pr_number);
                        $tasks->push(compact('instance', 'step', 'document', 'approve', 'reject', 'url', 'reference'));
                    }
                }
            });
        }
        $tasks = $tasks->sortBy(fn ($task) => $task['instance']->id)->values();
        $page = max(1, $request->integer('page', 1));

        return view('admin.approvals.index', [
            'tasks' => new LengthAwarePaginator($tasks->forPage($page, 15)->values(), $tasks->count(), 15, $page,
                ['path' => $request->url(), 'query' => $request->query()]),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }
}
