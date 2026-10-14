// Offline foundation unit tests (Node test runner, no browser). The queue and
// sync manager run against the memory store and a scripted fetch; IndexedDB,
// the service worker and the real DOM are covered by the manual device UAT.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { MemoryStore, STORES } from '../../resources/js/offline/store.js';
import { OfflineQueue, STATUS, BACKOFF_SECONDS } from '../../resources/js/offline/queue.js';
import { SyncManager, ERROR } from '../../resources/js/offline/sync-manager.js';
import { generateUuid, UUID_PATTERN } from '../../resources/js/offline/uuid.js';
import { indicativeGeofence, distanceMeters } from '../../resources/js/offline/reference.js';

const clock = (start = Date.parse('2026-10-15T08:00:00Z')) => { let t = start; return { now: () => t, tick: ms => { t += ms; } }; };
const attendance = (overrides = {}) => ({ type: 'attendance', action: 'check_in', payload: { latitude: 24.7136, longitude: 46.6753, accuracy: 9, site_id: 1, project_id: 1 }, captured_at: '2026-10-15T07:30:00Z', owner_user_id: 7, owner_name: 'Ahmed', ...overrides });
const expense = (overrides = {}) => ({ type: 'site_expense', action: 'create', payload: { expense_date: '2026-10-15', project_id: 1, site_id: 1, expense_category_id: 3, payment_type: 'Cash', payment_account_id: 4, taxable_amount: '100.00', vat_applicable: '1', description: 'Diesel' }, captured_at: '2026-10-15T07:45:00Z', owner_user_id: 7, ...overrides });

const okResponse = results => ({ ok: true, status: 200, json: async () => ({ results }) });
const sessionResponse = (userId = 7) => ({ ok: true, status: 200, json: async () => ({ user_id: userId, csrf_token: 'fresh' }) });
const manager = (queue, fetchImpl, extra = {}) => new SyncManager({ queue, userId: 7, userName: 'Ahmed', endpoints: { attendance: '/sync/attendance', site_expense: '/sync/expenses' }, sessionUrl: '/sync/session', csrfToken: 'tok', fetchImpl, locks: null, lease: null, now: () => Date.now(), ...extra });

test('44/45 queue write and read with a durable, well-formed UUID', async () => {
    const queue = new OfflineQueue(new MemoryStore());
    const item = await queue.enqueue(attendance());
    assert.match(item.client_uuid, UUID_PATTERN);
    assert.match(generateUuid(), UUID_PATTERN);
    assert.notEqual(generateUuid(), generateUuid());
    const stored = await queue.get(item.client_uuid);
    assert.equal(stored.sync_status, STATUS.pending);
    assert.equal(stored.payload.latitude, 24.7136);
    assert.equal(stored.owner_user_id, 7);
    assert.equal(stored.attempt_count, 0);
});

test('46 queue preserves logical order by sequence', async () => {
    const queue = new OfflineQueue(new MemoryStore());
    const first = await queue.enqueue(attendance({ action: 'check_in' }));
    const second = await queue.enqueue(attendance({ action: 'check_out', captured_at: '2026-10-15T16:00:00Z' }));
    const third = await queue.enqueue(expense());
    const list = await queue.list({ owner: 7 });
    assert.deepEqual(list.map(i => i.client_uuid), [first.client_uuid, second.client_uuid, third.client_uuid]);
    assert.ok(list[0].sequence < list[1].sequence && list[1].sequence < list[2].sequence);
});

test('47 retry keeps the same UUID and clears the backoff', async () => {
    const c = clock();
    const queue = new OfflineQueue(new MemoryStore(), { now: c.now });
    const item = await queue.enqueue(attendance());
    await queue.deferred(item.client_uuid, 1, ERROR.NETWORK_ERROR, null);
    let stored = await queue.get(item.client_uuid);
    assert.equal(stored.sync_status, STATUS.pending);
    assert.equal(Date.parse(stored.next_attempt_at), c.now() + BACKOFF_SECONDS[0] * 1000);
    assert.equal((await queue.due(7)).length, 0);
    assert.equal((await queue.due(7, { ignoreBackoff: true })).length, 1);
    await queue.applyResult(item.client_uuid, { status: 'needs_attention', code: 'STALE_REFERENCE', reason: 'category_unavailable', message: 'x' });
    await queue.retry(item.client_uuid);
    stored = await queue.get(item.client_uuid);
    assert.equal(stored.client_uuid, item.client_uuid);
    assert.equal(stored.sync_status, STATUS.pending);
    assert.equal(stored.next_attempt_at, null);
    assert.equal(stored.attempt_count, 2);
});

