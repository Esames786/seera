<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Services\Accounting\CustomerCreditResolutionService;
use App\Services\Zatca\ZatcaSubmissionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/** Diagnostic deployment-gate regressions. Run explicitly with --filter test_gate_review; no live database or ZATCA calls. */
class DeploymentGateReviewTest extends CustomerCreditResolutionTest
{
    private function fixture(string $method, array $arguments = []): mixed
    {
        return (new \ReflectionMethod(CustomerCreditResolutionTest::class, $method))->invokeArgs($this, $arguments);
    }

    public function test_gate_review_refund_must_not_consume_credit_with_a_draft_journal(): void
    {
        $note = $this->fixture('creditNote');
        $credits = app(CustomerCreditResolutionService::class);
        $before = $credits->availableCredit($note);
        AutomaticPostingRule::where('source_module', 'Customer Receipt')->update(['auto_post' => false]);
        try {
            $refund = $this->fixture('refund', [$note, 100.0]);
        } catch (ValidationException $exception) {
            $this->assertSame($before, $credits->availableCredit($note));
            return;
        }
        $this->assertSame('posted', $refund->journalEntry->status,
            'Refund consumed credit: before='.$before.' after='.$credits->availableCredit($note).' but its journal is '.$refund->journalEntry->status);
    }

    public function test_gate_review_inflight_submission_cannot_be_claimed_by_a_second_request(): void
    {
        $invoice = $this->fixture('invoice');
        $service = app(ZatcaSubmissionService::class);
        $service->sign($invoice, $this->admin());
        $calls = 0;
        $this->fake([self::SANDBOX.'/invoices/clearance/single' => function (Request $request) use (&$calls, $invoice, $service) {
            $calls++;
            if ($calls === 1) {
                try {
                    $service->submit($invoice->fresh(['zatcaRecord']), $this->admin());
                } catch (\App\Services\Zatca\ZatcaException $exception) {
                    $this->assertSame('submission_in_progress', $exception->errorCode);
                }
                return Http::response(['clearanceStatus' => 'NOT_CLEARED', 'validationResults' => ['errorMessages' => [['code' => 'DUPLICATE', 'message' => 'Duplicate request']]]], 400);
            }
            return Http::response(['clearanceStatus' => 'CLEARED', 'clearedInvoice' => base64_encode($this->clearedXml($request)), 'validationResults' => ['status' => 'PASS', 'errorMessages' => []]], 200);
        }]);
        $service->submit($invoice->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame(1, $calls, 'Concurrent pending record was submitted twice; final state='.$invoice->fresh(['zatcaRecord'])->zatcaRecord->status);
    }

    public function test_gate_review_http_200_without_clearance_evidence_is_not_accepted(): void
    {
        $invoice = $this->fixture('invoice');
        $this->fake([self::SANDBOX.'/invoices/clearance/single' => Http::response(['message' => 'unexpected proxy response'], 200)]);
        $record = app(ZatcaSubmissionService::class)->process($invoice, $this->admin());
        $this->assertFalse($record->isAccepted(), 'HTTP 200 without CLEARED status or cleared XML became '.$record->status);
    }
}
