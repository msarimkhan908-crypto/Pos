import Alpine from 'alpinejs';

/**
 * POS cashier screen.
 *
 * The browser only keeps the cart (ids, quantities, discounts). Every total shown
 * comes from POST /pos/quote, and checkout recalculates everything again on the
 * server. Prices shown on a line before the quote arrives are only estimates.
 */

const CART_KEY = 'pos.cart.v1';
const cents = (value) => Math.round((parseFloat(value) || 0) * 100);
const fromCents = (value) => (value / 100).toFixed(2);

Alpine.data('pos', ({ urls, currency, currencySymbol = '', hideTrailingZeros = true, customers = [], canSaveCustomer = false, print = { receipt: true, kot: true, layout: 'combined' }, printers = { receipt: null, kot: null }, messages = {}, canEdit = false }) => {
    // Kept outside Alpine's reactive state: Intl formatters and AbortControllers break when proxied.
    const money = new Intl.NumberFormat('en', { style: 'currency', currency, minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const moneyWhole = new Intl.NumberFormat('en', { style: 'currency', currency, minimumFractionDigits: 0, maximumFractionDigits: 0 });
    let searchTimer = null;
    let quoteTimer = null;
    let productsAbort = null;
    let flashTimer = null;

    return {
        urls,
        customers,
        canSaveCustomer,
        printSettings: print,
        printers,
        messages,

        // Catalog
        search: '',
        category: null,
        products: [],
        more: false,
        loadingProducts: false,
        productsError: null,
        barcode: '',

        // Cart
        lines: [],
        customerId: null,
        // Typed customer details and delivery fee (saved on the sale), edited through a draft in the dialog
        details: { name: '', phone: '', address: '', deliveryFee: '', save: false },
        draft: null,

        // Restaurant: order type, table (Dine In only) and the order note for the kitchen
        orderType: 'take_away',
        tableId: null,
        tableName: '',
        orderNote: '',
        tables: [],
        tablesLoading: false,
        canChangeTableStatus: false,
        noting: null,
        orderDiscount: { type: '', value: '' },

        // Server pricing
        quote: null,
        quoteKey: null,
        quoteErrors: {},
        quoting: false,

        // Dialogs
        picking: null,
        editing: null,
        held: [],
        heldCount: 0,
        heldLoading: false,
        history: [],
        historyLoading: false,
        canEdit,
        pay: { method: 'cash', cash_received: '', cash_amount: '', card_amount: '', errors: {}, submitting: false },
        receipt: null,
        busy: false,
        toast: null,
        flashKey: null,

        init() {
            this.restore();
            this.loadProducts();
            this.refreshHeld(false);
            this.requestQuote();

            this.$watch('search', () => this.debouncedLoad());
            this.$watch('lines', () => this.changed(), { deep: true });
            this.$watch('customerId', () => this.changed());
            this.$watch('details', () => this.changed(), { deep: true });
            this.$watch('orderType', () => this.changed());
            this.$watch('tableId', () => this.changed());
            this.$watch('orderNote', () => this.changed());
            this.$watch('orderDiscount', () => this.changed(), { deep: true });
        },

        // ---------------------------------------------------------------- helpers

        format(amount) {
            const value = parseFloat(amount) || 0;
            // Check if the value is whole (no fractional cents) after rounding to 2 dp.
            const isWhole = hideTrailingZeros && (Math.round(Math.abs(value) * 100) % 100 === 0);
            const fractionDigits = isWhole ? 0 : 2;
            if (currencySymbol) {
                return `${value < 0 ? '-' : ''}${currencySymbol} ${Math.abs(value).toLocaleString('en', { minimumFractionDigits: fractionDigits, maximumFractionDigits: fractionDigits })}`;
            }
            return isWhole ? moneyWhole.format(value) : money.format(value);
        },

        notify(message, type = 'success') {
            this.toast = { message, type, id: Date.now() };
            const id = this.toast.id;
            setTimeout(() => {
                if (this.toast?.id === id) this.toast = null;
            }, type === 'error' ? 5000 : 3000);
        },

        async api(method, url, body = null) {
            const response = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: body ? JSON.stringify(body) : null,
            });

            let data = {};
            try {
                data = await response.json();
            } catch (e) {
                // Non-JSON error page; fall through with an empty body.
            }

            if (response.status === 419 || response.status === 401) {
                data.message = this.messages.session;
            } else if (response.status === 403) {
                data.message = this.messages.forbidden;
            } else if (response.status >= 500) {
                data.message = this.messages.server;
            }

            return { ok: response.ok, status: response.status, data };
        },

        firstError(errors) {
            const first = Object.values(errors || {})[0];
            return Array.isArray(first) ? first[0] : first;
        },

        persist() {
            try {
                localStorage.setItem(CART_KEY, JSON.stringify({
                    lines: this.lines,
                    customerId: this.customerId,
                    details: this.details,
                    orderType: this.orderType,
                    tableId: this.tableId,
                    tableName: this.tableName,
                    orderNote: this.orderNote,
                    orderDiscount: this.orderDiscount,
                }));
            } catch (e) {
                // Storage unavailable; the cart still works for this page.
            }
        },

        restore() {
            try {
                const saved = JSON.parse(localStorage.getItem(CART_KEY) || 'null');
                if (saved && Array.isArray(saved.lines)) {
                    this.lines = saved.lines;
                    this.customerId = this.customers.some((c) => c.id === saved.customerId) ? saved.customerId : null;
                    this.orderDiscount = saved.orderDiscount ?? { type: '', value: '' };
                    this.details = { ...this.details, ...(saved.details ?? {}) };
                    this.orderType = saved.orderType ?? 'take_away';
                    this.tableId = saved.tableId ?? null;
                    this.tableName = saved.tableName ?? '';
                    this.orderNote = saved.orderNote ?? '';
                }
            } catch (e) {
                // Corrupt or blocked storage; start with an empty cart.
            }
        },

        // ---------------------------------------------------------------- catalog

        debouncedLoad() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => this.loadProducts(), 200);
        },

        selectCategory(id) {
            this.category = id;
            this.loadProducts();
        },

        async loadProducts() {
            productsAbort?.abort();
            const controller = new AbortController();
            productsAbort = controller;
            this.loadingProducts = true;
            this.productsError = null;

            const params = new URLSearchParams();
            if (this.search.trim()) params.set('search', this.search.trim());
            if (this.category) params.set('category', this.category);

            try {
                const response = await fetch(`${this.urls.products}?${params}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error(response.status);
                const data = await response.json();
                this.products = data.products;
                this.more = data.more;
            } catch (e) {
                if (e.name === 'AbortError') return;
                this.productsError = this.messages.productsFailed;
            }

            if (productsAbort === controller) this.loadingProducts = false;
        },

        async scan() {
            const code = this.barcode.trim();
            if (!code) return;
            this.barcode = '';

            try {
                const response = await fetch(`${this.urls.lookup}?${new URLSearchParams({ code })}`, {
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.notify(response.status === 404 ? this.messages.notFound : (data.message || this.messages.server), 'error');
                    return;
                }

                this.addProduct(data.product, data.variant_id);
            } catch (e) {
                this.notify(this.messages.server, 'error');
            }
        },

        // ---------------------------------------------------------------- adding

        needsOptions(product, variantId = null) {
            return (product.variants.length > 0 && !variantId) || product.modifier_groups.length > 0;
        },

        addProduct(product, variantId = null) {
            if (this.needsOptions(product, variantId)) {
                this.picking = {
                    product,
                    variant_id: variantId ?? (product.variants.length === 1 ? product.variants[0].id : null),
                    modifier_ids: [],
                    error: null,
                };
                this.$dispatch('open-modal', 'pos-options');
                return;
            }

            const variant = product.variants.find((v) => v.id === variantId) ?? null;
            this.addLine(product, variant, []);
        },

        pickerVariant() {
            return this.picking?.product.variants.find((v) => v.id === this.picking.variant_id) ?? null;
        },

        toggleModifier(group, modifier) {
            const ids = this.picking.modifier_ids;
            const inGroup = group.modifiers.map((m) => m.id);

            if (ids.includes(modifier.id)) {
                this.picking.modifier_ids = ids.filter((id) => id !== modifier.id);
                return;
            }

            if (group.max === 1) {
                // Single choice: swap within the group.
                this.picking.modifier_ids = [...ids.filter((id) => !inGroup.includes(id)), modifier.id];
                return;
            }

            if (group.max !== null && ids.filter((id) => inGroup.includes(id)).length >= group.max) {
                return;
            }

            this.picking.modifier_ids = [...ids, modifier.id];
        },

        pickerUnitCents() {
            if (!this.picking) return 0;
            const product = this.picking.product;
            const base = this.pickerVariant()?.price ?? product.price;
            const extras = product.modifier_groups
                .flatMap((g) => g.modifiers)
                .filter((m) => this.picking.modifier_ids.includes(m.id))
                .reduce((sum, m) => sum + cents(m.price), 0);

            return cents(base) + extras;
        },

        confirmPick() {
            const { product, variant_id: variantId, modifier_ids: ids } = this.picking;

            if (product.variants.length && !variantId) {
                this.picking.error = this.messages.chooseVariant;
                return;
            }

            for (const group of product.modifier_groups) {
                const count = group.modifiers.filter((m) => ids.includes(m.id)).length;
                if (count < group.min || (group.max !== null && count > group.max)) {
                    this.picking.error = `${group.name}: ${group.rule}`;
                    return;
                }
            }

            const modifiers = product.modifier_groups
                .flatMap((g) => g.modifiers)
                .filter((m) => ids.includes(m.id));

            this.addLine(product, this.pickerVariant(), modifiers);
            this.$dispatch('close-modal', 'pos-options');
            this.picking = null;
        },

        addLine(product, variant, modifiers) {
            const modifierIds = modifiers.map((m) => m.id).sort((a, b) => a - b);
            const key = `${product.id}:${variant?.id ?? 0}:${modifierIds.join(',')}`;
            const existing = this.lines.find((line) => line.key === key);

            if (existing) {
                existing.quantity++;
            } else {
                const unit = cents(variant?.price ?? product.price) + modifiers.reduce((sum, m) => sum + cents(m.price), 0);
                this.lines.push({
                    key,
                    product_id: product.id,
                    variant_id: variant?.id ?? null,
                    modifier_ids: modifierIds,
                    quantity: 1,
                    discount_type: '',
                    discount_value: '',
                    name: product.name,
                    variant_name: variant?.name ?? null,
                    is_deal: product.is_deal ?? false,
                    deal_items: product.deal_items ?? [],
                    modifiers: modifiers.map((m) => ({ name: m.name, price: m.price })),
                    unit_price: fromCents(unit),
                    note: '',
                    image_url: product.image_url,
                });
            }

            this.notify(`${product.name}${variant ? ` (${variant.name})` : ''} ${this.messages.added}`);
            this.showLine(key);
        },

        /** Scroll the cart to a line and highlight it briefly, so the cashier sees what changed. */
        showLine(key) {
            this.flashKey = key;
            clearTimeout(flashTimer);
            flashTimer = setTimeout(() => (this.flashKey = null), 900);

            this.$nextTick(() => {
                const row = this.$refs.cartList?.querySelector(`[data-key="${CSS.escape(key)}"]`);
                row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        },

        // ---------------------------------------------------------------- cart

        increase(index) {
            if (this.lines[index].quantity < 9999) this.lines[index].quantity++;
        },

        decrease(index) {
            if (this.lines[index].quantity <= 1) {
                this.remove(index);
            } else {
                this.lines[index].quantity--;
            }
        },

        setQuantity(index, value) {
            const quantity = parseInt(value, 10);
            if (!quantity || quantity < 1) {
                this.remove(index);
            } else {
                this.lines[index].quantity = Math.min(quantity, 9999);
            }
        },

        remove(index) {
            this.lines.splice(index, 1);
        },

        askClear() {
            if (this.lines.length) this.$dispatch('open-modal', 'pos-clear');
        },

        clearCart() {
            this.lines = [];
            this.resetCustomer();
            this.resetOrder();
            this.orderDiscount = { type: '', value: '' };
            this.quote = null;
            this.quoteErrors = {};
            this.$dispatch('close-modal', 'pos-clear');
            this.$nextTick(() => this.$refs.search?.focus());
        },

        get itemCount() {
            return this.lines.reduce((sum, line) => sum + line.quantity, 0);
        },

        // ---------------------------------------------------------------- discounts

        openDiscount(target, index = null) {
            const source = target === 'order' ? this.orderDiscount : this.lines[index];
            const type = target === 'order' ? source.type : source.discount_type;
            const value = target === 'order' ? source.value : source.discount_value;

            this.editing = { target, index, type: type || 'fixed', value: value || '' };
            this.$dispatch('open-modal', 'pos-discount');
        },

        applyDiscount(remove = false) {
            const { target, index } = this.editing;
            const type = remove || !(parseFloat(this.editing.value) > 0) ? '' : this.editing.type;
            const value = type ? this.editing.value : '';

            if (target === 'order') {
                this.orderDiscount = { type, value };
            } else if (this.lines[index]) {
                this.lines[index].discount_type = type;
                this.lines[index].discount_value = value;
            }

            this.$dispatch('close-modal', 'pos-discount');
            this.editing = null;
        },

        discountLabel(type, value) {
            if (!type || !(parseFloat(value) > 0)) return '';
            return type === 'percent' ? `${parseFloat(value)}%` : this.format(value);
        },

        // ---------------------------------------------------------------- customer

        get customer() {
            return this.customers.find((c) => c.id === this.customerId) ?? null;
        },

        /** Name shown on the cart: typed name, else the linked customer, else Walk-in. */
        get customerName() {
            return this.details.name.trim() || this.customer?.name || this.messages.walkIn;
        },

        get customerLine() {
            return [this.details.phone || this.customer?.phone, this.details.address].filter(Boolean).join(' · ');
        },

        get deliveryFee() {
            return this.orderType === 'delivery' && parseFloat(this.details.deliveryFee) > 0 ? this.details.deliveryFee : null;
        },

        resetCustomer() {
            this.customerId = null;
            this.details = { name: '', phone: '', address: '', deliveryFee: '', save: false };
        },

        openCustomer() {
            this.draft = { customerId: this.customerId, ...this.details, query: '' };
            this.$dispatch('open-modal', 'pos-customer');
            setTimeout(() => this.$refs.customerName?.focus(), 120);
        },

        /** Existing customers matching the search box, or the typed name / phone while no one is linked. */
        get draftMatches() {
            if (!this.draft) return [];
            const query = this.draft.query.trim().toLowerCase();
            const phone = this.draft.phone.replace(/\D/g, '');
            const name = this.draft.name.trim().toLowerCase();

            if (query) {
                return this.customers
                    .filter((c) => c.name.toLowerCase().includes(query) || (c.phone || '').replace(/\D/g, '').includes(query.replace(/\D/g, '') || '\u0000'))
                    .slice(0, 8);
            }

            if (this.draft.customerId || (phone.length < 3 && name.length < 2)) return [];

            return this.customers
                .filter((c) => (phone.length >= 3 && (c.phone || '').replace(/\D/g, '').includes(phone))
                    || (name.length >= 2 && c.name.toLowerCase().includes(name)))
                .slice(0, 5);
        },

        draftPick(customer) {
            Object.assign(this.draft, {
                customerId: customer.id,
                name: customer.name,
                phone: customer.phone || '',
                address: customer.address || '',
                save: false,
                query: '',
            });
        },

        draftUnlink() {
            this.draft.customerId = null;
        },

        applyCustomer() {
            const { customerId, name, phone, address, deliveryFee, save } = this.draft;
            this.customerId = customerId;
            this.details = { name, phone, address, deliveryFee, save: !customerId && save && name.trim() !== '' };
            this.$dispatch('close-modal', 'pos-customer');
            this.draft = null;
        },

        walkIn() {
            this.resetCustomer();
            this.$dispatch('close-modal', 'pos-customer');
            this.draft = null;
        },

        // ---------------------------------------------------------------- restaurant

        setOrderType(type) {
            this.orderType = type;

            if (type !== 'dine_in') {
                this.tableId = null;
                this.tableName = '';
            } else if (!this.tableId) {
                this.openTables();
            }
        },

        resetOrder() {
            this.tableId = null;
            this.tableName = '';
            this.orderNote = '';
        },

        async loadTables() {
            this.tablesLoading = true;
            const { ok, data } = await this.api('GET', this.urls.tables).catch(() => ({ ok: false, data: {} }));
            this.tablesLoading = false;

            if (ok) {
                this.tables = data.tables;
                this.canChangeTableStatus = data.can_change_status;
            } else {
                this.notify(data.message || this.messages.server, 'error');
            }
        },

        async openTables() {
            this.$dispatch('open-modal', 'pos-tables');
            await this.loadTables();
        },

        chooseTable(table) {
            if (table.status === 'reserved') {
                this.notify(this.messages.reserved.replace(':table', table.name), 'error');
                return;
            }

            this.orderType = 'dine_in';
            this.tableId = table.id;
            this.tableName = table.name;
            this.$dispatch('close-modal', 'pos-tables');
        },

        /** Change a table's status from the picker (e.g. a reserved party arrives). Saved on the server. */
        async setTableStatus(table, status) {
            const { ok, data } = await this.api('PATCH', this.urls.tableStatus.replace('__ID__', table.id), { status })
                .catch(() => ({ ok: false, data: {} }));
            this.notify(data.message || this.messages.server, ok ? 'success' : 'error');
            await this.loadTables();
        },

        openNote(target, index = null) {
            const text = target === 'order' ? this.orderNote : this.lines[index]?.note;
            this.noting = { target, index, text: text || '' };
            this.$dispatch('open-modal', 'pos-note');
            setTimeout(() => this.$refs.noteText?.focus(), 120);
        },

        applyNote() {
            const text = this.noting.text.trim();

            if (this.noting.target === 'order') {
                this.orderNote = text;
            } else if (this.lines[this.noting.index]) {
                this.lines[this.noting.index].note = text;
            }

            this.$dispatch('close-modal', 'pos-note');
            this.noting = null;
        },

        // ---------------------------------------------------------------- pricing

        payload() {
            return {
                items: this.lines.map((line) => ({
                    product_id: line.product_id,
                    variant_id: line.variant_id,
                    quantity: line.quantity,
                    modifier_ids: line.modifier_ids,
                    discount_type: line.discount_type || null,
                    discount_value: line.discount_type ? line.discount_value : null,
                    note: (line.note || '').trim() || null,
                })),
                order_type: this.orderType,
                table_id: this.orderType === 'dine_in' ? this.tableId : null,
                order_note: this.orderNote.trim() || null,
                customer_id: this.customerId,
                customer_name: this.details.name.trim() || null,
                customer_phone: this.details.phone.trim() || null,
                customer_address: this.details.address.trim() || null,
                save_customer: !this.customerId && this.details.save,
                delivery_fee: this.orderType === 'delivery' ? this.deliveryFee : null,
                order_discount_type: this.orderDiscount.type || null,
                order_discount_value: this.orderDiscount.type ? this.orderDiscount.value : null,
            };
        },

        changed() {
            this.persist();
            clearTimeout(quoteTimer);
            quoteTimer = setTimeout(() => this.requestQuote(), 150);
        },

        get fresh() {
            return this.quote !== null && this.quoteKey === JSON.stringify(this.payload());
        },

        async requestQuote() {
            if (!this.lines.length) {
                this.quote = null;
                this.quoteKey = null;
                this.quoteErrors = {};
                return;
            }

            const body = this.payload();
            const key = JSON.stringify(body);
            this.quoting = true;

            try {
                const { ok, status, data } = await this.api('POST', this.urls.quote, body);
                if (key !== JSON.stringify(this.payload())) return; // cart changed meanwhile

                if (ok) {
                    this.quote = data;
                    this.quoteKey = key;
                    this.quoteErrors = {};
                } else {
                    this.quote = null;
                    this.quoteKey = null;
                    this.quoteErrors = status === 422 ? data.errors : { cart: [data.message || this.messages.server] };
                }
            } catch (e) {
                this.quoteErrors = { cart: [this.messages.server] };
            } finally {
                this.quoting = false;
            }
        },

        lineError(index) {
            const prefix = `items.${index}.`;
            const key = Object.keys(this.quoteErrors).find((k) => k.startsWith(prefix));
            return key ? this.firstError({ key: this.quoteErrors[key] }) : null;
        },

        get cartError() {
            const key = Object.keys(this.quoteErrors).find((k) => !k.startsWith('items.'));
            return key ? this.firstError({ key: this.quoteErrors[key] }) : null;
        },

        lineTotal(index) {
            const line = this.lines[index];
            if (this.fresh && this.quote.lines[index]) return this.quote.lines[index].total;
            return fromCents(cents(line.unit_price) * line.quantity);
        },

        lineDiscount(index) {
            return this.fresh ? this.quote.lines[index]?.discount ?? '0.00' : null;
        },

        total(key) {
            return this.fresh ? this.quote[key] : null;
        },

        get needsTable() {
            return this.orderType === 'dine_in' && !this.tableId;
        },

        get canCheckout() {
            return !this.needsTable && this.lines.length > 0 && this.fresh && !this.quoting && Object.keys(this.quoteErrors).length === 0;
        },

        // ---------------------------------------------------------------- history

        async openHistory() {
            this.$dispatch('open-modal', 'pos-history');
            await this.loadHistory();
        },

        async loadHistory() {
            this.historyLoading = true;
            const { ok, data } = await this.api('GET', this.urls.history).catch(() => ({ ok: false, data: {} }));
            this.historyLoading = false;
            if (ok) {
                this.history = data.sales;
            }
        },

        // ---------------------------------------------------------------- held sales

        async refreshHeld(showList = true) {
            this.heldLoading = true;
            const { ok, data } = await this.api('GET', this.urls.held).catch(() => ({ ok: false, data: {} }));
            this.heldLoading = false;

            if (ok) {
                this.held = data.held;
                this.heldCount = data.held.length;
            } else if (showList) {
                this.notify(data.message || this.messages.server, 'error');
            }
        },

        async openHeld() {
            this.$dispatch('open-modal', 'pos-held');
            await this.refreshHeld();
        },

        async holdSale() {
            if (!this.lines.length || this.busy) return;
            this.busy = true;

            const { ok, data } = await this.api('POST', this.urls.hold, this.payload()).catch(() => ({ ok: false, data: {} }));
            this.busy = false;

            if (!ok) {
                this.notify(this.firstError(data.errors) || data.message || this.messages.server, 'error');
                return;
            }

            this.clearCart();
            this.notify(data.message);
            this.refreshHeld(false);
        },

        async resumeHeld(sale) {
            if (this.lines.length) {
                this.notify(this.messages.cartNotEmpty, 'error');
                return;
            }

            const { ok, data } = await this.api('POST', this.urls.resume.replace('__ID__', sale.id)).catch(() => ({ ok: false, data: {} }));

            if (!ok) {
                this.notify(data.message || this.messages.server, 'error');
                this.refreshHeld(false);
                return;
            }

            this.lines = data.items.map((item) => ({
                key: `${item.product_id}:${item.variant_id ?? 0}:${[...item.modifier_ids].sort((a, b) => a - b).join(',')}`,
                product_id: item.product_id,
                variant_id: item.variant_id,
                modifier_ids: item.modifier_ids,
                quantity: item.quantity,
                discount_type: item.discount_type ?? '',
                discount_value: item.discount_type ? item.discount_value : '',
                name: item.product_name,
                variant_name: item.variant_name,
                modifiers: item.modifiers.map((m) => ({ name: m.name, price: m.price })),
                unit_price: item.unit_price,
                image_url: item.image_url,
                note: item.note ?? '',
            }));
            this.customerId = data.customer_id;
            this.orderType = data.order_type ?? 'take_away';
            this.tableId = data.table_id;
            this.tableName = data.table_name ?? '';
            this.orderNote = data.order_note ?? '';
            this.details = {
                name: data.customer_name ?? '',
                phone: data.customer_phone ?? '',
                address: data.customer_address ?? '',
                deliveryFee: parseFloat(data.delivery_fee) > 0 ? data.delivery_fee : '',
                save: false,
            };
            this.orderDiscount = data.order_discount_type
                ? { type: data.order_discount_type, value: data.order_discount_value }
                : { type: '', value: '' };

            this.$dispatch('close-modal', 'pos-held');
            this.refreshHeld(false);

            if (data.dropped.length) {
                this.notify(`${this.messages.dropped} ${data.dropped.join(', ')}`, 'error');
            } else {
                this.notify(data.message);
            }
        },

        async discardHeld(sale) {
            const { ok, data } = await this.api('DELETE', this.urls.discard.replace('__ID__', sale.id)).catch(() => ({ ok: false, data: {} }));
            this.notify(data.message || this.messages.server, ok ? 'success' : 'error');
            this.refreshHeld(false);
        },

        // ---------------------------------------------------------------- checkout

        openCheckout() {
            if (!this.canCheckout) {
                if (!this.lines.length) {
                    this.notify(this.messages.empty, 'error');
                } else if (this.needsTable) {
                    this.notify(this.messages.selectTable, 'error');
                    this.openTables();
                }
                return;
            }

            this.pay = { method: 'cash', cash_received: '', cash_amount: '', card_amount: '', errors: {}, submitting: false };
            this.$dispatch('open-modal', 'pos-checkout');
            setTimeout(() => this.$refs.cashReceived?.focus(), 120);
        },

        setMethod(method) {
            const isWallet = method === 'easypaisa' || method === 'jazzcash';
            this.pay.method = method;
            this.pay.errors = {};
            this.pay.card_amount = (method === 'card' || isWallet) ? this.quote.total : '';
            this.pay.cash_amount = '';
            this.$nextTick(() => (method === 'cash' ? this.$refs.cashReceived : method === 'mixed' ? this.$refs.cashAmount : null)?.focus());
        },

        fillCard() {
            const rest = cents(this.quote.total) - cents(this.pay.cash_amount);
            this.pay.card_amount = rest > 0 ? fromCents(rest) : '';
        },

        get quickCash() {
            const total = cents(this.quote?.total);
            if (!total) return [];

            const options = new Set([total]);
            for (const note of [10000, 50000, 100000, 500000]) {
                options.add(Math.ceil(total / note) * note);
            }

            return [...options].sort((a, b) => a - b).slice(0, 4).map(fromCents);
        },

        get change() {
            return fromCents(cents(this.pay.cash_received) - cents(this.quote?.total));
        },

        get payProblem() {
            const total = cents(this.quote?.total);
            const pay = this.pay;

            if (pay.method === 'cash') {
                if (pay.cash_received === '' || pay.cash_received === null) return this.messages.enterCash;
                if (cents(pay.cash_received) < total) return this.messages.cashShort;
            }

            if (pay.method === 'card' && cents(pay.card_amount) !== total) return this.messages.cardMismatch;

            if (pay.method === 'mixed') {
                if (!(cents(pay.cash_amount) > 0) || !(cents(pay.card_amount) > 0)) return this.messages.mixedBoth;
                if (cents(pay.cash_amount) + cents(pay.card_amount) !== total) return this.messages.mixedMismatch;
            }

            return null;
        },

        async submitCheckout() {
            if (this.payProblem || this.pay.submitting) return;
            this.pay.submitting = true;
            this.pay.errors = {};

            const { ok, status, data } = await this.api('POST', this.urls.checkout, {
                ...this.payload(),
                payment_method: this.pay.method,
                cash_received: this.pay.method === 'cash' ? this.pay.cash_received : null,
                cash_amount: this.pay.method === 'mixed' ? this.pay.cash_amount : null,
                card_amount: this.pay.method === 'card' ? this.pay.card_amount : (this.pay.method === 'mixed' ? this.pay.card_amount : null),
            }).catch(() => ({ ok: false, status: 0, data: {} }));

            this.pay.submitting = false;

            if (!ok) {
                this.pay.errors = status === 422 ? data.errors : { checkout: [data.message || this.messages.server] };
                // Cart problems (e.g. a product was just archived) also show on the cart.
                if (status === 422 && Object.keys(data.errors).some((k) => k.startsWith('items') || k.startsWith('order_') || k.startsWith('customer_') || k === 'delivery_fee' || k === 'save_customer')) {
                    this.requestQuote();
                }
                return;
            }

            this.receipt = {
                html: data.receipt_html,
                url: data.receipt_url,
                invoice: data.sale.invoice_number,
                change: data.sale.change_amount,
                orderType: data.sale.order_type,
                table: data.sale.table,
                kots: data.kitchen_orders,
                printUrl: data.print_url,
            };
            this.$dispatch('close-modal', 'pos-checkout');
            this.lines = [];
            this.resetCustomer();
            this.resetOrder();
            this.orderDiscount = { type: '', value: '' };
            this.quote = null;
            this.$nextTick(() => this.$dispatch('open-modal', 'pos-receipt'));

            this.autoPrintOrder();
        },

        /**
         * Print the placed order in one job: customer bill + kitchen order ticket(s), each on
         * its own page. only = 'receipt' or 'kitchen' reprints just one of them. Uses a hidden
         * frame on this page, so popup blockers don't interfere.
         */
        printOrder(only = null) {
            if (!this.receipt?.printUrl) return Promise.resolve();

            // "Print both" follows the printer layout setting: one job, or receipt then KOT.
            if (only === null && this.receipt.kots.length && this.printSettings.layout === 'separate') {
                return this.printJobs(['receipt', 'kitchen']);
            } else {
                return this.printJobs([only]);
            }
        },

        /** After checkout: print what Settings → POS Settings asks for, then auto-reset. */
        async autoPrintOrder() {
            const receipt = this.printSettings.receipt;
            const kot = this.printSettings.kot && this.receipt?.kots.length > 0;

            // KOT printing is on but the server created no KOT — products likely have no kitchen station.
            if (this.printSettings.kot && !kot) {
                this.notify('No KOT created — assign a kitchen station to your products in Settings.', 'error');
            }

            if (receipt && kot) {
                await this.printOrder();
            } else if (receipt || kot) {
                await this.printJobs([receipt ? 'receipt' : 'kitchen']);
            }

            // Start the new-sale timer only after printing finishes so newSale()
            // never nulls this.receipt while a print job is still in progress.
            if (receipt || kot) {
                setTimeout(() => { if (this.receipt) this.newSale(); }, 3000);
            }
        },

        /**
         * Print each job (null = receipt + KOT together) from the hidden frame, one after the
         * other. print() blocks until its dialog closes, so the next job starts afterwards.
         * On Electron desktop, uses silent printing instead (no dialog).
         */
        printJobs(jobs) {
            // Desktop: silent direct print — no dialog, no frame
            if (window.desktop?.printSilent) {
                return this.printJobsSilent(jobs);
            }

            const queue = [...jobs];
            const frame = this.$refs.printFrame;
            this.receipt.printed = queue.length > 1 || queue[0] === null ? 'both' : queue[0];

            const next = () => {
                if (!queue.length) {
                    frame.onload = null;
                    return;
                }

                const only = queue.shift();
                const url = new URL(this.receipt.printUrl, window.location.origin);
                if (only) url.searchParams.set('only', only);
                url.searchParams.set('t', `${Date.now()}${queue.length}`); // always reload, even for a reprint

                frame.onload = () => {
                    try {
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                    } catch (e) {
                        // Frame blocked (unusual): fall back to a tab that prints itself.
                        url.searchParams.set('autoprint', 1);
                        window.open(url, '_blank', 'noopener');
                    }
                    setTimeout(next, 50);
                };
                frame.src = url.toString();
            };

            next();
        },

        /**
         * Desktop-only: print silently with no dialog.
         * Receipt goes to the receipt printer; KOT goes to the KOT printer.
         * If null is in jobs (combined), we split into receipt then kitchen.
         */
        async printJobsSilent(jobs) {
            // Capture printUrl NOW — this.receipt may be nulled by newSale() mid-loop.
            const printUrl = this.receipt?.printUrl;
            if (!printUrl) return;

            const makeUrl = (only) => {
                const url = new URL(printUrl, window.location.origin);
                if (only) url.searchParams.set('only', only);
                url.searchParams.set('t', Date.now());
                return url.toString();
            };

            // Expand null (combined) → individual jobs so each can use its own printer
            const expanded = jobs[0] === null ? ['receipt', 'kitchen'] : [...jobs];
            if (this.receipt) {
                this.receipt.printed = expanded.length > 1 ? 'both' : (expanded[0] ?? 'both');
            }

            for (const job of expanded) {
                const printerName = job === 'kitchen' ? this.printers.kot : this.printers.receipt;
                try {
                    await window.desktop.printSilent({ url: makeUrl(job), printerName: printerName || null });
                } catch (e) {
                    const label = job === 'kitchen' ? 'KOT' : 'Receipt';
                    this.notify(`${label} print failed: ${e.message}`, 'error');
                    console.error(`[print] ${label} failed:`, e.message);
                }
            }
        },

        newSale() {
            this.$dispatch('close-modal', 'pos-receipt');
            this.receipt = null;
            this.$nextTick(() => this.$refs.barcode?.focus());
        },

        // ---------------------------------------------------------------- keyboard

        shortcut(event) {
            if (event.key === 'F2') {
                event.preventDefault();
                this.$refs.search?.focus();
            } else if (event.key === 'F4') {
                event.preventDefault();
                this.$refs.barcode?.focus();
            } else if (event.key === 'F9') {
                event.preventDefault();
                this.openCheckout();
            }
        },
    };
});
