@php /** @var \App\Models\AutomaticPostingRule|null $rule */ $rule = $rule ?? null; @endphp

<form method="POST" action="{{ $rule ? route('admin.accounting.posting-rules.update', $rule) : route('admin.accounting.posting-rules.store') }}">
    @csrf
    @if ($rule) @method('PUT') @endif

    <x-admin.form-section title="Posting Rule" columns="3">
        <div>
            <label for="source_module">Source Module *</label>
            <select id="source_module" name="source_module" class="select" required>
                @foreach ($sourceModules as $module)
                    <option value="{{ $module }}" @selected(old('source_module', $rule?->source_module) === $module)>{{ $module }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="trigger_event">Trigger Event *</label>
            <select id="trigger_event" name="trigger_event" class="select" required>
                @foreach ($triggerEvents as $event)
                    <option value="{{ $event }}" @selected(old('trigger_event', $rule?->trigger_event) === $event)>{{ $event }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="cost_center_rule">Cost Center Rule *</label>
            <select id="cost_center_rule" name="cost_center_rule" class="select" required>
                @foreach ($costCenterRules as $costRule)
                    <option value="{{ $costRule }}" @selected(old('cost_center_rule', $rule?->cost_center_rule ?? 'None') === $costRule)>{{ $costRule }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="debit_account_id">Debit Account</label>
            <select id="debit_account_id" name="debit_account_id" class="select">
                <option value="">Resolved from the document</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected(old('debit_account_id', $rule?->debit_account_id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="credit_account_id">Credit Account</label>
            <select id="credit_account_id" name="credit_account_id" class="select">
                <option value="">Resolved from the document</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected(old('credit_account_id', $rule?->credit_account_id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="auto_post">Auto Post</label>
            <select id="auto_post" name="auto_post" class="select">
                <option value="0" @selected(! old('auto_post', $rule?->auto_post ?? false))>No — create a draft journal</option>
                <option value="1" @selected(old('auto_post', $rule?->auto_post ?? false))>Yes — post straight to the ledger</option>
            </select>
        </div>
        <div>
            <label for="approval_required">Approval Required</label>
            <select id="approval_required" name="approval_required" class="select">
                <option value="1" @selected(old('approval_required', $rule?->approval_required ?? true))>Yes</option>
                <option value="0" @selected(! old('approval_required', $rule?->approval_required ?? true))>No</option>
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $rule?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $rule?->notes) }}</textarea></div>
    </x-admin.form-section>

    <div class="help-box">
        Leave an account blank when the posting engine already resolves it from the document — for example a supplier bill uses each line's own expense account.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.accounting.posting-rules.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $rule ? 'Update Posting Rule' : 'Save Posting Rule' }}</button>
    </div>
</form>
