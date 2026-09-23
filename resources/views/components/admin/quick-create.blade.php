@props([
    'id',                  // unique modal id, e.g. qc-customer
    'target' => '',        // id (or comma-separated ids) of the select(s) that receive the new option
    'targetSelector' => null, // CSS selector for repeated selects (dynamic rows); also patches their <template>
    'url',                 // POST endpoint answering JSON {id, label, parent?} when Accept: application/json
    'title',
    'permission',          // permission module the user needs "create" on, e.g. Customers
    'submit' => 'Save',
    'label' => '+ New',
    'wide' => false,
    'editUrl' => null,     // optional PUT endpoint with __ID__ placeholder; adds an "Edit" trigger for the selected option
    'editLabel' => 'Edit',
])

{{--
    "+ New" next to a dropdown. Opens a small dialog, posts the fields to the
    normal store route as JSON, and drops the created record into the select
    without leaving (or losing) the form the user was filling in. Fields with
    data-prefill-from="some_select_id" are pre-filled from the main form when
    the dialog opens (e.g. the department already chosen for the user).

    With edit-url the same dialog also edits the option currently selected
    (client change request NR-25): fields with data-edit-from="label" are
    filled from the option text and the form is sent as PUT.
--}}
@php $allowed = auth()->check() && auth()->user()->hasPermission($permission, 'create'); @endphp