test('48 items of another signed-in account are never submitted and are reported', async () => {
    const queue = new OfflineQueue(new MemoryStore());
    await queue.enqueue(attendance({ owner_user_id: 9, owner_name: 'Sara' }));
    const mine = await queue.enqueue(attendance());
    const calls = [];
    const fetchImpl = async (url, init) => { calls.push({ url, body: init.body ? JSON.parse(init.body) : null }); return url === '/sync/session' ? sessionResponse() : okResponse([{ client_uuid: mine.client_uuid, status: 'synced', record: { type: 'attendance_record', id: 1 } }]); };
    const m = manager(queue, fetchImpl);
    const summary = await m.sync({ manual: true });
    assert.equal(summary.sent, 1);
    assert.deepEqual(calls.filter(c => c.url === '/sync/attendance')[0].body.items.map(i => i.client_uuid), [mine.client_uuid]);
    assert.deepEqual(m.state.otherOwners, [{ id: 9, name: 'Sara' }]);
    assert.equal((await queue.list({ owner: 9 }))[0].sync_status, STATUS.pending);
});

test('49/50 pending counts and status transitions from server results', async () => {
    const queue = new OfflineQueue(new MemoryStore());
    const a = await queue.enqueue(attendance());
    const b = await queue.enqueue(attendance({ action: 'check_out' }));
    const c = await queue.enqueue(expense());
    const d = await queue.enqueue(expense());
    assert.deepEqual(await queue.counts(7), { pending: 4, syncing: 0, synced: 0, needs_attention: 0, failed: 0, total: 4 });
    await queue.markSyncing([a.client_uuid]);
    assert.equal((await queue.counts(7)).syncing, 1);
    await queue.applyResult(a.client_uuid, { status: 'synced', record: { type: 'attendance_record', id: 5 }, message: 'ok' });
    await queue.applyResult(b.client_uuid, { status: 'needs_attention', code: 'BUSINESS_CONFLICT', reason: 'no_check_in', message: 'wait' });
    await queue.applyResult(c.client_uuid, { status: 'failed', code: 'VALIDATION_ERROR', reason: 'invalid_payload', message: 'bad' });
    await queue.applyResult(d.client_uuid, { status: 'error', code: 'TEMPORARY_SERVER_ERROR', message: 'later' });
    assert.deepEqual(await queue.counts(7), { pending: 1, syncing: 0, synced: 1, needs_attention: 1, failed: 1, total: 4 });
    assert.equal((await queue.get(a.client_uuid)).result.record.id, 5);
    assert.equal((await queue.get(b.client_uuid)).last_error_reason, 'no_check_in');
    assert.ok((await queue.get(d.client_uuid)).next_attempt_at);
    await assert.rejects(queue.discard(a.client_uuid), /Synced records cannot be discarded/);
    assert.equal(await queue.removeLocal(a.client_uuid), true);
    assert.equal(await queue.discard(c.client_uuid), true);
    assert.equal((await queue.counts(7)).total, 2);
});

test('51 the receipt Blob persists with the item and is released after sync', async () => {
    const queue = new OfflineQueue(new MemoryStore());
    const blob = new Blob(['%PDF-1.4 fake'], { type: 'application/pdf' });
    const item = await queue.enqueue(expense({ blob, blob_name: 'receipt.pdf', blob_type: 'application/pdf' }));
    const stored = await queue.get(item.client_uuid);
    assert.ok(stored.blob instanceof Blob);
    assert.equal(await stored.blob.text(), '%PDF-1.4 fake');
    assert.equal(stored.blob_name, 'receipt.pdf');
    await queue.applyResult(item.client_uuid, { status: 'synced', record: { type: 'site_expense', id: 2, reference: 'SE-2026-000001' } });
    assert.equal((await queue.get(item.client_uuid)).blob, null);
});

