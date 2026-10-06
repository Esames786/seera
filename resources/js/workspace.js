import { relatedPanels } from './workspace-related-panels';

// Generic connected workspace: one parent form plus lazily loaded related
// panels, addressed by hash so every tab is bookmarkable. Progressive
// enhancement: without JS the parent form and the nav links still work.
const root = document.querySelector('[data-workspace]');
if (root) {
    const nav = root.querySelector('[data-workspace-nav]');
    const form = root.querySelector('[data-workspace-form]');
    const related = relatedPanels();
    const links = [...nav.querySelectorAll('[data-workspace-section], [data-workspace-related]')];
    const keyOf = link => link.dataset.workspaceSection || link.dataset.workspaceRelated;
    const keys = links.map(keyOf);
    let current = keys[0];
    const guardedShow = (key, callback) => {
        if (root.querySelector('[data-saving="1"]')) return;
        if (key === current) { callback?.(); return; }
        const currentForm = root.querySelector('[data-related-panel="' + current + '"] form') || (!related.has(current) ? form?.querySelector('form') : null);
        const navigate = () => { show(key); callback?.(); };
        if (!root.hasAttribute('data-workspace-guard-tabs') || !currentForm || currentForm.dispatchEvent(new CustomEvent('seera:before-form-close', { bubbles: true, cancelable: true, detail: { close: navigate } }))) navigate();
    };
    const previousButtons = () => root.querySelectorAll('[data-workspace-previous]').forEach(button => {
        button.hidden = false;
        button.disabled = keys.indexOf(current) <= 0;
    });
    const show = key => {
        if (!keys.includes(key)) key = keys[0];
        current = key;
        previousButtons();
        const isRelated = related.has(key);
        if (form) form.hidden = isRelated;
        related.show(isRelated ? key : null);
        links.forEach(link => {
            const active = keyOf(link) === key;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    };
    nav.hidden = false;
    nav.addEventListener('click', event => {
        const link = event.target.closest('[data-workspace-section], [data-workspace-related]');
        if (!link) return;
        event.preventDefault();
        guardedShow(keyOf(link), () => history.replaceState(null, '', link.hash));
    });
    document.addEventListener('seera:workspace-next', event => {
        const index = keys.indexOf(event.detail.panel);
        if (index >= 0 && keys[index + 1]) {
            show(keys[index + 1]);
            history.replaceState(null, '', '#' + keys[index + 1]);
        }
    });
    document.addEventListener('seera:workspace-panel-loaded', previousButtons);
    root.addEventListener('click', event => {
        const button = event.target.closest('[data-workspace-previous]');
        if (!button || button.disabled || root.querySelector('[data-saving="1"]')) return;
        const index = keys.indexOf(current);
        if (index <= 0) return;
        guardedShow(keys[index - 1], () => {
            history.replaceState(null, '', '#' + keys[index - 1]);
            nav.querySelector('[aria-current="location"]')?.focus();
        });
    });
    document.addEventListener('seera:reveal-form', event => {
        const panel = event.target.closest('[data-related-panel]');
        show(panel ? panel.dataset.relatedPanel : keys[0]);
    });
    window.addEventListener('hashchange', () => {
        const key = location.hash.slice(1);
        if (!root.hasAttribute('data-workspace-guard-tabs')) { show(key); return; }
        history.replaceState(null, '', '#' + current);
        guardedShow(key, () => history.replaceState(null, '', '#' + key));
    });
    // A parent form with validation errors opens on the profile so the errors are visible.
    show(form?.querySelector('.field-error, .alert.danger, .is-invalid') ? keys[0] : location.hash.slice(1));
}
