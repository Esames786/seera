// Sends the device queue to the authenticated sync endpoints in bounded
// batches and applies per-item results. navigator.onLine is advisory only:
// the request outcome is what decides. One run at a time per device (Web
// Locks, with a lease fallback); the server receipt is the final protection.
import { STATUS, TYPES } from './queue.js';

export const ERROR = {
    AUTH_REQUIRED: 'AUTH_REQUIRED', PERMISSION_DENIED: 'PERMISSION_DENIED', VALIDATION_ERROR: 'VALIDATION_ERROR', BUSINESS_CONFLICT: 'BUSINESS_CONFLICT',
    STALE_REFERENCE: 'STALE_REFERENCE', DUPLICATE: 'DUPLICATE', TEMPORARY_SERVER_ERROR: 'TEMPORARY_SERVER_ERROR', NETWORK_ERROR: 'NETWORK_ERROR',
};
export const LOCK_NAME = 'seera-offline-sync';
const LEASE_MS = 20000;

export class SyncManager {
    constructor({ queue, userId, userName = null, endpoints, sessionUrl, csrfToken, fetchImpl = globalThis.fetch?.bind(globalThis), now = () => Date.now(),
        batchSizes = { attendance: 20, site_expense: 5 }, locks = globalThis.navigator?.locks, lease = null, onLine = () => globalThis.navigator?.onLine !== false }) {
        this.queue = queue; this.userId = userId; this.userName = userName; this.endpoints = endpoints; this.sessionUrl = sessionUrl;
        this.csrfToken = csrfToken; this.fetch = fetchImpl; this.now = now; this.batchSizes = batchSizes; this.locks = locks; this.lease = lease; this.onLine = onLine;
        this.listeners = new Set();
        this.state = { online: this.onLine(), syncing: false, authRequired: false, otherOwners: [], lastSyncAt: null, lastMessage: null, lastCode: null, counts: null };
        this.running = false;
    }

    subscribe(fn) { this.listeners.add(fn); fn(this.state); return () => this.listeners.delete(fn); }

    async refresh(patch = {}) {
        this.state = { ...this.state, ...patch, online: this.onLine(), counts: await this.queue.counts(this.userId), otherOwners: await this.queue.otherOwnersWithUnsynced(this.userId) };
        for (const fn of this.listeners) fn(this.state);
        return this.state;
    }

    /** Browser hooks: reconnect, tab becomes visible, periodic tick. Manual Sync now is always available. */
    attach(target = globalThis) {
        if (!target || !target.addEventListener) return;
        target.addEventListener('online', () => { this.refresh(); this.sync({ reason: 'online' }); });
        target.addEventListener('offline', () => this.refresh());
        if (target.document) target.document.addEventListener('visibilitychange', () => { if (target.document.visibilityState === 'visible') this.sync({ reason: 'visible' }); });
        this.timer = target.setInterval(() => this.sync({ reason: 'timer' }), 60000);
    }

