// Native browser back/refresh/close uses beforeunload. In-app navigation gets
// explicit choices. Nothing is auto-saved, including approvals and posting.
const dialog = document.getElementById('unsaved-changes');
if (dialog) {
    const snapshots = new Map();
    const invalid = new Set();
    let leaving = false;
    let pending = null;
    let saveForm = null;
    let submitted = null;
    let discardedForms = [];
    const fields = form => [...form.elements].filter(field =>
        field.name && !field.hasAttribute('data-dirty-ignore') && !['_token', '_method'].includes(field.name)
        && !['submit', 'button', 'reset'].includes(field.type));
    const snapshot = form => JSON.stringify(fields(form).map(field => [
        field.name, field.disabled, field.type === 'file'
            ? [...field.files].map(file => [file.name, file.size, file.lastModified])
            : ['radio', 'checkbox'].includes(field.type) ? [field.checked, field.value]
                : field.multiple ? [...field.selectedOptions].map(option => option.value) : field.value,
    ]));
    const register = form => {
        if (form.method.toLowerCase() === 'get' || form.hasAttribute('data-no-dirty-guard')) return;
        if (!fields(form).some(field => field.type !== 'hidden')) return;
        if (!snapshots.has(form)) snapshots.set(form, snapshot(form));
    };
    document.querySelectorAll('form').forEach(register);
    if (dialog.dataset.validationErrors === '1') {
        document.querySelectorAll('.page form').forEach(form => {
            if (snapshots.has(form)) invalid.add(form);
        });
    }
    const dirtyForms = () => [...snapshots.keys()].filter(form => form.isConnected
        && (invalid.has(form) || snapshots.get(form) !== snapshot(form)));
    const reset = form => {
        register(form);
        if (snapshots.has(form)) snapshots.set(form, snapshot(form));
        invalid.delete(form);
    };
    // AJAX forms must emit saved ONLY after a successful server response.
    document.addEventListener('seera:form-saved', event => reset(event.target));
    document.addEventListener('seera:form-baseline', event => reset(event.target));
    document.addEventListener('focusin', event => {
        if (event.target.form) register(event.target.form);
    });
    const prompt = (action, forms = dirtyForms()) => {
        if (!forms.length) { action(); return; }
        pending = action;
        discardedForms = forms;
        // Saving is explicit, with the form's normal validation and redirect.
        // Never guess which form to save if multiple forms contain changes.
        saveForm = forms.length === 1 ? forms[0] : null;
        dialog.querySelector('[data-unsaved-save]').hidden = !saveForm;
        dialog.showModal();
        dialog.querySelector('[data-unsaved-stay]').focus();
    };
    const stay = () => { pending = null; saveForm = null; dialog.close(); };
    document.addEventListener('seera:before-form-close', event => {
        if (!dirtyForms().includes(event.target)) return;
        event.preventDefault();
        prompt(() => { reset(event.target); event.detail.close(); }, [event.target]);
    });
    document.addEventListener('seera:before-navigation', event => {
        if (!dirtyForms().length) return;
        event.preventDefault();
        prompt(() => { leaving = true; event.detail.navigate(); });
    });
    dialog.addEventListener('cancel', stay);
    dialog.querySelector('[data-unsaved-stay]').addEventListener('click', stay);
    dialog.querySelector('[data-unsaved-discard]').addEventListener('click', () => {
        const action = pending;
        discardedForms.forEach(reset);
        stay();
        leaving = true;
        action?.();
        setTimeout(() => { leaving = false; }, 1000);
    });
    dialog.querySelector('[data-unsaved-save]').addEventListener('click', () => {
        const form = saveForm;
        stay();
        const button = form?.querySelector('[data-save-default], button[type="submit"], input[type="submit"]');
        if (form) {
            form.dispatchEvent(new CustomEvent('seera:reveal-form', { bubbles: true }));
            form.requestSubmit(button || undefined);
        }
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey
            || event.shiftKey || event.altKey || link.hasAttribute('download') || link.target === '_blank') return;
        const url = new URL(link.href, location.href);
        if (url.origin === location.origin && url.pathname === location.pathname
            && url.search === location.search && url.hash) return;
        if (!['http:', 'https:'].includes(url.protocol) || !dirtyForms().length) return;
        event.preventDefault();
        prompt(() => { location.href = link.href; });
    });
    document.addEventListener('submit', event => {
        if (event.defaultPrevented) return; // AJAX owns its own success/failure.
        const otherDirty = dirtyForms().filter(form => form !== event.target);
        if (!leaving && otherDirty.length) {
            event.preventDefault();
            const { target, submitter } = event;
            prompt(() => target.requestSubmit(submitter || undefined), otherDirty);
            return;
        }
        submitted = { form: event.target, value: snapshot(event.target) };
    });
    window.addEventListener('beforeunload', event => {
        if (leaving || !dirtyForms().length) return;
        if (submitted && snapshot(submitted.form) === submitted.value
            && dirtyForms().every(form => form === submitted.form)) return;
        event.preventDefault();
        event.returnValue = '';
    });
    window.addEventListener('pageshow', () => { leaving = false; submitted = null; });
}