test('52/53 reconnect triggers a sync and manual Sync now ignores the backoff; expenses travel as multipart', async () => {
    const c = clock();
    const queue = new OfflineQueue(new MemoryStore(), { now: c.now });
    const item = await queue.enqueue(expense({ blob: new Blob(['img'], { type: 'image/jpeg' }), blob_name: 'r.jpg' }));
    await queue.deferred(item.client_uuid, 1, ERROR.NETWORK_ERROR, null);
    const bodies = [];
    const fetchImpl = async (url, init) => { if (url === '/sync/session') return sessionResponse(); bodies.push(init.body); return okResponse([{ client_uuid: item.client_uuid, status: 'synced', record: { type: 'site_expense', id: 1, reference: 'SE-1' } }]); };
    let online = false;
    const m = manager(queue, fetchImpl, { now: c.now, onLine: () => online });
    const listeners = {};
    m.attach({ addEventListener: (name, fn) => { listeners[name] = fn; }, setInterval: () => 0 });
    assert.equal((await m.sync({ reason: 'timer' })).skipped, 'nothing'); // backoff not elapsed
    online = true;
    c.tick(BACKOFF_SECONDS[0] * 1000 + 1);
    listeners.online();
    await new Promise(r => setTimeout(r, 10));
    assert.equal(bodies.length, 1);
    assert.ok(bodies[0] instanceof FormData);
    assert.equal(bodies[0].get('items[0][client_uuid]'), item.client_uuid);
    assert.equal(bodies[0].get('items[0][payload][taxable_amount]'), '100.00');
    assert.ok(bodies[0].get('items[0][receipt]') instanceof Blob);
    assert.equal((await queue.get(item.client_uuid)).sync_status, STATUS.synced);

    const again = await queue.enqueue(expense());
    await queue.deferred(again.client_uuid, 3, ERROR.TEMPORARY_SERVER_ERROR, null);
    assert.equal((await m.sync({ reason: 'timer' })).skipped, 'nothing');
    bodies.length = 0;
    const summary = await m.sync({ manual: true });
    assert.equal(summary.sent, 1);
});

test('54 an expired session keeps the queue and asks for sign-in; a different signed-in user blocks submission', async () => {
    const queue = new OfflineQueue(new MemoryStore());
    const item = await queue.enqueue(attendance());
    let sessionStatus = 401;
    const sent = [];
    const fetchImpl = async (url) => { if (url === '/sync/session') return sessionStatus === 401 ? { ok: false, status: 401 } : sessionResponse(sessionStatus); sent.push(url); return okResponse([]); };
    const m = manager(queue, fetchImpl);
    let summary = await m.sync({ manual: true });
    assert.equal(summary.skipped, 'auth');
    assert.equal(m.state.authRequired, true);
    assert.equal(sent.length, 0);
    assert.equal((await queue.get(item.client_uuid)).sync_status, STATUS.pending);
    sessionStatus = 9;
    summary = await m.sync({ manual: true });
    assert.equal(summary.skipped, 'other-user');
    assert.equal(sent.length, 0);
    // A 419 on the batch itself releases the items back to pending without counting an attempt.
    sessionStatus = 7;
    const fetch419 = async url => (url === '/sync/session' ? sessionResponse() : { ok: false, status: 419, json: async () => ({ message: 'CSRF token mismatch' }) });
    const m2 = manager(queue, fetch419);
    summary = await m2.sync({ manual: true });
    assert.equal(summary.authRequired, true);
    const stored = await queue.get(item.client_uuid);
    assert.equal(stored.sync_status, STATUS.pending);
    assert.equal(stored.attempt_count, 0);
});

test('55 Clear offline data is refused while unsynced records remain and clears everything once forced', async () => {
    const store = new MemoryStore();
    const queue = new OfflineQueue(store);
    const item = await queue.enqueue(attendance());
    await store.put(STORES.meta, { key: 'reference', data: {} });
    await assert.rejects(queue.clearAll(), /unsynced/);
    assert.equal((await queue.counts(7)).total, 1);
    await queue.applyResult(item.client_uuid, { status: 'synced', record: { id: 1 } });
    await queue.clearAll();
    assert.equal((await queue.counts(7)).total, 0);
    assert.equal(await store.get(STORES.meta, 'reference'), null);
    await queue.enqueue(attendance({ owner_user_id: 9 }));
    await assert.rejects(queue.clearAll(), /unsynced/);
    await queue.clearAll({ force: true });
    assert.equal((await store.all(STORES.queue)).length, 0);
});

