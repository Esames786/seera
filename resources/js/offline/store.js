// Storage adapters behind the offline queue. IndexedDB holds the durable
// business queue (including receipt Blobs); the memory store backs the Node
// unit tests. Both expose the same small async API keyed per object store.
export const DB_NAME = 'seera-offline';
export const DB_VERSION = 1;
export const STORES = { queue: 'queue', meta: 'meta' };

export class MemoryStore {
    constructor() { this.data = { [STORES.queue]: new Map(), [STORES.meta]: new Map() }; }
    key(store) { return store === STORES.queue ? 'client_uuid' : 'key'; }
    async get(store, key) { const v = this.data[store].get(key); return v === undefined ? null : structuredClone(v); }
    async put(store, value) { this.data[store].set(value[this.key(store)], structuredClone(value)); return value; }
    async delete(store, key) { this.data[store].delete(key); }
    async all(store) { return [...this.data[store].values()].map(v => structuredClone(v)); }
    async clear(store) { this.data[store].clear(); }
}

export class IndexedDbStore {
    constructor(factory = globalThis.indexedDB) { this.factory = factory; this.db = null; }

    static available() { return typeof globalThis.indexedDB !== 'undefined'; }

    open() {
        if (this.db) return Promise.resolve(this.db);
        return new Promise((resolve, reject) => {
            const request = this.factory.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(STORES.queue)) {
                    const queue = db.createObjectStore(STORES.queue, { keyPath: 'client_uuid' });
                    queue.createIndex('status', 'status', { unique: false });
                    queue.createIndex('owner', 'owner_user_id', { unique: false });
                }
                if (!db.objectStoreNames.contains(STORES.meta)) db.createObjectStore(STORES.meta, { keyPath: 'key' });
            };
            request.onsuccess = () => { this.db = request.result; this.db.onversionchange = () => { this.db.close(); this.db = null; }; resolve(this.db); };
            request.onerror = () => reject(request.error || new Error('IndexedDB open failed'));
            request.onblocked = () => reject(new Error('IndexedDB open blocked'));
        });
    }

    async run(store, mode, work) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(store, mode);
            const request = work(tx.objectStore(store));
            tx.oncomplete = () => resolve(request && 'result' in request ? request.result : undefined);
            tx.onerror = () => reject(tx.error || new Error('IndexedDB transaction failed'));
            tx.onabort = () => reject(tx.error || new Error('IndexedDB transaction aborted'));
        });
    }

    async get(store, key) { const v = await this.run(store, 'readonly', s => s.get(key)); return v === undefined ? null : v; }
    async put(store, value) { await this.run(store, 'readwrite', s => s.put(value)); return value; }
    async delete(store, key) { await this.run(store, 'readwrite', s => s.delete(key)); }
    async all(store) { return (await this.run(store, 'readonly', s => s.getAll())) || []; }
    async clear(store) { await this.run(store, 'readwrite', s => s.clear()); }
}
