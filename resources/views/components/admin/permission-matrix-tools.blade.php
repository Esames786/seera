@props([
    'table',                    // id of the permission table
    'departmentSelect' => null, // id of a department <select> that drives the module filter
    'showAllToggle' => null,    // id of a "show all modules" checkbox
    'groups' => [],             // department id => [modules] (from PermissionGroups::byDepartmentId)
    'note' => null,             // id of an element that shows "x of y modules"
])

{{--
    Behaviour for a permission matrix:
      - an "All" checkbox at the start of each module row that ticks/unticks the
        row's actions together (indeterminate when only some are ticked);
      - "Select all visible" / "Clear visible" buttons for the whole table;
      - optional department-driven module filter that hides unrelated rows.
    Hiding a row is display only: its checkboxes stay in the form, so nothing
    is granted or revoked by filtering.
--}}
@once
    @push('scripts')
    <script>
        window.seeraPermissionMatrix = function (tableId, options) {
            var table = document.getElementById(tableId);
            if (!table) return;
            options = options || {};

            function actionBoxes(row) {
                return row.querySelectorAll('input[type=checkbox][name="permissions[]"]');
            }

            function syncRow(row) {
                var all = row.querySelector('.js-row-all');
                if (!all) return;
                var boxes = actionBoxes(row);
                var checked = 0;
                boxes.forEach(function (box) { if (box.checked) checked++; });
                all.checked = boxes.length > 0 && checked === boxes.length;
                all.indeterminate = checked > 0 && checked < boxes.length;
            }

            var rows = table.querySelectorAll('tbody tr[data-module]');
            rows.forEach(syncRow);

            table.addEventListener('change', function (event) {
                var target = event.target;
                if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') return;
                var row = target.closest('tr');
                if (!row) return;
                if (target.classList.contains('js-row-all')) {
                    actionBoxes(row).forEach(function (box) { box.checked = target.checked; });
                    target.indeterminate = false;
                    return;
                }
                syncRow(row);
            });

            document.querySelectorAll('[data-matrix-select="' + tableId + '"]').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    var on = button.dataset.matrixValue === '1';
                    rows.forEach(function (row) {
                        if (row.classList.contains('module-hidden')) return;
                        actionBoxes(row).forEach(function (box) { box.checked = on; });
                        syncRow(row);
                    });
                });
            });

            var department = options.departmentSelect ? document.getElementById(options.departmentSelect) : null;
            var showAll = options.showAllToggle ? document.getElementById(options.showAllToggle) : null;
            var note = options.note ? document.getElementById(options.note) : null;
            var groups = options.groups || {};

            function applyFilter() {
                var modules = department && department.value ? groups[department.value] : null;
                var everything = !modules || (showAll && showAll.checked);
                var visible = 0;

                rows.forEach(function (row) {
                    var show = everything || modules.indexOf(row.dataset.module) !== -1;
                    row.classList.toggle('module-hidden', !show);
                    if (show) visible++;
                });

                if (note) {
                    note.textContent = everything
                        ? 'Showing all ' + rows.length + ' modules.'
                        : 'Showing ' + visible + ' of ' + rows.length + ' modules relevant to this department. Hidden modules keep their current permissions.';
                }
            }

            if (department) department.addEventListener('change', applyFilter);
            if (showAll) showAll.addEventListener('change', applyFilter);
            if (department || showAll) applyFilter();
        };
    </script>
    @endpush
@endonce

@push('scripts')
<script>
    window.seeraPermissionMatrix(@json($table), {
        departmentSelect: @json($departmentSelect),
        showAllToggle: @json($showAllToggle),
        groups: @json((object) $groups),
        note: @json($note)
    });
</script>
@endpush
