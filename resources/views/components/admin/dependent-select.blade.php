@props([
    'parent',       // id of the controlling select, e.g. department_id
    'child',        // id of the dependent select, e.g. designation_id
    'placeholder' => 'Select...',
])

{{--
    Filters a child select down to the options that belong to the currently
    chosen parent. Each child option carries data-parent="{id}"; options with
    no data-parent stay visible so unassigned records are still selectable.
    A record created inline (quick-create) announces itself with a
    "seera:option-added" event so the cached option list picks it up.
--}}
@once
    @push('scripts')
    <script>
        window.seeraDependentSelect = function (parentId, childId, placeholder) {
            const parent = document.getElementById(parentId);
            const child = document.getElementById(childId);
            if (!parent || !child) return;

            // Keep the full option list so filtering is never destructive.
            const options = Array.from(child.options).map(function (option) {
                return {
                    value: option.value,
                    label: option.textContent,
                    parent: option.dataset.parent || '',
                };
            });

            function render(forceValue) {
                const selected = parent.value;
                const current = forceValue || child.value;

                child.innerHTML = '';

                options
                    .filter(function (option) {
                        return option.value === '' || !option.parent || !selected || option.parent === selected;
                    })
                    .forEach(function (option) {
                        const element = document.createElement('option');
                        element.value = option.value;
                        element.textContent = option.label;
                        if (option.parent) element.dataset.parent = option.parent;
                        if (option.value === current) element.selected = true;
                        child.appendChild(element);
                    });

                // If the previous choice no longer belongs to this parent, clear it.
                if (current && child.value !== current) {
                    child.value = '';
                }

                if (child.options.length === 1 && child.options[0].value === '') {
                    child.options[0].textContent = 'No matching ' + placeholder;
                }
            }

            parent.addEventListener('change', function () { render(); });

            child.addEventListener('seera:option-added', function (event) {
                const added = event.detail || {};
                if (!options.some(function (option) { return option.value === added.value; })) {
                    options.push({ value: added.value, label: added.label, parent: added.parent || '' });
                }
                render(added.value);
            });

            render();
        };
    </script>
    @endpush
@endonce

@push('scripts')
<script>
    window.seeraDependentSelect(@json($parent), @json($child), @json($placeholder));
</script>
@endpush
