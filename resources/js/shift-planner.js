/**
 * Setup → Shifts planner. Mirrors App\Support\ShiftSchedule: each shift runs
 * from its start to the next shift's start, so the day is always covered.
 */
const DAY = 1440;
const DEFAULT_NAMES = { 1: ['Full day'], 2: ['Day', 'Night'], 3: ['Morning', 'Evening', 'Night'] };

const toMinutes = (time) => {
    const [hour, minute] = String(time || '00:00').split(':').map(Number);

    return (hour || 0) * 60 + (minute || 0);
};

const toTime = (minutes) => {
    const value = ((minutes % DAY) + DAY) % DAY;

    return `${String(Math.floor(value / 60)).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`;
};

const clock = (time) => {
    const minutes = toMinutes(time);
    const hour = Math.floor(minutes / 60);
    const suffix = hour < 12 ? 'AM' : 'PM';

    return `${hour % 12 || 12}:${String(minutes % 60).padStart(2, '0')} ${suffix}`;
};

export default (plan) => ({
    count: plan.length,
    // All three slots are kept so switching 3 → 2 → 3 does not lose names.
    rows: [0, 1, 2].map((i) => ({ name: plan[i]?.name ?? DEFAULT_NAMES[3][i], start: plan[i]?.start ?? '' })),

    get active() {
        return this.rows.slice(0, this.count);
    },

    span(i) {
        if (this.count === 1) return DAY;

        const next = this.active[(i + 1) % this.count];

        return (toMinutes(next.start) - toMinutes(this.active[i].start) + DAY) % DAY;
    },

    end(i) {
        return this.count === 1 ? this.active[0].start : this.active[(i + 1) % this.count].start;
    },

    /** A shift's end is the next shift's start: setting one sets the other. */
    setEnd(i, value) {
        if (this.count === 1 || !/^\d{2}:\d{2}$/.test(value)) return;

        this.rows[(i + 1) % this.count].start = value;
    },

    duration(i) {
        const minutes = this.span(i);
        const hours = Math.floor(minutes / 60);
        const rest = minutes % 60;

        return rest ? `${hours} h ${rest} min` : `${hours} hours`;
    },

    /** Same checks as the server, so the owner sees the problem before saving. */
    get problem() {
        if (this.active.some((row) => !row.name.trim() || !/^\d{2}:\d{2}$/.test(row.start))) {
            return 'Every shift needs a name and a start time.';
        }

        const names = this.active.map((row) => row.name.trim().toLowerCase());
        if (new Set(names).size !== names.length) return 'Each shift needs a different name.';
        if (this.count === 1) return '';

        const spans = this.active.map((_, i) => this.span(i));
        if (spans.some((minutes) => minutes < 60)) return 'Each shift must be at least 1 hour long.';
        if (spans.reduce((a, b) => a + b, 0) !== DAY) return 'Start times must follow each other through the day (e.g. 06:00, 14:00, 22:00).';

        return '';
    },

    /** Picks a number of shifts and splits the day equally from the first start. */
    choose(count) {
        const previous = this.count;
        this.count = count;

        if (count !== previous) {
            this.equalize(true);
        }
    },

    equalize(renameDefaults = false) {
        const first = toMinutes(this.rows[0].start || '06:00');
        const step = DAY / this.count;

        this.rows.forEach((row, i) => {
            if (i >= this.count) return;

            row.start = toTime(first + i * step);

            // Give standard names when the old one was a standard name of another layout.
            const standard = Object.values(DEFAULT_NAMES).flat();
            if (renameDefaults && (!row.name.trim() || standard.includes(row.name.trim()))) {
                row.name = DEFAULT_NAMES[this.count][i];
            }
        });
    },

    /** Bar pieces for the 24-hour timeline; a shift past midnight is drawn in two parts. */
    get segments() {
        if (this.problem) return [];

        return this.active.flatMap((row, i) => {
            const start = toMinutes(row.start);
            const length = this.span(i);
            const piece = (from, minutes) => ({ slot: i, name: row.name, left: (from / DAY) * 100, width: (minutes / DAY) * 100 });

            return start + length <= DAY ? [piece(start, length)] : [piece(start, DAY - start), piece(0, start + length - DAY)];
        });
    },

    clock,
});
