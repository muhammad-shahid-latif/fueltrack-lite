/**
 * Khata register helpers: the "Receive payment" popup and the customer search.
 */
const money = (value) => 'Rs. ' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });

export function receivePayment(customers, actionTemplate, reopenFor = null, amount = '') {
    return {
        customers,
        customerId: reopenFor || null,
        amount,

        init() {
            // A failed save comes back to this page: open the popup again with the entered values.
            if (reopenFor) {
                this.$nextTick(() => this.modal().show());
            }

            // Buttons may preselect a customer with data-customer-id. The popup is
            // teleported to <body>, so it exists only after this component's first tick.
            this.$nextTick(() => {
                document.getElementById('receivePaymentModal')?.addEventListener('show.bs.modal', (event) => {
                    const id = Number(event.relatedTarget?.dataset.customerId);

                    if (id) {
                        this.customerId = id;
                        this.amount = '';
                    }
                });
            });
        },

        modal() {
            return window.bootstrap.Modal.getOrCreateInstance(document.getElementById('receivePaymentModal'));
        },

        get customer() {
            return this.customers.find((c) => c.id === this.customerId) ?? null;
        },

        get action() {
            return this.customerId ? actionTemplate.replace('__ID__', this.customerId) : '#';
        },

        get value() {
            const number = parseFloat(String(this.amount).replace(/,/g, ''));

            return Number.isFinite(number) ? number : 0;
        },

        get ready() {
            return this.customer !== null && this.value > 0 && this.value <= this.customer.balance + 0.005;
        },

        money,
    };
}

export function customerFilter(total) {
    return {
        q: '',
        show: 'all',
        total,
        visible: total,

        init() {
            this.$watch('q', () => this.count());
            this.$watch('show', () => this.count());
        },

        /** Whether a row (its data-* attributes) passes the search and the chip filter. */
        matches(data) {
            const term = this.q.trim().toLowerCase();

            if (term && !data.search.includes(term)) return false;

            return {
                all: true,
                owes: data.owes === '1',
                overdue: data.overdue === '1',
                over: data.over === '1',
                inactive: data.inactive === '1',
            }[this.show];
        },

        count() {
            this.$nextTick(() => {
                this.visible = this.$root.querySelectorAll('tbody tr[data-search]:not([style*="display: none"])').length;
            });
        },
    };
}
