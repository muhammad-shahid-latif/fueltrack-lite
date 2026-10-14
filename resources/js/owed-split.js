/**
 * "Part given now, rest later" on a fuel credit slip.
 *
 * Used inside a credit form whose x-data has `qty` (liters on the slip),
 * `fuel` (selected product id) and `rates` (price per product). Only
 * `given_quantity` is posted; the server works out the owed liters.
 */
const parse = (value) => {
    const number = parseFloat(String(value ?? '').replace(/,/g, ''));

    return Number.isFinite(number) ? number : null;
};

const round = (value) => Math.round(value * 100) / 100;

const plain = (value) => String(round(value));

export default (initialGiven = null) => ({
    later: initialGiven !== null && initialGiven !== '',
    given: initialGiven ?? '',
    owedInput: '',

    init() {
        this.syncOwed();

        // Stop the parent form while the split is incomplete (the server checks it too).
        this.$el.closest('form')?.addEventListener('submit', (event) => {
            if (this.later && this.fuel !== '' && !this.splitReady) {
                event.preventDefault();
                this.submitting = false;
                this.$refs.givenInput?.focus();
            }
        });

        // The slip liters can change after the split is set: keep "given now", recompute "later".
        this.$watch('qty', () => this.syncOwed());
    },

    get slipLiters() {
        return parse(this.qty) ?? 0;
    },

    get givenLiters() {
        return parse(this.given);
    },

    get owedLiters() {
        return this.givenLiters === null ? null : round(this.slipLiters - this.givenLiters);
    },

    /** Message when the split cannot be saved, or '' when it is fine. */
    get splitProblem() {
        if (!this.later || this.fuel === '') return '';
        if (this.givenLiters === null) return 'Enter the liters given now.';
        if (this.givenLiters < 0 || this.owedLiters < 0) return 'Given now cannot be more than the liters on the slip.';
        if (this.owedLiters === 0) return 'Nothing is left for later. Switch this off if all fuel was given.';

        return '';
    },

    get splitReady() {
        return this.splitProblem === '';
    },

    get givenShare() {
        return this.slipLiters > 0 && this.givenLiters !== null ? Math.min(100, Math.max(0, (this.givenLiters / this.slipLiters) * 100)) : 0;
    },

    rate() {
        return this.rates[this.fuel] ?? 0;
    },

    onGiven() {
        this.syncOwed();
    },

    onOwed() {
        const owed = parse(this.owedInput);
        this.given = owed === null ? '' : plain(this.slipLiters - owed);
    },

    syncOwed() {
        this.owedInput = this.owedLiters === null ? '' : plain(this.owedLiters);
    },

    toggle() {
        if (this.later && this.given === '' && this.slipLiters > 0) {
            this.given = plain(this.slipLiters / 2);
        }

        this.syncOwed();
        this.$nextTick(() => this.later && this.$refs.givenInput?.focus());
    },

    money(value) {
        return 'Rs. ' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
    },

    liters(value) {
        return Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' L';
    },
});
