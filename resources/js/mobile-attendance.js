// Location-validated mobile attendance (Phase 1, online only).
// The browser asks for a position only when the user presses Check in / Check out,
// sends it to the server for evaluation, shows the server's verdict, then confirms.
// The server computes distance and status and records its own time; nothing here
// is trusted for policy. No continuous tracking: one position per action.
const root = document.querySelector('[data-mobile-attendance]');
if (root && root.dataset.locateUrl) {
    const t = JSON.parse(root.dataset.i18n || '{}');
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': root.dataset.token };
    const status = root.querySelector('[data-status]');
    const result = root.querySelector('[data-result]');
    const error = root.querySelector('[data-error]');
    const locateButton = root.querySelector('[data-action="locate"]');
    const confirmButton = root.querySelector('[data-action="confirm"]');
    const cancelButton = root.querySelector('[data-action="cancel"]');
    const stateLabel = root.querySelector('[data-state-label]');
    let position = null;
    let busy = false;

    const say = message => { if (status) status.textContent = message || ''; };
    const fail = message => { error.textContent = message; error.hidden = false; };
    const clearError = () => { error.hidden = true; error.textContent = ''; };
    const setBusy = value => {
        busy = value;
        [locateButton, confirmButton, cancelButton].forEach(button => { if (button) button.disabled = value; });
    };
    const reset = () => {
        position = null;
        result.hidden = true;
        confirmButton.hidden = true;
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
    const post = async (url, body) => {
        const response = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body) });
        let data = null;
        try { data = await response.json(); } catch { data = null; }
        if (!response.ok) {
            const message = data && (data.message || (data.errors && Object.values(data.errors).flat().join(' ')));
            throw new Error(message || t.request_failed);
        }
        return data;
    };
    const show = (selector, text) => { const node = root.querySelector(selector); if (node) node.textContent = text; };

    locateButton?.addEventListener('click', async () => {
        if (busy) return;
        clearError();
        setBusy(true);
        say(t.getting_location);
        try {
            position = await getPosition();
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
            position = null;
            say('');
            fail(e.message || t.request_failed);
        } finally {
            setBusy(false);
        }
    });

    confirmButton?.addEventListener('click', async () => {
        if (busy || !position) return;
        clearError();
        setBusy(true);
        const step = root.dataset.state === 'can_check_in' ? 'check_in' : 'check_out';
        try {
            const data = await post(step === 'check_in' ? root.dataset.checkInUrl : root.dataset.checkOutUrl, position);
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
            fail(e.message || t.request_failed);
        } finally {
            setBusy(false);
        }
    });

    cancelButton?.addEventListener('click', () => { clearError(); reset(); });
}
