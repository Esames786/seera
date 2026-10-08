@php
    /** @var \App\Models\Customer $customer */
    $readonly = $readonly ?? false;
    $panelUrl = route('admin.master.customers.workspace.panel', [$customer, $panel]);
    // Invoice pages opened from here return to this customer context (View page or Edit / Manage workspace).
    $invoiceOrigin = ($readonly ? route('admin.master.customers.show', $customer, false) : route('admin.master.customers.edit', $customer, false)).'#'.$panel;
    $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
    $prefix = 'customer-'.$customer->id.'-'.$panel.'-';
    $editing = $editing ?? null;   // contact being edited (loaded with ?record=)
@endphp
<div class="card" style="padding:18px" data-panel-content id="{{ $panel }}">
    <h2>{{ __($title) }} — {{ $customer->code }} / {{ $customer->name }}</h2>
    <div role="status" aria-live="polite" data-panel-status></div>

    @switch($panel)
        @case('contacts')
            @if ($canAdd || ($canEdit && $editing))
                <form method="POST" action="{{ route('admin.master.customers.workspace.save', [$customer, 'contacts']) }}" data-related-save>
                    @csrf
                    <input type="hidden" name="customer_id" value="{{ $customer->id }}"/>
                    @if ($editing)<input type="hidden" name="record_id" value="{{ $editing->id }}"/>@endif
                    <div class="alert" data-related-errors role="alert" hidden></div>
                    <h3>{{ $editing ? __('Edit contact') : __('Add contact') }}</h3>
                    <div class="form-grid three">
                        <div>
                            <label for="{{ $prefix }}location">{{ __('Where') }} *</label>
                            <select id="{{ $prefix }}location" name="location_type" class="select" required>
                                <option value="office" @selected(($editing?->location_type ?? 'office') === 'office')>{{ __('Office') }}</option>
                                <option value="site" @selected($editing?->location_type === 'site')>{{ __('Site') }}</option>
                            </select>
                            <div class="field-error" data-field-error="location_type"></div>
                        </div>
                        <div>
                            <label for="{{ $prefix }}site">{{ __('ui.site') }}</label>
                            <select id="{{ $prefix }}site" name="site_id" class="select">
                                <option value="">{{ __('ui.no_site') }}</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected($editing?->site_id == $site->id)>{{ $site->name }}</option>
                                @endforeach
                            </select>
                            <div class="field-error" data-field-error="site_id"></div>
                        </div>
                        @foreach (['name' => 'Name', 'title' => 'Title', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address'] as $field => $label)
                            <div>
                                <label for="{{ $prefix.$field }}">{{ __($label) }}{{ $field === 'name' ? ' *' : '' }}</label>
                                <input id="{{ $prefix.$field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" class="input" value="{{ $editing?->{$field} }}" @required($field === 'name')/>
                                <div class="field-error" data-field-error="{{ $field }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="_save_action" value="stay" class="btn primary" data-save-default>{{ $editing ? __('Save contact') : __('Add contact') }}</button>
                        @if ($editing)<button type="button" class="btn outline" data-panel-load="{{ $panelUrl }}">{{ __('Cancel editing') }}</button>@endif
                    </div>
                </form>
            @endif
            <div class="table-wrap"><table>
                <thead><tr><th>{{ __('Where') }}</th><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Email') }}</th><th>{{ __('Address') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $contact)
                        <tr>
                            <td><span class="badge {{ $contact->location_type === 'office' ? 'blue' : 'purple' }}">{{ ucfirst($contact->location_type) }}</span>@if($contact->site)<div class="small">{{ $contact->site->name }}</div>@endif</td>
                            <td>{{ $contact->name }}@if($contact->title)<div class="small">{{ $contact->title }}</div>@endif</td>
                            <td><bdi>{{ $contact->phone ?? '-' }}</bdi></td>
                            <td><bdi>{{ $contact->email ?? '-' }}</bdi></td>
                            <td>{{ $contact->address ?? '-' }}</td>
                            <td>
                                @if ($canEdit)<button type="button" class="btn sm outline" data-panel-load="{{ $panelUrl.'?record='.$contact->id }}">{{ __('Edit here') }}</button>@endif
                                @if ($canRemove)<button type="button" class="btn sm outline" data-related-action="{{ route('admin.master.customers.workspace.action', [$customer, 'contacts', $contact->id, 'remove']) }}" data-action="remove">{{ __('ui.remove') }}</button>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">{{ __('ui.empty_contacts') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('notes')
            @if ($canAdd)
                <form method="POST" action="{{ route('admin.master.customers.workspace.save', [$customer, 'notes']) }}" data-related-save>
                    @csrf
                    <input type="hidden" name="customer_id" value="{{ $customer->id }}"/>
                    <div class="alert" data-related-errors role="alert" hidden></div>
                    <label for="{{ $prefix }}note">{{ __('ui.new_note') }}</label>
                    <textarea id="{{ $prefix }}note" name="note" class="textarea" maxlength="2000" required></textarea>
                    <div class="field-error" data-field-error="note"></div>
                    <div class="form-actions"><button type="submit" name="_save_action" value="stay" class="btn primary" data-save-default>{{ __('Add note') }}</button></div>
                </form>
            @endif
            @forelse ($rows as $note)
                <div class="note" style="margin-bottom:10px">
                    <div style="white-space:pre-line">{{ $note->note }}</div>
                    <div class="small">{{ $note->user?->name ?? __('Unknown') }} · {{ $note->created_at->format('d M Y H:i') }}</div>
                    @if ($canRemove)<button type="button" class="btn sm outline" data-related-action="{{ route('admin.master.customers.workspace.action', [$customer, 'notes', $note->id, 'remove']) }}" data-action="remove">{{ __('ui.remove') }}</button>@endif
                </div>
            @empty
                <p class="small">{{ __('ui.empty_notes') }}</p>
            @endforelse
            @break

        @case('projects')
            <div class="table-wrap"><table>
                <thead><tr><th>Code</th><th>Project</th><th>Manager</th><th>Budget</th><th>Status</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $project)
                        <tr>
                            <td>{{ $project->code }}</td>
                            <td>{{ $project->name }}</td>
                            <td>{{ $project->manager?->name ?? '-' }}</td>
                            <td>{{ $money($project->budget) }}</td>
                            <td><x-admin.status-badge :status="$project->status"/></td>
                            <td>@if ($canViewProjects)<a class="btn sm outline" href="{{ route('admin.master.projects.show', $project) }}">View project</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">{{ __('No projects for this customer in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('invoices')
            <div class="table-wrap"><table>
                <thead><tr><th>Invoice</th><th>Date</th><th>Project</th><th>Taxable</th><th>VAT</th><th>Total</th><th>Received</th><th>Balance</th><th>Status</th><th>Local ZATCA</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->invoice_date?->toDateString() }}</td>
                            <td>{{ $invoice->project?->name ?? '-' }}</td>
                            <td>{{ $money($invoice->taxable_amount) }}</td>
                            <td>{{ $money($invoice->vat_amount) }}</td>
                            <td>{{ $money($invoice->total_amount) }}</td>
                            <td>{{ $money($invoice->received_amount) }}</td>
                            <td>{{ $money($invoice->balance_amount) }}</td>
                            <td><x-admin.status-badge :status="$invoice->payment_status"/></td>
                            <td>@if ($invoice->zatcaRecord)<span class="small">{{ $invoice->zatcaRecord->clearance_status }} (local record)</span>@else <span class="small">-</span>@endif</td>
                            <td>
                                <a class="btn sm outline" href="{{ route('admin.accounting.accounts-receivable.show', ['accounts_receivable' => $invoice, 'return_to' => $invoiceOrigin]) }}">View</a>
                                @if ($canEditInvoice && $invoice->isEditable())<a class="btn sm outline" href="{{ route('admin.accounting.accounts-receivable.edit', ['accounts_receivable' => $invoice, 'return_to' => $invoiceOrigin]) }}">Edit draft</a>@endif
                                @if ($canReceive && in_array($invoice->payment_status, \App\Support\Workspace\CustomerWorkspacePanels::OPEN_INVOICE_STATUSES, true))<a class="btn sm outline" href="{{ route('admin.accounting.accounts-receivable.receipt', ['accounts_receivable' => $invoice, 'return_to' => $invoiceOrigin]) }}">Record receipt</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="table-empty">{{ __('No invoices for this customer in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <p class="small">{{ __('Approve, reopen and receipts are done on the invoice itself; the customer profile never posts accounting.') }}</p>
            @break

        @case('receipts')
            @if ($summary)
                <div class="card-grid">
                    <x-admin.metric-card color="blue" :value="$money($summary['approved'])" label="Approved invoices"/>
                    <x-admin.metric-card color="green" :value="$money($summary['received'])" label="Received"/>
                    <x-admin.metric-card color="yellow" :value="$money($summary['outstanding'])" label="Outstanding"/>
                    <x-admin.metric-card color="cyan" :value="$summary['last_receipt'] ? $summary['last_receipt']->receipt_date->toDateString() : '-'" label="Last receipt"/>
                </div>
            @endif
            <div class="table-wrap"><table>
                <thead><tr><th>Date</th><th>Invoice</th><th>Account</th><th>Method</th><th>Amount</th><th>Reference</th><th>Journal</th></tr></thead>
                <tbody>
                    @forelse ($rows as $receipt)
                        <tr>
                            <td>{{ $receipt->receipt_date?->toDateString() }}</td>
                            <td>@if($receipt->invoice)<a href="{{ route('admin.accounting.accounts-receivable.show', ['accounts_receivable' => $receipt->invoice, 'return_to' => $invoiceOrigin]) }}">{{ $receipt->invoice->invoice_number }}</a>@else - @endif</td>
                            <td>{{ $receipt->receiptAccount?->label() ?? '-' }}</td>
                            <td>{{ $receipt->payment_method ?? '-' }}</td>
                            <td>{{ $money($receipt->amount) }}</td>
                            <td>{{ $receipt->reference_number ?? '-' }}</td>
                            <td>@if($receipt->journalEntry && auth()->user()->hasPermission('Journal Entries', 'view'))<a href="{{ route('admin.accounting.journal-entries.show', $receipt->journalEntry) }}">{{ $receipt->journalEntry->journal_number }}</a>@else - @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">{{ __('No receipts recorded for this customer in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <p class="small">{{ __('Figures come only from the invoices you are allowed to see. Receipts are recorded on the invoice page.') }}</p>
            @break

        @case('ageing')
            @if ($summary)
                <p class="small">{{ __('Current-state ageing as of today, by days past the due date of each open invoice you can see. This is not an as-of-date historical report.') }}</p>
                <div class="card-grid">
                    @foreach ($summary['ageing'] as $bucket => $amount)
                        <x-admin.metric-card :color="$bucket === 'Current' ? 'green' : ($bucket === '60+ days' ? 'red' : 'yellow')" :value="$money($amount)" :label="$bucket"/>
                    @endforeach
                </div>
            @endif
            <div class="table-wrap"><table>
                <thead><tr><th>Invoice</th><th>Due date</th><th>Days late</th><th>Balance</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($rows as $invoice)
                        @php $late = $invoice->due_date ? max($invoice->due_date->diffInDays(today(), false), 0) : 0; @endphp
                        <tr>
                            <td><a href="{{ route('admin.accounting.accounts-receivable.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                            <td>{{ $invoice->due_date?->toDateString() ?? '-' }}</td>
                            <td>{{ $late > 0 ? $late : '-' }}</td>
                            <td>{{ $money($invoice->balance_amount) }}</td>
                            <td><x-admin.status-badge :status="$invoice->payment_status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">{{ __('No open invoices for this customer in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('accounting')
            <p><strong>{{ __('Linked receivable account') }}:</strong> {{ $linkedAccount ?? __('Accounts Receivable control account') }} <span class="small">({{ __('invoices post to the receivable control account 1200') }})</span></p>
            <div class="table-wrap"><table>
                <thead><tr><th>Journal</th><th>Date</th><th>Source</th><th>Description</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($rows as $entry)
                        <tr>
                            <td><a href="{{ route('admin.accounting.journal-entries.show', $entry) }}">{{ $entry->journal_number }}</a></td>
                            <td>{{ $entry->journal_date?->toDateString() }}</td>
                            <td>{{ $entry->source_module }}</td>
                            <td>{{ $entry->description }}</td>
                            <td>{{ $money($entry->total_debit) }}</td>
                            <td><x-admin.status-badge :status="$entry->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">{{ __('No journal entries for this customer in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('zatca')
            <p class="help-box">{{ __('Local ZATCA Record: the ERP keeps a UUID, a QR payload, a local XML reference and a local status for each approved invoice. Clearance is not verified live; the ZATCA Phase-2 integration is not yet operational.') }}</p>
            <div class="table-wrap"><table>
                <thead><tr><th>Invoice</th><th>UUID</th><th>QR</th><th>XML</th><th>Local status</th><th>Retries</th><th>Last message</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $record)
                        <tr>
                            <td>{{ $record->customerInvoice?->invoice_number ?? '-' }}</td>
                            <td><span class="small">{{ $record->uuid }}</span></td>
                            <td>{{ $record->qrStatus() }}</td>
                            <td>{{ $record->xmlStatus() }}</td>
                            <td><x-admin.status-badge :status="$record->clearance_status"/> <span class="small">(local, not verified live)</span></td>
                            <td>{{ $record->retry_count }}</td>
                            <td>{{ $record->zatca_response_message ?? $record->failed_reason ?? '-' }}</td>
                            <td><a class="btn sm outline" href="{{ route('admin.accounting.zatca.show', $record) }}">View record</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">{{ __('No local ZATCA records for this customer in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('activity')
            <div class="table-wrap"><table>
                <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($rows as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $log->user_name }}</td>
                            <td>{{ $log->module }}</td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">{{ __('No activity recorded for this customer yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break
    @endswitch

    <div class="form-actions">
        @if ($readonly)
            <span class="small">{{ __('Showing the latest :count of :total', ['count' => $rows->count(), 'total' => $rows->total()]) }}</span>
        @else
            @if ($rows->currentPage() > 1)<button type="button" class="btn outline" data-panel-load="{{ $panelUrl.'?page='.($rows->currentPage() - 1) }}">{{ __('Previous') }}</button>@endif
            <span>{{ $rows->currentPage() }} / {{ max($rows->lastPage(), 1) }}</span>
            @if ($rows->hasMorePages())<button type="button" class="btn outline" data-panel-load="{{ $panelUrl.'?page='.($rows->currentPage() + 1) }}">{{ __('Next') }}</button>@endif
            <x-admin.workspace-previous/>
            @if ($hasNext)<button type="button" class="btn outline" data-workspace-next>{{ __('Next section') }}</button>@endif
        @endif
        @if ($viewAll)<a class="btn outline" href="{{ $viewAll }}">{{ __('View all') }}</a>@endif
    </div>
</div>
