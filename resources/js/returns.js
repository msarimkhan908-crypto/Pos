import Alpine from 'alpinejs';

/**
 * Return form: quantity per sale line and a live refund estimate. The estimate uses the same
 * formula as App\Services\ReturnService, but the server recalculates everything on save.
 */
const cents = (value) => Math.round((parseFloat(value) || 0) * 100);

Alpine.data('returnForm', ({ rows = [], old = {}, currency = 'USD', symbol = '' }) => {
    const money = new Intl.NumberFormat('en', { style: 'currency', currency, minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return {
        rows: rows.map((row) => ({ ...row, quantity: Number(old[row.id] ?? 0) })),

        format(amountCents) {
            const value = amountCents / 100;
            return symbol ? `${symbol} ${value.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : money.format(value);
        },

        /** refund = round(paid × (already + q) / sold) − round(paid × already / sold), in cents */
        refundCents(row) {
            const q = Math.max(0, Math.min(Number(row.quantity) || 0, row.returnable));
            const share = (units) => Math.round((cents(row.paid) * units) / row.sold);
            return share(row.returned + q) - share(row.returned);
        },

        get total() {
            return this.rows.reduce((sum, row) => sum + this.refundCents(row), 0);
        },

        get units() {
            return this.rows.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0);
        },

        returnAll() {
            this.rows.forEach((row) => (row.quantity = row.returnable));
        },

        clearAll() {
            this.rows.forEach((row) => (row.quantity = 0));
        },
    };
});
