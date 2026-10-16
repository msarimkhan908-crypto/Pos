import Alpine from 'alpinejs';
import './pos';
import './kitchen';
import './returns';
import './settings';

const SIDEBAR_COLLAPSED_KEY = 'pos.sidebar.collapsed';
const THEME_KEY = 'pos.theme';

Alpine.data('appShell', () => ({
    // Off-canvas drawer on mobile, expanded overlay on tablet.
    sidebarOpen: false,

    // Desktop-only collapsed rail; mirrored as a class on <html>.
    collapsed: document.documentElement.classList.contains('sidebar-collapsed'),

    // Theme: 'light' | 'dark' | 'auto'
    theme: (() => { try { return localStorage.getItem(THEME_KEY) || 'auto'; } catch(e) { return 'auto'; } })(),

    get isDark() {
        return this.theme === 'dark' || (this.theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    },

    setTheme(mode) {
        this.theme = mode;
        document.documentElement.classList.toggle('dark', this.isDark);
        try { localStorage.setItem(THEME_KEY, mode); } catch(e) {}
    },

    cycleTheme() {
        const modes = ['light', 'dark', 'auto'];
        this.setTheme(modes[(modes.indexOf(this.theme) + 1) % modes.length]);
    },

    toggleSidebar() {
        this.sidebarOpen = !this.sidebarOpen;
    },

    toggleCollapsed() {
        this.collapsed = !this.collapsed;
        document.documentElement.classList.toggle('sidebar-collapsed', this.collapsed);

        try {
            localStorage.setItem(SIDEBAR_COLLAPSED_KEY, this.collapsed ? '1' : '0');
        } catch (e) {
            // Storage unavailable (private mode); collapse still works for this page.
        }
    },

    onResize() {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            this.sidebarOpen = false;
        }
    },
}));

const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/tiff', 'image/avif', 'image/x-icon', 'image/vnd.microsoft.icon'];

/**
 * Image picker with preview. Photos above the server limit are resized in the
 * browser (canvas) and re-encoded until they fit, then swapped into the file input.
 */
Alpine.data('imageUpload', ({ current = null, maxBytes, resizeTo = 1600, messages = {} }) => ({
    preview: current,
    removed: false,
    busy: false,
    error: null,

    async pick(event) {
        const input = event.target;
        let file = input.files[0];
        this.error = null;

        if (!file) {
            return;
        }

        if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
            this.reject(input, messages.type);
            return;
        }

        if (file.size > maxBytes) {
            this.busy = true;
            try {
                file = await this.shrink(file);
            } catch (e) {
                file = null;
            }
            this.busy = false;

            if (!file || file.size > maxBytes) {
                this.reject(input, messages.size);
                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
        }

        this.preview = URL.createObjectURL(file);
        this.removed = false;
    },

    reject(input, message) {
        input.value = '';
        this.preview = current;
        this.error = message;
    },

    async shrink(file) {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, resizeTo / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        // JPEG for photos; WEBP keeps PNG transparency.
        const type = file.type === 'image/jpeg' ? 'image/jpeg' : 'image/webp';
        let blob = null;

        for (const quality of [0.85, 0.75, 0.6, 0.45]) {
            blob = await new Promise((resolve) => canvas.toBlob(resolve, type, quality));
            if (blob && blob.size <= maxBytes) {
                break;
            }
        }

        if (!blob) {
            return null;
        }

        const extension = blob.type === 'image/jpeg' ? 'jpg' : blob.type.split('/')[1];
        const name = file.name.replace(/\.[^.]+$/, '') + '.' + extension;

        return new File([blob], name, { type: blob.type });
    },
}));

// Money helpers for on-screen previews: integer cents / thousandths avoid float drift.
// The server recalculates every total on save; these numbers are never trusted.
const toCents = (value) => Math.round((parseFloat(value) || 0) * 100);
const toMilli = (value) => Math.round((parseFloat(value) || 0) * 1000);
const fromCents = (cents) => (cents / 100).toFixed(2);

/**
 * Purchase form: rows of product / variant / quantity / price / discount / tax
 * with a searchable product picker and live totals.
 */
Alpine.data('purchaseForm', ({ products = [], items = [], errors = {} }) => ({
    products,
    errors,
    rows: [],

    init() {
        this.rows = (items.length ? items : [{}]).map((item) => this.makeRow(item));
    },

    makeRow(item = {}) {
        const product = this.products.find((p) => p.id === Number(item.product_id)) ?? null;

        return {
            key: Math.random().toString(36).slice(2),
            product_id: product?.id ?? '',
            variant_id: item.variant_id ? Number(item.variant_id) : '',
            quantity: item.quantity ?? '1',
            purchase_price: item.purchase_price ?? '',
            discount: item.discount ?? '0',
            tax: item.tax ?? '0',
            query: product ? product.name : '',
            open: false,
        };
    },

    addRow() {
        this.rows.push(this.makeRow());
    },

    removeRow(index) {
        this.rows.splice(index, 1);
        if (this.rows.length === 0) {
            this.addRow();
        }
    },

    productOf(row) {
        return this.products.find((p) => p.id === Number(row.product_id)) ?? null;
    },

    variantsOf(row) {
        return this.productOf(row)?.variants ?? [];
    },

    matches(row) {
        const term = (row.query || '').trim().toLowerCase();
        const list = term
            ? this.products.filter((p) => [p.name, p.sku, p.barcode].some((v) => (v || '').toLowerCase().includes(term))
                || p.variants.some((v) => (v.sku || '').toLowerCase().includes(term)))
            : this.products;

        return list.slice(0, 20);
    },

    choose(row, product) {
        row.product_id = product.id;
        row.query = product.name;
        row.open = false;
        row.variant_id = '';
        row.purchase_price = product.variants.length ? '' : product.cost;
    },

    chooseVariant(row) {
        const variant = this.variantsOf(row).find((v) => v.id === Number(row.variant_id));
        if (variant) {
            row.purchase_price = variant.cost;
        }
    },

    grossCents(row) {
        return Math.round((toMilli(row.quantity) * toCents(row.purchase_price)) / 1000);
    },

    lineCents(row) {
        return this.grossCents(row) - toCents(row.discount) + toCents(row.tax);
    },

    lineTotal(row) {
        return fromCents(this.lineCents(row));
    },

    sum(fn) {
        return fromCents(this.rows.reduce((total, row) => total + fn(row), 0));
    },

    get subtotal() { return this.sum((row) => this.grossCents(row)); },
    get discountTotal() { return this.sum((row) => toCents(row.discount)); },
    get taxTotal() { return this.sum((row) => toCents(row.tax)); },
    get grandTotal() { return this.sum((row) => this.lineCents(row)); },

    error(index, field) {
        return this.errors[`items.${index}.${field}`] ?? null;
    },
}));

window.Alpine = Alpine;

Alpine.start();
