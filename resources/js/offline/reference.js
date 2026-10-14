// Operational reference data for the offline forms (own site context,
// scoped projects / sites, allowed categories, suppliers, payment accounts),
// cached on the device with its fetch time. Every id is revalidated by the
// server at sync; the cache is a convenience, never an authority.
import { STORES } from './store.js';

const KEY = 'reference';

export async function loadReference(store, fetchImpl, url, headers = {}) {
    const response = await fetchImpl(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...headers }, credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok) throw new Error(`reference ${response.status}`);
    const data = await response.json();
    const record = { key: KEY, last_updated_at: new Date().toISOString(), user_id: data.user?.id ?? null, data };
    await store.put(STORES.meta, record);
    return record;
}

export async function getReference(store, userId = null) {
    const record = await store.get(STORES.meta, KEY);
    if (!record) return null;
    if (userId !== null && record.user_id !== userId) return null;
    return record;
}

/** Great-circle distance in metres (same formula as the server; indicative only on the device). */
export function distanceMeters(lat1, lng1, lat2, lng2) {
    const R = 6371008.8;
    const toRad = d => d * Math.PI / 180;
    const dPhi = toRad(lat2 - lat1), dLambda = toRad(lng2 - lng1);
    const a = Math.sin(dPhi / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLambda / 2) ** 2;
    return Math.round(2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)) * 100) / 100;
}

/** Indicative verdict for the page while offline; the server's evaluation at sync is authoritative. */
export function indicativeGeofence(site, latitude, longitude) {
    if (!site) return { status: 'unknown', distance: null, blocked: false };
    if (!site.geofence_enabled) return { status: 'not_enforced', distance: site.latitude !== null && site.longitude !== null ? distanceMeters(latitude, longitude, site.latitude, site.longitude) : null, blocked: false };
    if (site.latitude === null || site.longitude === null) return { status: 'location_unavailable', distance: null, blocked: !!site.attendance_inside_only };
    const distance = distanceMeters(latitude, longitude, site.latitude, site.longitude);
    const inside = distance <= site.geofence_radius;
    return { status: inside ? 'inside' : 'outside', distance, blocked: !inside && !!site.attendance_inside_only };
}
