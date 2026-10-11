<?php

namespace App\Services\Payroll;

use App\Models\PayrollBankExport;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Support\Iban;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generic payroll bank transfer export (CSV). Amounts come from the stored,
 * approved payroll items; bank details from the employee record at the moment
 * the file is generated, then frozen in the export row so every download of
 * that file is identical. Incomplete bank-transfer employees block generation
 * and are listed by name. This is NOT a verified Saudi WPS / Mudad format.
 */
class PayrollBankExportService
{
    public const HEADERS = ['Payroll Period', 'Payroll Run', 'Employee Code', 'Employee Name', 'Payment Method', 'Bank Name', 'IBAN', 'Net Salary (SAR)', 'Reference'];

    /**
     * Finance readiness check for the whole run (not scoped to the viewer).
     *
     * @return array{employees: int, bank: int, cash: int, other: int, incomplete: int, net_total: float, bank_total: float, errors: array<int, array{code: string, name: string, problem: string}>, rows: array<int, array<string, mixed>>}
     */
    public function readiness(PayrollRun $run): array
    {
        $items = PayrollRunItem::withoutGlobalScopes()->where('payroll_run_id', $run->id)
            ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('id')->get();
        $summary = ['employees' => $items->count(), 'bank' => 0, 'cash' => 0, 'other' => 0, 'incomplete' => 0, 'net_total' => 0.0, 'bank_total' => 0.0, 'errors' => [], 'rows' => []];
        foreach ($items as $item) {
            $employee = $item->employee;
            $summary['net_total'] += (float) $item->net_amount;
            $code = $employee?->employee_code ?? '#'.$item->employee_id;
            $name = $employee?->name ?? 'Employee #'.$item->employee_id;
            if (! $employee) {
                $summary['other']++;
                $summary['incomplete']++;
                $summary['errors'][] = ['code' => $code, 'name' => $name, 'problem' => 'employee record missing'];

                continue;
            }
            $method = $employee->payment_method ?: 'Bank Transfer';
            if ($method === 'Cash') {
                $summary['cash']++;

                continue;
            }
            if ($method !== 'Bank Transfer') {
                $summary['other']++;

                continue;
            }
            $summary['bank']++;
            $problems = [];
            if (trim((string) $employee->bank_name) === '') {
                $problems[] = 'bank name missing';
            }
            if (Iban::normalize($employee->iban) === null) {
                $problems[] = 'IBAN missing';
            } elseif (! Iban::isValid($employee->iban)) {
                $problems[] = 'IBAN invalid';
            }
            if ($problems !== []) {
                $summary['incomplete']++;
                $summary['errors'][] = ['code' => $code, 'name' => $name, 'problem' => implode(', ', $problems)];

                continue;
            }
            $summary['bank_total'] += (float) $item->net_amount;
            $summary['rows'][] = [
                'period' => $run->periodLabel(),
                'run' => $run->code,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'payment_method' => 'Bank Transfer',
                'bank_name' => trim((string) $employee->bank_name),
                'iban' => Iban::normalize($employee->iban),
                'amount' => number_format((float) $item->net_amount, 2, '.', ''),
                'reference' => $run->code.'/'.$employee->employee_code,
            ];
        }
        $summary['net_total'] = round($summary['net_total'], 2);
        $summary['bank_total'] = round($summary['bank_total'], 2);

        return $summary;
    }

    /** Generate and freeze a new version of the transfer file for the run. */
    public function generate(PayrollRun $run, ?int $actorId): PayrollBankExport
    {
        return DB::transaction(function () use ($run, $actorId) {
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if (! $run->isApproved()) {
                $this->invalid('Only an approved payroll run can be exported; the stored rows must be final.');
            }
            if ($run->reversal_journal_id) {
                $this->invalid('The payroll accounting of this run was reversed; no transfer file can be generated.');
            }
            if ($run->isPaid()) {
                $this->invalid('This payroll run is already paid. Download the transfer file that was generated before payment; a new file would no longer match the settlement.');
            }
            $readiness = $this->readiness($run);
            if ($readiness['errors'] !== []) {
                $this->invalid('The transfer file was not generated: '.count($readiness['errors']).' employee(s) have incomplete bank details — '
                    .implode('; ', array_map(fn ($e) => $e['code'].' '.$e['name'].' ('.$e['problem'].')', array_slice($readiness['errors'], 0, 10)))
                    .(count($readiness['errors']) > 10 ? '; …' : '').'. Correct the employee records, then generate again.');
            }
            if ($readiness['rows'] === []) {
                $this->invalid('No bank-transfer employee in this run; nothing to export.');
            }
            $sequence = (int) PayrollBankExport::where('payroll_run_id', $run->id)->max('sequence') + 1;
            $fileName = 'payroll-bank-transfer-'.$run->code.'-v'.$sequence.'.csv';
            $export = new PayrollBankExport([
                'payroll_run_id' => $run->id,
                'sequence' => $sequence,
                'generated_by' => $actorId,
                'row_count' => count($readiness['rows']),
                'excluded_count' => $readiness['cash'] + $readiness['other'],
                'total_amount' => $readiness['bank_total'],
                'file_name' => $fileName,
                'rows' => $readiness['rows'],
                'file_hash' => '',
            ]);
            $export->file_hash = hash('sha256', $this->csv($export));
            $export->save();

            return $export;
        }, 3);
    }

    /** The file content, rebuilt from the frozen rows: identical on every download. */
    public function csv(PayrollBankExport $export): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::HEADERS);
        foreach ($export->rows as $row) {
            fputcsv($out, [$row['period'], $row['run'], $row['employee_code'], $row['employee_name'], $row['payment_method'], $row['bank_name'], $row['iban'], $row['amount'], $row['reference']]);
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['export' => $message]);
    }
}
