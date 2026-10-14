// The Mobile Sync page: mounts the full Pending Sync widget (with Clear
// offline data) and refreshes the cached reference data while online.
import { offlineShell } from './offline/shell.js';

const page = document.querySelector('[data-offline-sync-page]');
if (page) {
    const shell = offlineShell();
    if (shell) {
        const widget = page.querySelector('[data-pending-sync]');
        shell.mount(widget);
        shell.refreshReference().then(reference => {
            if (!reference) return;
            const stamp = document.createElement('p');
            stamp.className = 'muted';
            stamp.textContent = shell.t.reference_cached.replace(':at', new Date(reference.last_updated_at).toLocaleString());
            widget.append(stamp);
        });
    }
}
