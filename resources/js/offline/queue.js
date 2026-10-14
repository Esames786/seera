// The device queue of offline actions. Every item carries a durable client
// UUID, the originating signed-in user, a monotonic sequence (logical order),
// a status and the server's last structured answer. Items are never
// auto-submitted for a different user, and a discard is only possible before
// the server accepted the item.
import { STORES } from './store.js';
import { generateUuid } from './uuid.js';

export const STATUS = { pending: 'pending', syncing: 'syncing', synced: 'synced', needsAttention: 'needs_attention', failed: 'failed' };
export const TYPES = { attendance: 'attendance', siteExpense: 'site_expense' };
export const BACKOFF_SECONDS = [30, 60, 120, 300, 900];
export const HISTORY_LIMIT = 50;

export class OfflineQueue {
    constructor(store, { now = () => Date.now() } = {}) { this.store = store; this.now = now; }

    async nextSequence() {
        const meta = (await this.store.get(STORES.meta, 'sequence')) || { key: 'sequence', value: 0 };
        meta.value += 1;
        await this.store.put(STORES.meta, meta);
        return meta.value;
    }

    /** Queue one action. Returns the stored item (with its UUID). */
    async enqueue({ type, action, payload, captured_at, owner_user_id, owner_name = null, label = null, blob = null, blob_name = null, blob_type = null, context = null }) {
        if (!Object.values(TYPES).includes(type)) throw new Error(`Unknown offline type: ${type}`);
        if (!owner_user_id) throw new Error('An offline item needs its signed-in owner.');
        const item = {
            client_uuid: generateUuid(), type, action, payload, captured_at,
            created_at_client: new Date(this.now()).toISOString(), sequence: await this.nextSequence(),
            owner_user_id, owner_name, label, context,
            sync_status: STATUS.pending, attempt_count: 0, last_attempt_at: null, next_attempt_at: null,
            last_error_code: null, last_error_reason: null, last_error_message: null, result: null, synced_at: null,
            blob, blob_name, blob_type,
        };
        await this.store.put(STORES.queue, item);
        return item;
    }

    async get(uuid) { return this.store.get(STORES.queue, uuid); }

    /** Items in logical order, optionally filtered by owner, status or type. */
    async list({ owner = null, status = null, type = null } = {}) {
        const items = await this.store.all(STORES.queue);
        return items
            .filter(i => (owner === null || i.owner_user_id === owner) && (status === null || i.sync_status === status) && (type === null || i.type === type))
            .sort((a, b) => a.sequence - b.sequence);
    }

    async update(uuid, patch) {
        const item = await this.get(uuid);
        if (!item) return null;
        const next = { ...item, ...patch };
        await this.store.put(STORES.queue, next);
        return next;
    }

    async counts(owner) {
        const items = await this.list({ owner });
        const counts = { pending: 0, syncing: 0, synced: 0, needs_attention: 0, failed: 0, total: items.length };
        for (const item of items) counts[item.sync_status] = (counts[item.sync_status] || 0) + 1;
        return counts;
    }

    /** Owners other than the given one that still hold unsynced items on this device. */
    async otherOwnersWithUnsynced(owner) {
        const items = await this.store.all(STORES.queue);
        const owners = new Map();
        for (const i of items) if (i.owner_user_id !== owner && i.sync_status !== STATUS.synced) owners.set(i.owner_user_id, i.owner_name);
        return [...owners.entries()].map(([id, name]) => ({ id, name }));
    }

    /** Items ready to send now: pending (or syncing left over from an interrupted run) whose backoff has elapsed. */
    async due(owner, { ignoreBackoff = false, type = null } = {}) {
        const now = this.now();
        const items = await this.list({ owner, type });
        return items.filter(i => [STATUS.pending, STATUS.syncing].includes(i.sync_status) && (ignoreBackoff || !i.next_attempt_at || Date.parse(i.next_attempt_at) <= now));
    }

