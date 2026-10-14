// Location-validated mobile attendance.
// Online: the browser asks for a position only when the user presses Check in /
// Check out, sends it to the server for evaluation, shows the server's verdict,
// then confirms. The server computes distance and status and records its own
// time; nothing here is trusted for policy. No continuous tracking.
// Offline (Phase 1): when the server cannot be reached, the captured position
// and time are queued on the device under a durable UUID with the site the
// page displayed; the page shows an indicative verdict only. The server
// re-validates everything when the queue syncs.
import { offlineShell } from './offline/shell.js';
import { indicativeGeofence } from './offline/reference.js';

const root = document.querySelector('[data-mobile-attendance]');
if (root && root.dataset.locateUrl) {
    const t = JSON.parse(root.dataset.i18n || '{}');
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': root.dataset.token };
    const status = root.querySelector('[data-status]');
    const result = root.querySelector('[data-result]');
    const error = root.querySelector('[data-error]');
    const locateButton = root.querySelector('[data-action="locate"]');
    const confirmButton = root.querySelector('[data-action="confirm"]');
    const queueButton = root.querySelector('[data-action="queue"]');
    const cancelButton = root.querySelector('[data-action="cancel"]');
    const stateLabel = root.querySelector('[data-state-label]');
    const offlineNote = root.querySelector('[data-offline-note]');
    const shell = offlineShell();
    const ot = shell ? shell.t : {};
    let position = null;
    let busy = false;
    let offline = false;

    const say = message => { if (status) status.textContent = message || ''; };
    const fail = message => { error.textContent = message; error.hidden = false; };
    const clearError = () => { error.hidden = true; error.textContent = ''; };
    const setBusy = value => {
        busy = value;
        [locateButton, confirmButton, queueButton, cancelButton].forEach(button => { if (button) button.disabled = value; });
    };
    const reset = () => {
        position = null;
        offline = false;
        result.hidden = true;
        confirmButton.hidden = true;
        if (queueButton) queueButton.hidden = true;
        cancelButton.hidden = true;
        locateButton.hidden = false;
        say('');
    };

    const locationError = code => {
        if (code === 1) return t.location_denied;
        if (code === 3) return t.location_timeout;
        return t.location_unavailable_device;
    };
    const getPosition = () => new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) { reject(new Error(t.location_unsupported)); return; }
        navigator.geolocation.getCurrentPosition(
            p => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy, captured_at: new Date(p.timestamp).toISOString() }),
            e => reject(new Error(locationError(e.code))),
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
        );
    });
    const NetworkError = class extends Error {};
    const post = async (url, body) => {
        let response;
        try {
            response = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body), credentials: 'same-origin' });
        } catch (e) {
            throw new NetworkError(t.request_failed);
        }
        let data = null;
        try { data = await response.json(); } catch { data = null; }
        if (!response.ok) {
            const message = data && (data.message || (data.errors && Object.values(data.errors).flat().join(' ')));
            throw new Error(message || t.request_failed);
        }
        return data;
    };
    const show = (selector, text) => { const node = root.querySelector(selector); if (node) node.textContent = text; };
    const step = () => (root.dataset.state === 'can_check_in' ? 'check_in' : 'check_out');
    const labelFor = status => ({ inside: t.inside, outside: t.outside, not_enforced: t.not_enforced, location_unavailable: t.location_unavailable }[status] || status);

    // Pending Sync widget and cached site context (refreshed whenever the page loads online).
    if (shell) {
        const widget = root.querySelector('[data-pending-sync]');
        if (widget) shell.mount(widget, { compact: true });
        shell.refreshReference();
    }

    /** Offline branch: show the indicative verdict from the cached site policy and offer to queue the action. */
    const offerOffline = async () => {
        const reference = shell ? await shell.reference() : null;
        const site = reference && reference.data && reference.data.attendance ? reference.data.attendance.site : null;
        const verdict = indicativeGeofence(site, position.latitude, position.longitude);
        offline = true;
        say(ot.offline_mode || t.request_failed);
        show('[data-result-accuracy]', position.accuracy !== null ? `${Math.round(position.accuracy)} m` : '-');
        show('[data-result-distance]', verdict.distance !== null ? `${Math.round(verdict.distance)} m` : '-');
        show('[data-result-radius]', site && site.geofence_enabled ? `${site.geofence_radius} m` : t.not_enforced);
        show('[data-result-status]', `${labelFor(verdict.status)} (${ot.indicative || ''})`);
        result.hidden = false;
        locateButton.hidden = true;
        confirmButton.hidden = true;
        cancelButton.hidden = false;
        if (queueButton) {
            queueButton.textContent = step() === 'check_in' ? ot.queue_check_in : ot.queue_check_out;
            queueButton.hidden = false;
        }
        if (offlineNote) { offlineNote.textContent = ot.offline_mode || ''; offlineNote.hidden = false; }
    };

    locateButton?.addEventListener('click', async () => {
        if (busy) return;
        clearError();
        setBusy(true);
        say(t.getting_location);
        try {
            position = await getPosition();
            if (shell && navigator.onLine === false) { await offerOffline(); return; }
            const verdict = await post(root.dataset.locateUrl, position);
            say(t.location_ready);
            show('[data-result-accuracy]', verdict.accuracy_meters !== null ? `${Math.round(verdict.accuracy_meters)} m` : '-');
            show('[data-result-distance]', verdict.distance_meters !== null ? `${Math.round(verdict.distance_meters)} m` : '-');
            show('[data-result-radius]', verdict.enforced ? `${verdict.radius_meters} m` : verdict.status_label);
            show('[data-result-status]', verdict.status_label);
            result.hidden = false;
            if (verdict.blocked) {
                fail(verdict.reason || t.request_failed);
                position = null;
                cancelButton.hidden = false;
                locateButton.hidden = true;
            } else {
                locateButton.hidden = true;
                confirmButton.hidden = false;
                cancelButton.hidden = false;
            }
        } catch (e) {
            if (e instanceof NetworkError && shell && position) { await offerOffline(); return; }
            position = null;
            say('');
            fail(e.message || t.request_failed);
        } finally {
            setBusy(false);
        }
    });

    queueButton?.addEventListener('click', async () => {
        if (busy || !position || !shell) return;
        clearError();
        setBusy(true);
        try {
            const reference = await shell.reference();
            const attendance = reference && reference.data ? reference.data.attendance : null;
            const action = step();
            const site = attendance && attendance.site ? attendance.site : null;
            const verdict = indicativeGeofence(site, position.latitude, position.longitude);
            await shell.queue.enqueue({
                type: 'attendance', action,
                payload: { latitude: position.latitude, longitude: position.longitude, accuracy: position.accuracy,
                    site_id: site ? site.id : Number(root.dataset.siteId), project_id: attendance && attendance.project ? attendance.project.id : Number(root.dataset.projectId),
                    client_geofence_status: verdict.status },
                captured_at: position.captured_at, owner_user_id: shell.userId, owner_name: shell.userName,
                label: `${action === 'check_in' ? t.check_in : t.check_out} · ${site ? site.name : ''}`,
                context: { site_name: site ? site.name : null, indicative: verdict },
            });
            root.dataset.state = action === 'check_in' ? 'can_check_out' : 'completed';
            stateLabel.textContent = (action === 'check_in' ? t.state_can_check_out : t.state_completed) + ' · ' + (ot.status_pending || '');
            stateLabel.classList.toggle('ok', action === 'check_in');
            say(ot.queued_attendance || '');
            result.hidden = true;
            queueButton.hidden = true;
            cancelButton.hidden = true;
            locateButton.hidden = action !== 'check_in';
            if (action === 'check_in') { locateButton.textContent = t.check_out; confirmButton.textContent = t.confirm_check_out; }
            position = null;
            offline = false;
            await shell.manager.refresh();
            shell.manager.sync({ reason: 'queued' });
        } catch (e) {
            fail(e.message || t.request_failed);
        } finally {
            setBusy(false);
        }
    });

    confirmButton?.addEventListener('click', async () => {
        if (busy || !position) return;
        clearError();
        setBusy(true);
        const current = step();
        try {
            const data = await post(current === 'check_in' ? root.dataset.checkInUrl : root.dataset.checkOutUrl, position);
            say(data.message || '');
            show('[data-result-status]', data.status_label);
            show('[data-result-distance]', data.distance_meters !== null ? `${Math.round(data.distance_meters)} m` : '-');
            root.dataset.state = data.state;
            stateLabel.textContent = data.state === 'completed' ? t.state_completed : t.state_can_check_out;
            stateLabel.classList.toggle('ok', data.state === 'can_check_out');
            confirmButton.hidden = true;
            cancelButton.hidden = true;
            if (data.state === 'can_check_out') {
                locateButton.textContent = t.check_out;
                confirmButton.textContent = t.confirm_check_out;
                locateButton.hidden = false;
                const late = data.late_minutes > 0 ? t.late_by.replace(':minutes', data.late_minutes) : t.on_time;
                say(`${t.checked_in_at.replace(':time', data.time)} · ${data.status_label} · ${late}`);
            } else {
                locateButton.hidden = true;
                say(t.checked_out_at.replace(':time', data.time) + ' · ' + data.status_label);
            }
            position = null;
        } catch (e) {
            if (e instanceof NetworkError && shell && position) { await offerOffline(); setBusy(false); return; }
            fail(e.message || t.request_failed);
        } finally {
            setBusy(false);
        }
    });

    cancelButton?.addEventListener('click', () => { clearError(); if (offlineNote) offlineNote.hidden = true; reset(); });
}
