/**
 * "Give fuel" popup of the owed-fuel register (credits/partials/owed-fuel).
 * The form posts normally; Turbo refreshes the page in place afterwards.
 */
export default () => ({
    slip: { action: '', customer: '', fuel: '', slip: '', vehicle: '', remaining: 0, rate: 0 },
    quantity: '',

    get amount() {
        const value = parseFloat(String(this.quantity).replace(/,/g, ''));

        return Number.isFinite(value) ? Math.round(value * 100) / 100 : 0;
    },

    get valid() {
        return this.amount > 0 && this.amount <= this.slip.remaining + 0.001;
    },

    get left() {
        return Math.max(0, Math.round((this.slip.remaining - this.amount) * 100) / 100);
    },

    open(slip) {
        this.slip = slip;
        this.quantity = String(slip.remaining);

        const modal = document.getElementById('owedGiveModal');
        modal.addEventListener('shown.bs.modal', () => modal.querySelector('#give_quantity')?.select(), { once: true });
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    },

    liters(value) {
        return Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' L';
    },
});
