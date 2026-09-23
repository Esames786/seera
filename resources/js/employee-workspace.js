import { employeeRelatedPanels } from './employee-related-panels';

// Progressive enhancement: the accepted full form remains usable without JS.
const employeeForm = document.querySelector('[data-employee-workspace]');
if (employeeForm) {
    const keys = ['personal', 'employment', 'payroll', 'documents', 'access'];
    const sections = [...employeeForm.querySelectorAll(':scope > .form-section')];
    const nav = document.querySelector('.employee-workspace-nav');
    const state = employeeForm.querySelector('[name="_workspace_section"]');
    const related = employeeRelatedPanels();
    if (sections.length === keys.length && nav) {
        nav.hidden = false;
        sections.forEach((section, index) => { section.id = keys[index]; });
        const show = (key, all = false) => {
            const isRelated = !all && related.has(key);
            employeeForm.hidden = isRelated;
            related.show(isRelated ? key : null);
            if (isRelated) {
                nav.querySelectorAll('[data-employee-section], [data-employee-related]').forEach(link => {
                    const active = link.dataset.employeeRelated === key;
                    link.classList.toggle('active', active);
                    if (active) link.setAttribute('aria-current', 'location');
                    else link.removeAttribute('aria-current');
                });
                return;
            }
            if (!keys.includes(key)) key = 'personal';
            state.value = key;
            sections.forEach(section => { section.hidden = !all && section.id !== key; });
            nav.querySelectorAll('[data-employee-section], [data-employee-related]').forEach(link => {
                const active = !all && link.dataset.employeeSection === key;
                link.classList.toggle('active', active);
                if (active) link.setAttribute('aria-current', 'location');
                else link.removeAttribute('aria-current');
            });
        };
        nav.addEventListener('click', event => {
            const link = event.target.closest('[data-employee-section], [data-employee-related]');
            if (!link) return;
            event.preventDefault();
            show(link.dataset.employeeSection || link.dataset.employeeRelated);
            history.replaceState(null, '', link.hash);
        });
        employeeForm.addEventListener('click', event => {
            const link = event.target.closest('[data-open-related]');
            if (!link) return;
            event.preventDefault();
            show(link.dataset.openRelated);
            history.replaceState(null, '', link.hash);
        });
        nav.querySelector('[data-employee-all]').addEventListener('click', () => show(state.value, true));
        window.addEventListener('hashchange', () => show(location.hash.slice(1)));
        // Reveal invalid controls before the browser attempts to focus them.
        employeeForm.addEventListener('invalid', event => {
            const section = event.target.closest('.form-section');
            if (section) show(section.id, true);
        }, true);
        const errors = sections.filter(section => section.querySelector('.field-error'));
        if (errors.length) show(errors[0].id, true);
        else if (employeeForm.dataset.employeeWorkspace === 'create') show('personal', true);
        else show(location.hash.slice(1) || state.value);
    }
}
