@php
    /** Line rows for the journal form. Keeps at least four editable rows on screen. */
    $rows = max(count($lineData), 4);
@endphp

<x-admin.form-section title="Journal Lines">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="min-width:220px">Account *</th>
                    <th style="min-width:180px">Description</th>
                    <th style="width:140px">Debit</th>
                    <th style="width:140px">Credit</th>
                    <th style="min-width:150px">Cost Center</th>
                    <th style="min-width:150px">Project</th>
                    <th style="min-width:150px">Site</th>
                </tr>
            </thead>
            <tbody>
                @for ($i = 0; $i < $rows; $i++)
                    @php $line = $lineData[$i] ?? []; @endphp
                    <tr>
                        <td>
                            <select name="lines[{{ $i }}][chart_of_account_id]" class="select">
                                <option value="">Select account...</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(($line['chart_of_account_id'] ?? null) == $account->id)>{{ $account->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input name="lines[{{ $i }}][description]" class="input" value="{{ $line['description'] ?? '' }}"/></td>
                        <td><input name="lines[{{ $i }}][debit]" type="number" step="0.01" min="0" class="input js-debit" value="{{ $line['debit'] ?? '' }}"/></td>
                        <td><input name="lines[{{ $i }}][credit]" type="number" step="0.01" min="0" class="input js-credit" value="{{ $line['credit'] ?? '' }}"/></td>
                        <td>
                            <select name="lines[{{ $i }}][cost_center_id]" class="select">
                                <option value="">-</option>
                                @foreach ($costCenters as $costCenter)
                                    <option value="{{ $costCenter->id }}" @selected(($line['cost_center_id'] ?? null) == $costCenter->id)>{{ $costCenter->code }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[{{ $i }}][project_id]" class="select">
                                <option value="">-</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected(($line['project_id'] ?? null) == $project->id)>{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[{{ $i }}][site_id]" class="select">
                                <option value="">-</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected(($line['site_id'] ?? null) == $site->id)>{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align:right">Totals</th>
                    <th><span data-total-debit>0.00</span></th>
                    <th><span data-total-credit>0.00</span></th>
                    <th colspan="3"><span class="badge gray" data-balance-badge>Balanced</span></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="small" style="margin-top:10px">
        Rows without an account or amount are ignored. Total debit must equal total credit before the entry can be posted.
    </div>
</x-admin.form-section>

@push('scripts')
<script>
    (function () {
        const form = document.querySelector('form[data-journal-form]');
        if (!form) return;

        const debitTotal = form.querySelector('[data-total-debit]');
        const creditTotal = form.querySelector('[data-total-credit]');
        const badge = form.querySelector('[data-balance-badge]');

        function sum(selector) {
            return Array.from(form.querySelectorAll(selector))
                .reduce((total, input) => total + (parseFloat(input.value) || 0), 0);
        }

        function refresh() {
            const debit = sum('.js-debit');
            const credit = sum('.js-credit');
            debitTotal.textContent = debit.toFixed(2);
            creditTotal.textContent = credit.toFixed(2);

            const balanced = Math.abs(debit - credit) < 0.01;
            badge.textContent = balanced ? 'Balanced' : 'Out of balance by ' + Math.abs(debit - credit).toFixed(2);
            badge.className = 'badge ' + (balanced ? 'green' : 'red');
        }

        form.addEventListener('input', function (event) {
            if (event.target.matches('.js-debit, .js-credit')) refresh();
        });

        refresh();
    })();
</script>
@endpush