    async markSyncing(uuids) {
        const at = new Date(this.now()).toISOString();
        for (const uuid of uuids) await this.update(uuid, { sync_status: STATUS.syncing, last_attempt_at: at });
    }

    /** Apply one server result. `error` (temporary) keeps the item pending with backoff; the UUID never changes. */
    async applyResult(uuid, result) {
        const item = await this.get(uuid);
        if (!item) return null;
        const attempts = item.attempt_count + 1;
        if (result.status === STATUS.synced) {
            // The blob has done its job; free the device storage but keep the record of what happened.
            return this.update(uuid, { sync_status: STATUS.synced, attempt_count: attempts, synced_at: new Date(this.now()).toISOString(), result: { record: result.record || null, message: result.message || null, replay: !!(result.data && result.data.replay) },
                last_error_code: null, last_error_reason: null, last_error_message: null, next_attempt_at: null, blob: null });
        }
        if (result.status === STATUS.needsAttention || result.status === STATUS.failed) {
            return this.update(uuid, { sync_status: result.status, attempt_count: attempts, last_error_code: result.code || null, last_error_reason: result.reason || null,
                last_error_message: result.message || null, result: { record: result.record || null, data: result.data || null }, next_attempt_at: null });
        }
        return this.deferred(uuid, attempts, result.code || 'TEMPORARY_SERVER_ERROR', result.message || null);
    }

    /** Keep an item pending and schedule its next attempt by backoff. */
    async deferred(uuid, attempts, code, message) {
        const delay = BACKOFF_SECONDS[Math.min(attempts, BACKOFF_SECONDS.length) - 1] || BACKOFF_SECONDS[0];
        return this.update(uuid, { sync_status: STATUS.pending, attempt_count: attempts, last_error_code: code, last_error_message: message,
            next_attempt_at: new Date(this.now() + delay * 1000).toISOString() });
    }

    /** Put an item back to pending without counting an attempt (for example after a blocked lock or sign-in). */
    async release(uuids) {
        for (const uuid of uuids) {
            const item = await this.get(uuid);
            if (item && item.sync_status === STATUS.syncing) await this.update(uuid, { sync_status: STATUS.pending });
        }
    }

    /** Manual retry: clears backoff; needs-attention / failed items go back to pending. The UUID is kept. */
    async retry(uuid) {
        const item = await this.get(uuid);
        if (!item || item.sync_status === STATUS.synced) return null;
        return this.update(uuid, { sync_status: STATUS.pending, next_attempt_at: null });
    }

    /** Discard is only possible while the server has not accepted the item. */
    async discard(uuid) {
        const item = await this.get(uuid);
        if (!item) return false;
        if (item.sync_status === STATUS.synced) throw new Error('Synced records cannot be discarded; remove the local copy instead.');
        await this.store.delete(STORES.queue, uuid);
        return true;
    }

    /** Remove the device copy of a synced item. The server record is untouched. */
    async removeLocal(uuid) {
        const item = await this.get(uuid);
        if (!item || item.sync_status !== STATUS.synced) return false;
        await this.store.delete(STORES.queue, uuid);
        return true;
    }

    /** Keep the local history bounded: oldest synced items beyond the limit are dropped. */
    async trimHistory(limit = HISTORY_LIMIT) {
        const synced = (await this.list({ status: STATUS.synced })).sort((a, b) => b.sequence - a.sequence);
        for (const item of synced.slice(limit)) await this.store.delete(STORES.queue, item.client_uuid);
    }

    async hasUnsynced(owner = null) {
        const items = await this.list(owner === null ? {} : { owner });
        return items.some(i => i.sync_status !== STATUS.synced);
    }

    /**
     * Clear everything on the device (queue and cached reference). Refused while
     * unsynced records of any account remain unless the caller forces it after an
     * explicit, typed confirmation; callers confirm first in every case.
     */
    async clearAll({ force = false } = {}) {
        if (!force && await this.hasUnsynced(null)) throw new Error('unsynced');
        await this.store.clear(STORES.queue);
        await this.store.clear(STORES.meta);
    }
}