@if ($allowed)
    <span class="quick-create-triggers">
        <button type="button" class="quick-create-trigger" data-quick-create="{{ $id }}" title="{{ $title }}">{{ $label }}</button>
        @if ($editUrl)
            <button type="button" class="quick-create-trigger" data-quick-edit="{{ $id }}" title="Edit the selected {{ strtolower($title) }}">{{ $editLabel }}</button>
        @endif
    </span>

    @push('modals')
        <div class="modal-overlay quick-create-modal" id="{{ $id }}" data-target="{{ $target }}" @if($targetSelector) data-target-selector="{{ $targetSelector }}" @endif data-url="{{ $url }}" @if($editUrl) data-edit-url="{{ $editUrl }}" @endif>
            <div class="modal-card {{ $wide ? 'wide' : '' }}">
                <div class="modal-head">
                    <span><span class="qc-mode-label">New</span> {{ $title }}</span>
                    <button type="button" class="modal-close js-qc-close" aria-label="Close">&times;</button>
                </div>
                <form class="quick-create-form" novalidate>
                    @csrf
                    <div class="modal-body">
                        <div class="alert qc-error" hidden></div>
                        <div class="form-grid">{{ $slot }}</div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn outline js-qc-close">Cancel</button>
                        <button type="submit" class="btn primary">{{ $submit }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush

    @once
        @push('scripts')
        <script>
            (function () {
                function tokenFor(form) {
                    var input = form.querySelector('input[name="_token"]');
                    return input ? input.value : '';
                }

                function targets(modal) {
                    var found = [];
                    (modal.dataset.target || '').split(',').forEach(function (id) {
                        var select = id.trim() ? document.getElementById(id.trim()) : null;
                        if (select) found.push(select);
                    });
                    // Repeated selects in dynamic rows all receive the new option.
                    if (modal.dataset.targetSelector) {
                        document.querySelectorAll(modal.dataset.targetSelector).forEach(function (select) {
                            if (found.indexOf(select) === -1) found.push(select);
                        });
                    }
                    return found;
                }

                function firstTarget(modal) {
                    return targets(modal)[0] || null;
                }

                /** Rows added after the value was created must offer it too. */
                function patchTemplates(modal, record) {
                    if (!modal.dataset.targetSelector) return;
                    document.querySelectorAll('template').forEach(function (template) {
                        template.content.querySelectorAll(modal.dataset.targetSelector).forEach(function (select) {
                            var value = String(record.id);
                            var exists = Array.prototype.some.call(select.options, function (o) { return o.value === value; });
                            if (exists) return;
                            var option = document.createElement('option');
                            option.value = value;
                            option.textContent = record.label;
                            select.appendChild(option);
                        });
                    });
                }

                function open(modal, mode) {
                    if (!modal) return;
                    clearErrors(modal);
                    var form = modal.querySelector('.quick-create-form');
                    form.reset();
                    form.dataset.mode = mode;
                    delete form.dataset.editId;

                    var modeLabel = modal.querySelector('.qc-mode-label');
                    if (modeLabel) modeLabel.textContent = mode === 'edit' ? 'Edit' : 'New';

                    if (mode === 'edit') {
                        var select = firstTarget(modal);
                        var option = select && select.value ? select.options[select.selectedIndex] : null;
                        if (!option) {
                            showErrors(modal, { message: 'Choose an entry in the dropdown first, then press Edit.' });
                            modal.classList.add('open');
                            return;
                        }
                        form.dataset.editId = select.value;
                        modal.querySelectorAll('[data-edit-from="label"]').forEach(function (field) {
                            field.value = option.textContent.trim();
                        });
                    } else {
                        modal.querySelectorAll('[data-prefill-from]').forEach(function (field) {
                            var source = document.getElementById(field.dataset.prefillFrom);
                            if (source && source.value) field.value = source.value;
                        });
                    }

                    form.dispatchEvent(new CustomEvent('seera:form-baseline', { bubbles: true }));
                    modal.classList.add('open');
                    var first = modal.querySelector('input:not([type=hidden]), select, textarea');
                    if (first) first.focus();
                }

                function close(modal) {
                    if (!modal) return;
                    var form = modal.querySelector('.quick-create-form');
                    var closeNow = function () { modal.classList.remove('open'); };
                    if (form && !form.dispatchEvent(new CustomEvent('seera:before-form-close', {
                        bubbles: true, cancelable: true, detail: { close: closeNow }
                    }))) return;
                    closeNow();
                }

                function clearErrors(modal) {
                    modal.querySelectorAll('.qc-field-error').forEach(function (el) { el.remove(); });
                    var box = modal.querySelector('.qc-error');
                    box.hidden = true;
                    box.textContent = '';
                }

                function showErrors(modal, payload) {
                    var box = modal.querySelector('.qc-error');
                    box.textContent = payload.message || 'Please correct the highlighted fields.';
                    box.hidden = false;
                    Object.keys(payload.errors || {}).forEach(function (name) {
                        var field = modal.querySelector('[name="' + name + '"]');
                        if (!field) return;
                        var error = document.createElement('div');
                        error.className = 'field-error qc-field-error';
                        var messages = payload.errors[name];
                        error.textContent = Array.isArray(messages) ? messages[0] : messages;
                        field.insertAdjacentElement('afterend', error);
                    });
                }

                function addOption(select, record, shouldSelect) {
                    var value = String(record.id);
                    var parent = (record.parent === undefined || record.parent === null) ? '' : String(record.parent);
                    var existing = Array.prototype.find.call(select.options, function (o) { return o.value === value; });

                    if (!existing) {
                        var option = document.createElement('option');
                        option.value = value;
                        option.textContent = record.label;
                        if (parent) option.dataset.parent = parent;
                        select.appendChild(option);
                    }

                    // Dependent selects keep their own option cache; let them add it first.
                    select.dispatchEvent(new CustomEvent('seera:option-added', {
                        detail: { value: value, label: record.label, parent: parent }
                    }));

                    // With repeated rows only the first free row is switched to the new
                    // value; the others just gain the option.
                    if (shouldSelect === false) return;

                    select.value = value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                function renameOption(select, record) {
                    var value = String(record.id);
                    Array.prototype.forEach.call(select.options, function (option) {
                        if (option.value === value) option.textContent = record.label;
                    });
                    select.value = value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-quick-create]');
                    if (trigger) {
                        event.preventDefault();
                        open(document.getElementById(trigger.dataset.quickCreate), 'create');
                        return;
                    }
                    var editor = event.target.closest('[data-quick-edit]');
                    if (editor) {
                        event.preventDefault();
                        open(document.getElementById(editor.dataset.quickEdit), 'edit');
                        return;
                    }
                    var closer = event.target.closest('.quick-create-modal .js-qc-close');
                    if (closer) {
                        close(closer.closest('.quick-create-modal'));
                        return;
                    }
                    if (event.target.classList && event.target.classList.contains('quick-create-modal')) {
                        close(event.target);
                    }
                });

                document.addEventListener('submit', function (event) {
                    var form = event.target.closest('.quick-create-form');
                    if (!form) return;
                    event.preventDefault();

                    var modal = form.closest('.quick-create-modal');
                    var button = form.querySelector('[type=submit]');
                    var editing = form.dataset.mode === 'edit' && form.dataset.editId;
                    if (form.dataset.mode === 'edit' && !form.dataset.editId) return;

                    clearErrors(modal);
                    button.disabled = true;

                    var body = new FormData(form);
                    var url = modal.dataset.url;
                    if (editing) {
                        url = modal.dataset.editUrl.replace('__ID__', form.dataset.editId);
                        body.append('_method', 'PUT');
                    }

                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': tokenFor(form)
                        },
                        body: body
                    }).then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (payload) {
                            if (response.status === 422) {
                                showErrors(modal, payload);
                                return;
                            }
                            if (!response.ok || !payload.id) {
                                showErrors(modal, { message: payload.message || ('The record could not be saved (' + response.status + ').') });
                                return;
                            }
                            var repeated = !!modal.dataset.targetSelector;
                            var claimed = false;
                            targets(modal).forEach(function (select) {
                                if (editing) {
                                    renameOption(select, payload);
                                    return;
                                }
                                var take = ! repeated || (! claimed && select.value === '');
                                addOption(select, payload, take);
                                if (take) claimed = true;
                            });
                            if (!editing) patchTemplates(modal, payload);
                            form.reset();
                            form.dispatchEvent(new CustomEvent('seera:form-saved', { bubbles: true }));
                            close(modal);
                        });
                    }).catch(function () {
                        showErrors(modal, { message: 'Network error. Please try again.' });
                    }).finally(function () {
                        button.disabled = false;
                    });
                });
            })();
        </script>
        @endpush
    @endonce
@endif
