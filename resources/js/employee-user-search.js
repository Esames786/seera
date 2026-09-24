// Employee lookup is progressively enhanced; manual account creation still works.
const search = document.querySelector('[data-employee-search]');
if (search) {
    const results = document.getElementById('employee-search-results');
    const form = search.form;
    let timer;
    let request;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        request?.abort();
        results.replaceChildren();
        if (search.value.trim().length < 2) return;
        timer = setTimeout(async () => {
            request = new AbortController();
            try {
                const response = await fetch(`${search.dataset.employeeSearch}?q=${encodeURIComponent(search.value.trim())}`, {
                    headers: { Accept: 'application/json' }, signal: request.signal,
                });
                if (!response.ok) throw new Error('lookup');
                const payload = await response.json();
                const employees = payload.data;
                const unavailable = payload.unavailable ?? [];
                results.replaceChildren();
                if (!employees.length && !unavailable.length) results.textContent = search.dataset.empty;
                unavailable.forEach(employee => {
                    const note = document.createElement('p');
                    note.className = 'small';
                    note.textContent = `${employee.employee_code} — ${employee.name}: ${employee.reason}`;
                    results.append(note);
                });
                employees.forEach(employee => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn outline';
                    button.textContent = `${employee.employee_code} — ${employee.name}`;
                    button.addEventListener('click', () => {
                        if (!window.confirm(search.dataset.confirm)) return;
                        // Ordered so dependent selects reset before their child is set.
                        Object.entries(employee.fields).forEach(([name, value]) => {
                            const field = form.elements.namedItem(name);
                            if (!field) return;
                            field.value = value ?? '';
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        form.elements.namedItem('source_employee_id').value = employee.id;
                        search.value = `${employee.employee_code} — ${employee.name}`;
                        results.textContent = search.dataset.selected;
                    });
                    results.append(button);
                });
            } catch (error) {
                if (error.name !== 'AbortError') results.textContent = search.dataset.error;
            }
        }, 250);
    });
    document.querySelector('[data-clear-employee]')?.addEventListener('click', () => {
        form.elements.namedItem('source_employee_id').value = '';
        search.value = '';
        results.textContent = search.dataset.cleared;
    });
}
