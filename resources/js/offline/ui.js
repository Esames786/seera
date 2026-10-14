// The reusable Pending Sync widget: network state, counts, Sync now, the
// device queue with Retry / Discard / Remove, sign-in and other-account
// notices and Clear offline data. Mounted on the mobile attendance page,
// the mobile site expense form and the Mobile Sync page.
import { STATUS } from './queue.js';

const el = (tag, attrs = {}, children = []) => {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'class') node.className = v;
        else if (k === 'text') node.textContent = v;
        else if (k === 'hidden') node.hidden = !!v;
        else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
        else node.setAttribute(k, v);
    }
    for (const child of [].concat(children)) if (child) node.append(child);
    return node;
};

const badgeClass = status => ({ pending: 'yellow', syncing: 'blue', synced: 'green', needs_attention: 'red', failed: 'gray' })[status] || 'gray';

export function mountPendingSync(container, { queue, manager, t, userId, loginUrl, clearPages = () => {}, compact = false }) {
    if (!container) return null;
    container.classList.add('pending-sync');
    container.hidden = false;
    const status = el('div', { class: 'pending-sync-status', role: 'status', 'aria-live': 'polite' });
    const notice = el('div', { class: 'alert', role: 'alert', hidden: true });
    const list = el('div', { class: 'pending-sync-list' });
    const syncButton = el('button', { type: 'button', class: 'btn primary', text: t.sync_now, 'data-sync-now': '', onclick: async () => { syncButton.disabled = true; try { await manager.sync({ manual: true }); } finally { syncButton.disabled = false; await render(); } } });
    const clearWrap = el('div', { class: 'pending-sync-clear', hidden: compact });
    const clearInput = el('input', { type: 'text', class: 'input', placeholder: 'CLEAR', 'aria-label': t.clear_offline_data, style: 'max-width:140px' });
    const clearButton = el('button', { type: 'button', class: 'btn danger', text: t.clear_offline_data, 'data-clear-offline': '', onclick: async () => {
        if (clearInput.value.trim() !== 'CLEAR') { showNotice(t.clear_offline_data_confirm); return; }
        try { await queue.clearAll(); } catch { showNotice(t.clear_offline_data_blocked); return; }
        clearPages(); clearInput.value = ''; showNotice(t.no_items); await render();
    } });
    clearWrap.append(el('p', { class: 'muted', text: t.clear_offline_data_confirm }), el('div', { class: 'pending-sync-actions' }, [clearInput, clearButton]));
    container.replaceChildren(el('div', { class: 'pending-sync-head' }, [el('h3', { text: t.pending_sync }), syncButton]), status, notice, list, clearWrap);

    const showNotice = message => { notice.textContent = message; notice.hidden = !message; };
    const fmt = iso => { if (!iso) return '-'; const d = new Date(iso); return isNaN(d) ? iso : d.toLocaleString(); };

    const row = item => {
        const actions = [];
        if (item.sync_status !== STATUS.synced && item.sync_status !== STATUS.syncing) {
            actions.push(el('button', { type: 'button', class: 'btn sm outline', text: t.retry, 'data-retry': item.client_uuid, onclick: async () => { await queue.retry(item.client_uuid); await manager.sync({ manual: true }); await render(); } }));
            actions.push(el('button', { type: 'button', class: 'btn sm danger', text: t.discard, 'data-discard': item.client_uuid, onclick: async () => {
                if (row.dataset.confirm !== '1') { row.dataset.confirm = '1'; showNotice(t.discard_confirm); return; }
                await queue.discard(item.client_uuid); showNotice(''); await render();
            } }));
        }
        if (item.sync_status === STATUS.synced) {
            actions.push(el('button', { type: 'button', class: 'btn sm outline', text: t.remove_local, title: t.remove_local_note, 'data-remove-local': item.client_uuid, onclick: async () => { await queue.removeLocal(item.client_uuid); await render(); } }));
            if (item.result && item.result.record && item.result.record.url) actions.push(el('a', { class: 'btn sm outline', href: item.result.record.url, text: item.result.record.reference || '#' }));
        }
        const label = t['operation_' + item.type + '_' + item.action] || item.label || `${item.type} ${item.action}`;
        const details = [
            `${t.captured_at}: ${fmt(item.captured_at)}`,
            item.result && item.result.message ? item.result.message : null,
            item.last_error_message ? `${t.last_error}: ${item.last_error_message}` : null,
            item.attempt_count ? `${t.attempts}: ${item.attempt_count}` : null,
            item.next_attempt_at && item.sync_status === STATUS.pending ? `${t.retry}: ${fmt(item.next_attempt_at)}` : null,
        ].filter(Boolean);
        const row = el('div', { class: 'pending-sync-item', 'data-sync-item': item.client_uuid, 'data-sync-status': item.sync_status }, [
            el('div', { class: 'pending-sync-item-head' }, [el('strong', { text: label }), el('span', { class: 'badge ' + badgeClass(item.sync_status), text: t['status_' + item.sync_status] || item.sync_status })]),
            el('div', { class: 'muted small' }, details.map(d => el('div', { text: d }))),
            el('div', { class: 'pending-sync-actions' }, actions),
        ]);
        return row;
    };

    async function render(state = manager.state) {
        const counts = state.counts || await queue.counts(userId);
        const parts = [state.online ? t.network_online : t.network_offline];
        if (state.syncing) parts.push(t.status_syncing);
        parts.push(`${t.status_pending}: ${counts.pending + counts.syncing}`);
        if (counts.needs_attention) parts.push(`${t.status_needs_attention}: ${counts.needs_attention}`);
        if (counts.failed) parts.push(`${t.status_failed}: ${counts.failed}`);
        if (counts.synced) parts.push(`${t.status_synced}: ${counts.synced}`);
        status.textContent = parts.join(' · ');
        container.dataset.pending = String(counts.pending + counts.syncing);
        container.dataset.online = state.online ? '1' : '0';
        if (state.authRequired) {
            notice.replaceChildren(document.createTextNode(t.sign_in_to_continue + ' '), loginUrl ? el('a', { href: loginUrl, text: t.sign_in_to_continue.split('.')[0] }) : null);
            notice.hidden = false;
        } else if (state.otherOwners && state.otherOwners.length) {
            showNotice(t.other_account);
        } else if (state.lastCode === 'NETWORK_ERROR') {
            showNotice(t.network_offline);
        }
        const items = await queue.list({ owner: userId });
        list.replaceChildren(...(items.length ? items.slice().reverse().map(row) : [el('p', { class: 'muted', text: t.no_items })]));
    }

    const unsubscribe = manager.subscribe(state => { render(state); });
    return { render, unsubscribe, showNotice };
}
