// Bootstraps the offline foundation for a signed-in, mobile-capable account:
// one queue + sync manager per page, the service worker on offline-enabled
// pages, the Pending Sync widget where a page asks for it, and the logout
// warning when unsynced records remain on the device.
import { IndexedDbStore, MemoryStore, STORES } from './store.js';
import { OfflineQueue } from './queue.js';
import { SyncManager } from './sync-manager.js';
import { loadReference, getReference } from './reference.js';
import { mountPendingSync } from './ui.js';

let shell = null;

export function offlineShell() {
    if (shell) return shell;
    const body = document.body;
    if (!body || body.dataset.offlineCapable !== '1') return null;
    const t = JSON.parse(body.dataset.offlineI18n || '{}');
    const userId = Number(body.dataset.userId);
    const store = IndexedDbStore.available() ? new IndexedDbStore() : new MemoryStore();
    const queue = new OfflineQueue(store);
    let lease = null;
    try { lease = window.localStorage; } catch { lease = null; }
    const manager = new SyncManager({
        queue, userId, userName: body.dataset.userName, csrfToken: body.dataset.csrfToken, sessionUrl: body.dataset.syncSessionUrl,
        endpoints: { attendance: body.dataset.syncUrlAttendance, site_expense: body.dataset.syncUrlExpenses }, lease,
    });
    manager.attach(window);
    const clearPages = () => { if (navigator.serviceWorker && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage({ type: 'clear-pages' }); };

    shell = {
        t, userId, userName: body.dataset.userName, store, queue, manager, clearPages,
        urls: { reference: body.dataset.syncReferenceUrl, page: body.dataset.syncPageUrl, login: body.dataset.loginUrl, sw: body.dataset.swUrl },
        reference: async () => getReference(store, userId),
        refreshReference: async () => {
            try { return await loadReference(store, fetch, body.dataset.syncReferenceUrl, { 'X-CSRF-TOKEN': manager.csrfToken }); } catch { return getReference(store, userId); }
        },
        mount: (container, options = {}) => mountPendingSync(container, { queue, manager, t, userId, loginUrl: body.dataset.loginUrl, clearPages, ...options }),
    };
    registerServiceWorker(shell);
    guardLogout(shell);
    queue.counts(userId).then(counts => { if (counts.pending || counts.syncing) manager.sync({ reason: 'load' }); else manager.refresh(); });
    return shell;
}

function registerServiceWorker(shell) {
    const page = document.querySelector('[data-offline-page]');
    if (!page || !('serviceWorker' in navigator) || !shell.urls.sw) return;
    // Registered only from offline-enabled pages, scoped to the whole site so build assets and the three mobile pages can be cached.
    navigator.serviceWorker.register(shell.urls.sw, { scope: '/' }).catch(() => {});
}

function guardLogout(shell) {
    const forms = [...document.querySelectorAll('form[action$="/logout"]')];
    if (!forms.length) return;
    for (const form of forms) {
        form.addEventListener('submit', event => {
            if (form.dataset.offlineConfirmed === '1') return;
            event.preventDefault();
            shell.queue.hasUnsynced(null).then(unsynced => {
                if (!unsynced) { form.dataset.offlineConfirmed = '1'; shell.clearPages(); form.requestSubmit(); return; }
                let dialog = document.getElementById('offline-logout');
                if (!dialog) {
                    dialog = document.createElement('dialog');
                    dialog.id = 'offline-logout';
                    dialog.innerHTML = `<h3>${shell.t.pending_sync}</h3><p>${shell.t.logout_pending}</p><div class="form-actions"><button type="button" class="btn outline" data-stay>${shell.t.stay}</button><a class="btn outline" href="${shell.urls.page}">${shell.t.title}</a><button type="button" class="btn danger" data-leave>${shell.t.sign_out_anyway}</button></div>`;
                    document.body.append(dialog);
                    dialog.querySelector('[data-stay]').addEventListener('click', () => dialog.close());
                    dialog.querySelector('[data-leave]').addEventListener('click', () => { dialog.close(); form.dataset.offlineConfirmed = '1'; shell.clearPages(); form.requestSubmit(); });
                }
                dialog.showModal();
            }).catch(() => { form.dataset.offlineConfirmed = '1'; form.requestSubmit(); });
        });
    }
}

export { STORES };
