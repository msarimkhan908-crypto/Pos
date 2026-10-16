import Alpine from 'alpinejs';

/**
 * Kitchen display: polls the ticket feed and moves tickets (or one station's items)
 * Pending -> Preparing -> Ready -> Completed. All state changes happen on the server.
 */

const FILTER_KEY = 'pos.kitchen.filters.v1';
const REFRESH_MS = 10000;

Alpine.data('kitchen', ({ urls, canManage = false, messages = {} }) => {
    let refreshTimer = null;
    let clockTimer = null;

    return {
        canManage,
        messages,
        orders: [],
        counts: {},
        status: 'all',
        station: null,
        loading: true,
        error: null,
        busy: {},
        now: Date.now(),
        toast: null,
        lastUpdated: null,

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(FILTER_KEY) || 'null');
                if (saved) {
                    this.status = saved.status ?? 'all';
                    this.station = saved.station ?? null;
                }
            } catch (e) {
                // Storage unavailable; use the defaults.
            }

            this.load();
            refreshTimer = setInterval(() => this.load(), REFRESH_MS);
            clockTimer = setInterval(() => (this.now = Date.now()), 15000);
        },

        destroy() {
            clearInterval(refreshTimer);
            clearInterval(clockTimer);
        },

        saveFilters() {
            try {
                localStorage.setItem(FILTER_KEY, JSON.stringify({ status: this.status, station: this.station }));
            } catch (e) {
                // Ignore; filters just won't be remembered.
            }
        },

        setStatus(status) {
            this.status = status;
            this.saveFilters();
            this.load();
        },

        setStation(id) {
            this.station = id;
            this.saveFilters();
            this.load();
        },

        async load() {
            const params = new URLSearchParams({ status: this.status });
            if (this.station) params.set('station', this.station);

            try {
                const response = await fetch(`${urls.orders}?${params}`, { headers: { Accept: 'application/json' } });
                if (response.status === 401 || response.status === 419) throw new Error(this.messages.session);
                if (!response.ok) throw new Error(this.messages.failed);
                const data = await response.json();
                this.orders = data.orders;
                this.counts = data.counts;
                this.error = null;
                this.lastUpdated = new Date();
            } catch (e) {
                this.error = e.message || this.messages.failed;
            } finally {
                this.loading = false;
            }
        },

        minutes(order) {
            return Math.max(0, Math.floor((this.now - new Date(order.created_at).getTime()) / 60000));
        },

        ageClass(order) {
            if (order.status === 'completed') return 'text-gray-400';
            const m = this.minutes(order);
            return m >= 20 ? 'text-red-600' : m >= 10 ? 'text-amber-600' : 'text-gray-500';
        },

        /** The main button for a ticket in its current status. */
        next(order) {
            return {
                pending: { status: 'preparing', label: this.messages.start },
                preparing: { status: 'ready', label: this.messages.ready },
                ready: { status: 'completed', label: this.messages.complete },
            }[order.status] ?? null;
        },

        async advance(order, status) {
            if (this.busy[order.id]) return;
            this.busy[order.id] = true;

            try {
                const response = await fetch(urls.status.replace('__ID__', order.id), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ status, station: this.station }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const first = Object.values(data.errors ?? {})[0];
                    this.notify((Array.isArray(first) ? first[0] : null) ?? data.message ?? this.messages.failed, 'error');
                } else {
                    this.notify(data.message);
                }
            } catch (e) {
                this.notify(this.messages.failed, 'error');
            } finally {
                delete this.busy[order.id];
                this.load();
            }
        },

        notify(message, type = 'success') {
            const id = Date.now();
            this.toast = { message, type, id };
            setTimeout(() => {
                if (this.toast?.id === id) this.toast = null;
            }, 3000);
        },
    };
});
