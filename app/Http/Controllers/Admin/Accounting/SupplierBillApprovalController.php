<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\SupplierBill;
use App\Services\Accounting\SupplierBillPostingService;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\SupplierBillApprovalSubject;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierBillApprovalController extends Controller
{
    public function submit(Request $request, SupplierBill $accounts_payable, ApprovalRuntimeService $runtime, SupplierBillApprovalSubject $subject)
    {
        abort_unless($request->user()->hasPermission('Accounts Payable', 'view'), 403);
        $request->validate(['workflow_id' => ['nullable', 'integer'], 'previous_instance_id' => ['nullable', 'integer']]);
        $choices = ApprovalWorkflow::where('module', $subject->workflowModule())->where('trigger_action', $subject->trigger())->where('status', 'active')->get();
        if ($choices->isEmpty()) {
            throw ValidationException::withMessages(['approval' => 'No supported approval workflow is configured for Supplier Bills. Please contact an administrator.']);
        }
        $id = $request->integer('workflow_id') ?: ($choices->count() === 1 ? $choices->first()->id : 0);
        if (! $choices->contains('id', $id)) {
            throw ValidationException::withMessages(['workflow_id' => 'Choose a supported Supplier Bill workflow.']);
        }
        $runtime->start($subject, $accounts_payable->id, $id, $request->user(), $request->filled('previous_instance_id') ? $request->integer('previous_instance_id') : null);

        return redirect($subject->url($accounts_payable))->with('status', 'Bill submitted. All required reviewers must approve before accounting is attempted.');
    }

    public function approve(Request $request, SupplierBill $accounts_payable)
    {
        return $this->decision($request, $accounts_payable, 'approve');
    }

    public function reject(Request $request, SupplierBill $accounts_payable)
    {
        return $this->decision($request, $accounts_payable, 'reject');
    }

    private function decision(Request $request, SupplierBill $bill, string $action)
    {
        $data = $request->validate(['instance_id' => ['required', 'integer'], 'step_id' => ['required', 'integer'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $instance = $bill->approvals()->findOrFail($data['instance_id']);
        $step = $instance->steps()->findOrFail($data['step_id']);
        $subject = app(SupplierBillApprovalSubject::class);
        app(ApprovalRuntimeService::class)->decide($subject, $bill->id, $instance->id, $step->id, $request->user(), $action, $data['comment'] ?? null);

        return redirect($subject->url($bill))->with('status', 'Decision saved. Approval and accounting status are shown separately.');
    }

    public function retry(Request $request, SupplierBill $accounts_payable, SupplierBillPostingService $posting)
    {
        abort_unless($request->user()->hasPermission('Accounts Payable', 'view') && $request->user()->hasPermission('Accounts Payable', 'post') && $request->user()->hasPermission('Accounts Payable', 'retry'), 403);
        $posting->log($accounts_payable, $request->user()->id, 'Posting retried', 'Explicit Finance retry');
        $posting->attempt($accounts_payable->id, $request->user()->id);

        return redirect()->route('admin.accounting.accounts-payable.show', $accounts_payable)->with('status', 'Posting checked. Existing journals are not duplicated.');
    }
}
