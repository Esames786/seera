@props([
    'id',                  // unique modal id, e.g. qc-customer
    'target',              // id (or comma-separated ids) of the select(s) that receive the new option
    'url',                 // POST endpoint answering JSON {id, label, parent?} when Accept: application/json
    'title',
    'permission',          // permission module the user needs "create" on, e.g. Customers
    'submit' => 'Save',
    'label' => '+ New',
    'wide' => false,
])

{{--
    "+ New" next to a dropdown. Opens a small dialog, posts the fields to the
    normal store route as JSON, and drops the created record into the select
    without leaving (or losing) the form the user was filling in. Fields with
    data-prefill-from="some_select_id" are pre-filled from the main form when
    the dialog opens (e.g. the department already chosen for the user).
--}}
@php $allowed = auth()->check() && auth()->user()->hasPermission($permission, 'create'); @endphp

@if ($allowed)
    <button type="button" class="quick-create-trigger" data-quick-create="{{ $id }}" title="{{ $title }}">{{ $label }}</button>

    @push('modals')
        <div class="modal-overlay quick-create-modal" id="{{ $id }}" data-target="{{ $target }}" data-url="{{ $url }}">
            <div class="modal-card {{ $wide ? 'wide' : '' }}">
                <div class="modal-head">
                    <span>{{ $title }}</span>
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

                function open(modal) {
                    if (!modal) return;
                    clearErrors(modal);
                    modal.querySelectorAll('[data-prefill-from]').forEach(function (field) {
                        var source = document.getElementById(field.dataset.prefillFrom);
                        if (source && source.value) field.value = source.value;
                    });
                    modal.classList.add('open');
                    var first = modal.querySelector('input:not([type=hidden]), select, textarea');
                    if (first) first.focus();
                }

                function close(modal) {
                    if (modal) modal.classList.remove('open');
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

                function addOption(select, record) {
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

                    select.value = value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-quick-create]');
                    if (trigger) {
                        event.preventDefault();
                        open(document.getElementById(trigger.dataset.quickCreate));
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
                    clearErrors(modal);
                    button.disabled = true;

                    fetch(modal.dataset.url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': tokenFor(form)
                        },
                        body: new FormData(form)
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
                            modal.dataset.target.split(',').forEach(function (id) {
                                var select = document.getElementById(id.trim());
                                if (select) addOption(select, payload);
                            });
                            form.reset();
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