test('56 two tabs cannot run the same queue at once (Web Locks and the lease fallback)', async () => {
    const queue = new OfflineQueue(new MemoryStore());
    const item = await queue.enqueue(attendance());
    let release;
    const slowFetch = async url => { if (url === '/sync/session') return sessionResponse(); await new Promise(r => { release = r; }); return okResponse([{ client_uuid: item.client_uuid, status: 'synced', record: { id: 1 } }]); };
    let held = false;
    const locks = { request: async (name, options, cb) => { if (held) return cb(null); held = true; try { return await cb({ name }); } finally { held = false; } } };
    const first = manager(queue, slowFetch, { locks });
    const second = manager(queue, slowFetch, { locks });
    const running = first.sync({ manual: true });
    await new Promise(r => setTimeout(r, 5));
    assert.deepEqual(await second.sync({ manual: true }), { skipped: 'locked' });
    release();
    assert.equal((await running).synced, 1);

    // Lease fallback (no Web Locks): a fresh lease blocks, a stale one is taken over.
    const lease = new Map();
    const leaseStore = { getItem: k => lease.get(k) ?? null, setItem: (k, v) => lease.set(k, v), removeItem: k => lease.delete(k) };
    const other = await queue.enqueue(attendance({ action: 'check_out' }));
    const quickFetch = async url => (url === '/sync/session' ? sessionResponse() : okResponse([{ client_uuid: other.client_uuid, status: 'synced', record: { id: 2 } }]));
    leaseStore.setItem('seera-offline-sync', String(Date.now()));
    assert.deepEqual(await manager(queue, quickFetch, { lease: leaseStore }).sync({ manual: true }), { skipped: 'locked' });
    leaseStore.setItem('seera-offline-sync', String(Date.now() - 60000));
    assert.equal((await manager(queue, quickFetch, { lease: leaseStore }).sync({ manual: true })).synced, 1);
    assert.equal(leaseStore.getItem('seera-offline-sync'), null);
});

test('server error categories map to retry behaviour: 5xx defers with backoff, 403 blocks, 422 fails, network defers', async () => {
    const queue = new OfflineQueue(new MemoryStore());
    const item = await queue.enqueue(attendance());
    const withStatus = status => async url => (url === '/sync/session' ? sessionResponse() : { ok: false, status, json: async () => ({ message: 'm' }) });
    await manager(queue, withStatus(503)).sync({ manual: true });
    let s = await queue.get(item.client_uuid);
    assert.equal(s.sync_status, STATUS.pending); assert.equal(s.last_error_code, ERROR.TEMPORARY_SERVER_ERROR); assert.ok(s.next_attempt_at);
    await manager(queue, async url => { if (url === '/sync/session') return sessionResponse(); throw new TypeError('Failed to fetch'); }).sync({ manual: true });
    s = await queue.get(item.client_uuid);
    assert.equal(s.last_error_code, ERROR.NETWORK_ERROR); assert.equal(s.attempt_count, 2);
    await manager(queue, withStatus(403)).sync({ manual: true });
    s = await queue.get(item.client_uuid);
    assert.equal(s.sync_status, STATUS.needsAttention); assert.equal(s.last_error_code, ERROR.PERMISSION_DENIED);
    await queue.retry(item.client_uuid);
    await manager(queue, withStatus(422)).sync({ manual: true });
    s = await queue.get(item.client_uuid);
    assert.equal(s.sync_status, STATUS.failed); assert.equal(s.last_error_code, ERROR.VALIDATION_ERROR);
});

test('indicative geofence mirrors the server formula and is advisory', () => {
    assert.ok(Math.abs(distanceMeters(24.7136, 46.6753, 24.7136, 46.67629) - 100) < 5);
    const site = { latitude: 24.7136, longitude: 46.6753, geofence_radius: 300, geofence_enabled: true, attendance_inside_only: true };
    assert.equal(indicativeGeofence(site, 24.7136, 46.67629).status, 'inside');
    assert.equal(indicativeGeofence(site, 24.7136, 46.68518).blocked, true);
    assert.equal(indicativeGeofence({ ...site, geofence_enabled: false }, 24.7136, 46.68518).status, 'not_enforced');
    assert.equal(indicativeGeofence(null, 1, 1).status, 'unknown');
});