    headers(json = true) {
        const h = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrfToken };
        if (json) h['Content-Type'] = 'application/json';
        return h;
    }

    /** Ask the server who is signed in; refreshes the CSRF token. Returns the user id, or null when sign-in is required. */
    async session() {
        try {
            const response = await this.fetch(this.sessionUrl, { headers: this.headers(false), credentials: 'same-origin', cache: 'no-store' });
            if (response.status === 401 || response.status === 419) return null;
            if (!response.ok) throw new Error(`session ${response.status}`);
            const data = await response.json();
            if (data.csrf_token) this.csrfToken = data.csrf_token;
            return data.user_id ?? null;
        } catch (e) {
            throw Object.assign(new Error('network'), { code: ERROR.NETWORK_ERROR, cause: e });
        }
    }

    async withLock(work) {
        if (this.locks && typeof this.locks.request === 'function') {
            return this.locks.request(LOCK_NAME, { ifAvailable: true }, async lock => (lock ? work() : { skipped: 'locked' }));
        }
        // Fallback lease: a timestamp in shared storage; stale leases (crashed tab) expire.
        const store = this.lease;
        const held = store ? Number(store.getItem(LOCK_NAME) || 0) : 0;
        if (held && this.now() - held < LEASE_MS) return { skipped: 'locked' };
        if (store) store.setItem(LOCK_NAME, String(this.now()));
        try { return await work(); } finally { if (store) store.removeItem(LOCK_NAME); }
    }

    /**
     * One synchronisation run. Returns a summary. `manual` ignores backoff.
     * Items of another signed-in account are never sent.
     */
    async sync({ manual = false, reason = 'manual' } = {}) {
        if (this.running) return { skipped: 'running' };
        this.running = true;
        try {
            return await this.withLock(() => this.run({ manual, reason }));
        } finally {
            this.running = false;
        }
    }

    async run({ manual }) {
        const summary = { sent: 0, synced: 0, needs_attention: 0, failed: 0, deferred: 0, authRequired: false, skipped: null };
        const due = await this.queue.due(this.userId, { ignoreBackoff: manual });
        if (due.length === 0) { await this.refresh({ syncing: false }); return { ...summary, skipped: 'nothing' }; }
        if (!this.onLine() && !manual) { await this.refresh({ syncing: false }); return { ...summary, skipped: 'offline' }; }

        await this.refresh({ syncing: true, lastMessage: null, lastCode: null });
        let sessionUser;
        try {
            sessionUser = await this.session();
        } catch (e) {
            await this.refresh({ syncing: false, lastCode: ERROR.NETWORK_ERROR });
            return { ...summary, skipped: 'network' };
        }
        if (sessionUser === null) {
            await this.refresh({ syncing: false, authRequired: true, lastCode: ERROR.AUTH_REQUIRED });
            return { ...summary, authRequired: true, skipped: 'auth' };
        }
        if (sessionUser !== this.userId) {
            // The device signed into another account since this page was built: never submit under it.
            await this.refresh({ syncing: false, authRequired: true, lastCode: ERROR.AUTH_REQUIRED });
            return { ...summary, authRequired: true, skipped: 'other-user' };
        }

        for (const type of [TYPES.attendance, TYPES.siteExpense]) {
            const items = due.filter(i => i.type === type);
            const size = this.batchSizes[type] || 10;
            for (let start = 0; start < items.length; start += size) {
                const batch = items.slice(start, start + size);
                const outcome = await this.send(type, batch);
                summary.sent += batch.length;
                for (const key of ['synced', 'needs_attention', 'failed', 'deferred']) summary[key] += outcome[key] || 0;
                if (outcome.stop) {
                    if (outcome.authRequired) summary.authRequired = true;
                    await this.refresh({ syncing: false, authRequired: !!outcome.authRequired, lastCode: outcome.code || null, lastMessage: outcome.message || null });
                    return { ...summary, skipped: outcome.code };
                }
            }
        }
        await this.queue.trimHistory();
        await this.refresh({ syncing: false, authRequired: false, lastSyncAt: new Date(this.now()).toISOString() });
        return summary;
    }

    /** Send one batch. Per-item results update the queue; envelope-level failures decide whether to stop. */
    async send(type, batch) {
        const uuids = batch.map(i => i.client_uuid);
        await this.queue.markSyncing(uuids);
        let response;
        try {
            response = await this.fetch(this.endpoints[type], this.request(type, batch));
        } catch (e) {
            for (const item of batch) await this.queue.deferred(item.client_uuid, item.attempt_count + 1, ERROR.NETWORK_ERROR, null);
            return { deferred: batch.length, stop: true, code: ERROR.NETWORK_ERROR };
        }
        if (response.status === 401 || response.status === 419) {
            await this.queue.release(uuids);
            return { stop: true, authRequired: true, code: ERROR.AUTH_REQUIRED };
        }
        if (response.status === 403) {
            // Blocked until an administrator corrects the permission; nothing is retried automatically.
            for (const item of batch) await this.queue.applyResult(item.client_uuid, { status: STATUS.needsAttention, code: ERROR.PERMISSION_DENIED, reason: 'permission_denied', message: await this.message(response) });
            return { needs_attention: batch.length, stop: true, code: ERROR.PERMISSION_DENIED };
        }
        if (response.status === 422 || response.status === 413) {
            const message = await this.message(response);
            for (const item of batch) await this.queue.applyResult(item.client_uuid, { status: STATUS.failed, code: ERROR.VALIDATION_ERROR, reason: 'invalid_envelope', message });
            return { failed: batch.length };
        }
        if (!response.ok) {
            for (const item of batch) await this.queue.deferred(item.client_uuid, item.attempt_count + 1, ERROR.TEMPORARY_SERVER_ERROR, `HTTP ${response.status}`);
            return { deferred: batch.length, stop: response.status === 429 || response.status >= 500, code: ERROR.TEMPORARY_SERVER_ERROR };
        }
        let data;
        try { data = await response.json(); } catch { data = null; }
        const results = new Map((data && data.results || []).map(r => [r.client_uuid, r]));
        const outcome = { synced: 0, needs_attention: 0, failed: 0, deferred: 0 };
        for (const item of batch) {
            const result = results.get(item.client_uuid);
            if (!result) { await this.queue.deferred(item.client_uuid, item.attempt_count + 1, ERROR.TEMPORARY_SERVER_ERROR, 'no result'); outcome.deferred++; continue; }
            const updated = await this.queue.applyResult(item.client_uuid, result);
            if (updated.sync_status === STATUS.synced) outcome.synced++;
            else if (updated.sync_status === STATUS.needsAttention) outcome.needs_attention++;
            else if (updated.sync_status === STATUS.failed) outcome.failed++;
            else outcome.deferred++;
        }
        return outcome;
    }

    /** Attendance travels as JSON; site expenses as multipart so the receipt Blob rides with its item. */
    request(type, batch) {
        const deviceTime = new Date(this.now()).toISOString();
        if (type === TYPES.siteExpense) {
            const form = new FormData();
            form.append('device_time', deviceTime);
            batch.forEach((item, i) => {
                form.append(`items[${i}][client_uuid]`, item.client_uuid);
                form.append(`items[${i}][action]`, item.action);
                form.append(`items[${i}][captured_at]`, item.captured_at);
                for (const [key, value] of Object.entries(item.payload)) if (value !== null && value !== undefined) form.append(`items[${i}][payload][${key}]`, String(value));
                if (item.blob) form.append(`items[${i}][receipt]`, item.blob, item.blob_name || 'receipt');
            });
            return { method: 'POST', headers: this.headers(false), credentials: 'same-origin', body: form };
        }
        const body = { device_time: deviceTime, items: batch.map(item => ({ client_uuid: item.client_uuid, action: item.action, captured_at: item.captured_at, payload: item.payload })) };
        return { method: 'POST', headers: this.headers(true), credentials: 'same-origin', body: JSON.stringify(body) };
    }

    async message(response) {
        try { const data = await response.json(); return data.message || (data.errors && Object.values(data.errors).flat().join(' ')) || `HTTP ${response.status}`; } catch { return `HTTP ${response.status}`; }
    }
}
