/**
 * Add pump: pick how many nozzles the machine has (1, 2, 4 or any up to 8)
 * and fill each one's fuel, tank and current meter.
 */
const DEFAULT_FUEL_ORDER = { 1: [0], 2: [0, 1], 4: [0, 0, 1, 1] };

let nextKey = 0;

export default (fuels, tanks, old = []) => ({
    fuels,
    tanks,
    rows: [],

    init() {
        if (old.length) {
            this.rows = old.map((row) => this.makeRow(Number(row.fuel_product_id) || this.fuels[0]?.id, row.tank_id ?? '', row.opening_meter ?? ''));
        } else if (this.fuels.length) {
            this.setCount(2);
        }
    },

    get count() {
        return this.rows.length;
    },

    makeRow(fuel, tank = null, meter = '') {
        return { key: nextKey++, fuel, tank: tank === null ? this.firstTank(fuel) : tank, meter };
    },

    /** Keeps rows already filled and adds or removes from the end. */
    setCount(count) {
        if (!this.fuels.length) return;

        const target = Math.max(0, Math.min(8, count));
        const order = DEFAULT_FUEL_ORDER[target];

        // Nothing typed yet: lay the machine out afresh (a quad is petrol, petrol, diesel, diesel).
        if (order && this.rows.every((row) => row.meter === '')) {
            this.rows = [];
        }

        if (target > this.rows.length) {
            for (let i = this.rows.length; i < target; i++) {
                const index = order ? order[i] : i % this.fuels.length;
                const fuel = (this.fuels[index] ?? this.fuels[0]).id;
                this.rows.push(this.makeRow(fuel));
            }
        } else {
            this.rows.splice(target);
        }
    },

    tanksFor(fuel) {
        return this.tanks.filter((tank) => tank.fuel === fuel);
    },

    firstTank(fuel) {
        return this.tanksFor(fuel)[0]?.id ?? '';
    },

    gradeOf(fuel) {
        return this.fuels.find((f) => f.id === fuel)?.grade ?? '';
    },
});
