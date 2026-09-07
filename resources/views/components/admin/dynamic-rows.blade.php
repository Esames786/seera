@props([
    'body',        // id of the <tbody> holding the rows
    'template',    // id of the <template> with one <tr>; __INDEX__ is replaced with the next row index
    'add',         // id of the "+ Add" button
    'min' => 1,    // rows that must remain
])

{{--
    Add/remove rows on a line table (employee documents, purchase order lines).
    Rows are plain form inputs indexed as name[__INDEX__][field]; nothing is
    saved until the surrounding form is submitted, so removing an unsaved row
    never touches anything already on the record.
--}}
@once
    @push('scripts')
    <script>
        window.seeraDynamicRows = function (bodyId, templateId, addId, min) {
            var body = document.getElementById(bodyId);
            var template = document.getElementById(templateId);
            var add = document.getElementById(addId);
            if (!body || !template) return;

            function rows() {
                return body.querySelectorAll('tr[data-row-index]');
            }

            function nextIndex() {
                var max = -1;
                rows().forEach(function (row) {
                    max = Math.max(max, parseInt(row.dataset.rowIndex, 10));
                });
                return max + 1;
            }

            function refresh() {
                var all = rows();
                all.forEach(function (row) {
                    var button = row.querySelector('.row-remove');
                    if (button) button.disabled = all.length <= min;
                });
                body.dispatchEvent(new CustomEvent('seera:rows-changed', { bubbles: true }));
            }

            function addRow() {
                var index = nextIndex();
                var holder = document.createElement('tbody');
                holder.innerHTML = template.innerHTML.replace(/__INDEX__/g, index).trim();
                var row = holder.querySelector('tr');
                if (!row) return null;
                row.dataset.rowIndex = index;
                body.appendChild(row);
                refresh();
                var first = row.querySelector('select, input, textarea');
                if (first) first.focus();
                return row;
            }

            if (add) {
                add.addEventListener('click', function (event) {
                    event.preventDefault();
                    addRow();
                });
            }

            body.addEventListener('click', function (event) {
                var button = event.target.closest('.row-remove');
                if (!button) return;
                event.preventDefault();
                if (rows().length <= min) return;
                button.closest('tr').remove();
                refresh();
            });

            refresh();
        };
    </script>
    @endpush
@endonce

@push('scripts')
<script>
    window.seeraDynamicRows(@json($body), @json($template), @json($add), {{ (int) $min }});
</script>
@endpush
