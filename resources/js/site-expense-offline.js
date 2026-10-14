// Offline Site Expense entry on the mobile form. When the server cannot be
// reached, the typed fields and the receipt file are queued on the device
// under a durable UUID; the normal online submit is untouched otherwise.
// The server re-validates every field and reference at sync and creates the
// same draft the online form creates (no approval, no accounting).
import { offlineShell } from './offline/shell.js';

const form = document.querySelector('[data-se-form][data-offline-expense]');
if (form) {
    const shell = offlineShell();
    if (shell) {
        const t = shell.t;
        const note = form.querySelector('[data-offline-note]');
        const offlineButton = form.querySelector('[data-save-offline]');
        const widget = document.querySelector('[data-pending-sync]');
        if (widget) shell.mount(widget, { compact: true });
        shell.refreshReference();

        const setMode = online => {
            if (note) { note.textContent = online ? '' : t.offline_form_note; note.hidden = online; }
            if (offlineButton) offlineButton.hidden = online;
            form.dataset.offline = online ? '0' : '1';
        };
        setMode(navigator.onLine !== false);
        window.addEventListener('online', () => setMode(true));
        window.addEventListener('offline', () => setMode(false));

        const fieldNames = ['expense_date', 'project_id', 'site_id', 'expense_category_id', 'supplier_id', 'payment_type', 'payment_account_id', 'taxable_amount', 'vat_applicable', 'vat_rate', 'description', 'reference_number', 'notes'];
        const payload = () => {
            const data = {};
            for (const name of fieldNames) {
                const field = form.elements[name];
                if (!field) continue;
                const value = field.value;
                if (value !== '' && value !== null && value !== undefined) data[name] = value;
            }
            if (!data.vat_applicable) data.vat_applicable = '0';
            return data;
        };
        const label = () => {
            const category = form.querySelector('#expense_category_id');
            const name = category && category.selectedOptions[0] ? category.selectedOptions[0].textContent.trim() : '';
            return `${name} · SAR ${Number(form.elements.taxable_amount?.value || 0).toFixed(2)}`;
        };

        const queueExpense = async () => {
            if (!form.reportValidity()) return;
            const file = form.elements.receipt && form.elements.receipt.files[0] ? form.elements.receipt.files[0] : null;
            const item = await shell.queue.enqueue({
                type: 'site_expense', action: 'create', payload: payload(), captured_at: new Date().toISOString(),
                owner_user_id: shell.userId, owner_name: shell.userName, label: label(),
                blob: file, blob_name: file ? file.name : null, blob_type: file ? file.type : null,
            });
            form.reset();
            form.dispatchEvent(new Event('change'));
            const flash = form.querySelector('[data-offline-flash]');
            if (flash) { flash.textContent = `${t.queued_expense} (${item.client_uuid.slice(0, 8)})`; flash.hidden = false; }
            await shell.manager.refresh();
            shell.manager.sync({ reason: 'queued' });
        };

        offlineButton?.addEventListener('click', queueExpense);
        form.addEventListener('submit', event => {
            if (form.dataset.offline === '1' && navigator.onLine === false) { event.preventDefault(); queueExpense(); }
        });
    }
}
