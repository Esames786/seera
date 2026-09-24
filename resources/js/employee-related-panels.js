// Each related form lives outside the profile form. Switching tabs never removes
// its DOM, so typed values and selected files survive until explicitly saved.
export function employeeRelatedPanels() {
    const host = document.querySelector('[data-employee-related-host]');
    const links = new Map([...document.querySelectorAll('[data-employee-related]')]
        .map(link => [link.dataset.employeeRelated, link.dataset.relatedUrl]));
    const panels = new Map();
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const signal = (form, name) => form?.dispatchEvent(new CustomEvent(name, { bubbles: true }));
    const next = panel => document.dispatchEvent(new CustomEvent('seera:workspace-next', { detail: { panel: panel.dataset.relatedPanel } }));
    const close = () => {
        const navigate = () => location.assign(host.dataset.closeUrl);
        if (document.dispatchEvent(new CustomEvent('seera:before-navigation', { cancelable: true, detail: { navigate } }))) navigate();
    };
    const guarded = (panel, callback) => {
        const form = panel.querySelector('form');
        if (form?.dataset.saving === '1') return;
        const event = new CustomEvent('seera:before-form-close', { bubbles: true, cancelable: true, detail: { close: callback } });
        if (!form || form.dispatchEvent(event)) callback();
    };
    const status = (panel, message) => { panel.querySelector('[data-panel-status]').textContent = message; };
    const leaveDays = form => {
        const start = form.elements.namedItem('start_date');
        const end = form.elements.namedItem('end_date');
        const override = form.elements.namedItem('total_days_override');
        const days = form.elements.namedItem('total_days');
        if (!override || !days) return;
        days.readOnly = override.value !== '1';
        if (days.readOnly) {
            const count = (Date.parse(end.value) - Date.parse(start.value)) / 86400000 + 1;
            days.value = Number.isFinite(count) && count > 0 ? count : '';
        }
    };
    const load = async (panel, url, message = '') => {
        if (panel.dataset.loading === '1') return;
        panel.dataset.loading = '1';
        status(panel, host.dataset.loading);
        try {
            const response = await fetch(url, { headers });
            const body = await response.json();
            if (!response.ok || typeof body.html !== 'string') throw new Error();
            panel.innerHTML = body.html;
            panel.dataset.loaded = '1';
            const form = panel.querySelector('form');
            if (form) { leaveDays(form); signal(form, 'seera:form-baseline'); }
            status(panel, message);
        } catch {
            status(panel, message ? `${message} ${host.dataset.refreshError || host.dataset.error}` : host.dataset.error);
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn outline';
            retry.dataset.panelLoad = url;
            retry.textContent = host.dataset.retry || 'Retry';
            panel.querySelector('[data-panel-status]').append(' ', retry);
        } finally { delete panel.dataset.loading; }
    };
    if (host) {
        host.addEventListener('input', event => { if (event.target.form) leaveDays(event.target.form); });
        host.addEventListener('change', event => { if (event.target.form) leaveDays(event.target.form); });
        // Capture prevents the global native-form guard from treating an AJAX save
        // as a page departure (other sections may legitimately still be dirty).
        host.addEventListener('submit', async event => {
            const form = event.target.closest('[data-related-save]');
            if (!form) return;
            event.preventDefault();
            if (form.dataset.saving === '1') return;
            const panel = form.closest('[data-related-panel]');
            const payload = new FormData(form);
            const intent = event.submitter?.value || 'stay';
            payload.set('_save_action', intent);
            const controls = [...form.elements].filter(control => !control.disabled);
            const errors = form.querySelector('[data-related-errors]');
            errors.hidden = true;
            form.querySelectorAll('[data-field-error]').forEach(node => { node.textContent = ''; });
            form.dataset.saving = '1';
            controls.forEach(control => { control.disabled = true; });
            let saved = false;
            let savedUrl = links.get(panel.dataset.relatedPanel);
            try {
                const response = await fetch(form.action, { method: 'POST', headers, body: payload });
                const body = await response.json();
                if (!response.ok) {
                    errors.textContent = Object.values(body.errors || {}).flat().join(' ') || body.message || host.dataset.error;
                    errors.hidden = false;
                    form.querySelectorAll('[data-field-error]').forEach(node => {
                        node.textContent = (body.errors?.[node.dataset.fieldError] || []).join(' ');
                    });
                } else {
                    saved = true;
                    if (body.panel_url) savedUrl = body.panel_url;
                }
            } catch { errors.textContent = host.dataset.error; errors.hidden = false; }
            finally {
                controls.forEach(control => { control.disabled = false; });
                delete form.dataset.saving;
            }
            if (saved) {
                signal(form, 'seera:form-saved');
                // Prevent resubmission if the subsequent GET fails after a good POST.
                form.hidden = true;
                await load(panel, savedUrl, host.dataset.saved);
                if (intent === 'next') next(panel);
                if (intent === 'close') close();
            } else { errors.tabIndex = -1; errors.focus(); }
        }, true);
        host.addEventListener('click', event => {
            const panel = event.target.closest('[data-related-panel]');
            if (!panel || panel.dataset.loading === '1' || panel.querySelector('[data-saving="1"]')) return;
            const target = event.target.closest('button');
            if (!target) return;
            if (target.hasAttribute('data-workspace-next')) {
                next(panel);
            } else if (target.hasAttribute('data-add-salary-item')) {
                const template = panel.querySelector('[data-salary-item-template]');
                const index = Number(panel.dataset.itemIndex || 0);
                panel.dataset.itemIndex = index + 1;
                panel.querySelector('[data-salary-items]').insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
            } else if (target.hasAttribute('data-remove-salary-item')) {
                target.closest('[data-salary-item]').remove();
            } else if (target.dataset.panelLoad) {
                guarded(panel, () => load(panel, target.dataset.panelLoad));
            } else if (target.dataset.relatedAction) {
                guarded(panel, async () => {
                    if (!confirm(host.dataset.confirm)) return;
                    const payload = new FormData();
                    payload.set('_token', host.dataset.token);
                    if (target.dataset.action === 'reject') {
                        const reason = prompt(host.dataset.reason);
                        if (!reason) return;
                        payload.set('rejection_reason', reason);
                    }
                    target.disabled = true;
                    try {
                        const response = await fetch(target.dataset.relatedAction, { method: 'POST', headers, body: payload });
                        const body = await response.json();
                        if (!response.ok) { status(panel, Object.values(body.errors || {}).flat().join(' ') || body.message || host.dataset.error); return; }
                        await load(panel, links.get(panel.dataset.relatedPanel), body.message);
                    } catch { status(panel, host.dataset.error); }
                    finally { target.disabled = false; }
                });
            }
        });
    }
    return {
        has: key => !!host && links.has(key),
        show: key => {
            panels.forEach((panel, name) => { panel.hidden = name !== key; });
            if (!key || !host || !links.has(key)) return;
            let panel = panels.get(key);
            if (!panel) {
                panel = document.createElement('section');
                panel.dataset.relatedPanel = key;
                panel.innerHTML = '<div role="status" aria-live="polite" data-panel-status></div>';
                host.append(panel);
                panels.set(key, panel);
            }
            panel.hidden = false;
            if (!panel.dataset.loaded) load(panel, links.get(key));
        },
    };
}
