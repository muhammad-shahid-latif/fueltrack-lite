<x-layouts.app title="Shifts" subtitle="How many shifts the station runs and when each one starts">
    <div class="row g-4">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('shifts.update') }}" novalidate class="card"
                  x-data="shiftPlanner(@js(old('shifts') ? array_values(old('shifts')) : $plan))">
                @csrf
                @method('PUT')

                <div class="card-header">
                    <div>
                        <div class="fw-bold">Shift plan</div>
                        <div class="small text-secondary fw-normal">Each shift ends when the next one starts, so the whole day is covered. Changing an end time moves the next shift's start.</div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="small fw-semibold text-secondary text-uppercase mb-2" style="letter-spacing: .06em;">1 · How many shifts?</div>
                    <div class="row g-3 mb-4">
                        @foreach ([1 => ['24 hours', 'One team runs the whole day'], 2 => ['12 hours each', 'Day and night teams'], 3 => ['8 hours each', 'Morning, evening and night']] as $n => [$length, $hint])
                            <div class="col-md-4">
                                <button type="button" class="shift-count-card w-100" :class="count === {{ $n }} && 'active'" @click="choose({{ $n }})" :aria-pressed="count === {{ $n }}">
                                    <span class="shift-count-number">{{ $n }}</span>
                                    <span class="shift-count-text">
                                        <strong>{{ $n }} {{ str('shift')->plural($n) }}</strong>
                                        <span>{{ $length }} · {{ $hint }}</span>
                                    </span>
                                    <span class="shift-count-bars" aria-hidden="true">
                                        @for ($i = 0; $i < $n; $i++)<span class="slot-{{ $i }}"></span>@endfor
                                    </span>
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div class="small fw-semibold text-secondary text-uppercase" style="letter-spacing: .06em;">2 · Names and start times</div>
                        <button type="button" class="btn btn-sm btn-light" x-show="count > 1" @click="equalize()"><i class="bi bi-distribute-horizontal me-1"></i>Equal hours from the first start</button>
                    </div>

                    <div class="vstack gap-2 mb-4">
                        <template x-for="(row, i) in active" :key="i">
                            <div class="shift-row" :class="'slot-' + i">
                                <span class="shift-row-dot"></span>
                                <div class="flex-grow-1" style="min-width: 160px;">
                                    <label class="form-label small mb-1" :for="'shift_name_' + i">Shift name</label>
                                    <input type="text" class="form-control fw-semibold" maxlength="30" :id="'shift_name_' + i" :name="`shifts[${i}][name]`" x-model="row.name">
                                </div>
                                <div style="width: 150px;">
                                    <label class="form-label small mb-1" :for="'shift_start_' + i">Starts at</label>
                                    <input type="time" class="form-control ft-num" :id="'shift_start_' + i" :name="`shifts[${i}][start]`" x-model="row.start" step="900">
                                </div>
                                {{-- Editing the end moves the next shift's start, so the day stays covered. --}}
                                <div style="width: 150px;">
                                    <label class="form-label small mb-1" :for="'shift_end_' + i">Ends at</label>
                                    <input type="time" class="form-control ft-num" :id="'shift_end_' + i" step="900"
                                           :value="end(i)" @change="setEnd(i, $event.target.value)" :disabled="count === 1"
                                           :title="count === 1 ? 'One shift runs the full 24 hours' : 'Also the start of the next shift'">
                                </div>
                                <div class="shift-row-length">
                                    <span class="badge rounded-pill" x-text="problem ? '—' : duration(i)"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="small fw-semibold text-secondary text-uppercase mb-2" style="letter-spacing: .06em;">3 · Your day</div>
                    <div class="shift-timeline mb-1">
                        <template x-for="(piece, k) in segments" :key="k">
                            <span class="shift-timeline-piece" :class="'slot-' + piece.slot" :style="`left: ${piece.left}%; width: ${piece.width}%`" :title="piece.name">
                                <span x-show="piece.width > 9" x-text="piece.name"></span>
                            </span>
                        </template>
                    </div>
                    <div class="shift-timeline-scale small text-secondary ft-num" aria-hidden="true">
                        <span>12 AM</span><span>6 AM</span><span>12 PM</span><span>6 PM</span><span>12 AM</span>
                    </div>

                    {{-- x-if, not x-show: Bootstrap's d-flex (!important) would override x-show. --}}
                    <template x-if="problem">
                        <div class="alert alert-danger mt-3 mb-0 d-flex gap-2">
                            <i class="bi bi-exclamation-triangle-fill"></i><span x-text="problem"></span>
                        </div>
                    </template>
                    @error('shifts') <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div> @enderror
                    @error('shifts.*') <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div> @enderror
                </div>

                <div class="card-footer bg-transparent d-flex flex-wrap align-items-center gap-2 p-4">
                    <span class="small text-secondary me-auto"><i class="bi bi-info-circle me-1"></i>Readings already saved keep their shift. New readings use this plan.</span>
                    <button type="submit" class="btn btn-warning px-4" :disabled="problem !== ''"><i class="bi bi-check2-circle me-1"></i>Save shifts</button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">Running now</div>
                <div class="card-body">
                    @foreach ($shifts as $i => $row)
                        <div @class(['shift-now d-flex align-items-center gap-3', 'is-current' => $row['shift'] === $current, 'slot-'.$i])>
                            <span class="shift-row-dot"></span>
                            <div class="flex-grow-1">
                                <div class="fw-bold">{{ $row['name'] }}</div>
                                <div class="small text-secondary ft-num">{{ $row['hours'] }}</div>
                            </div>
                            @if ($row['shift'] === $current)
                                <span class="badge text-bg-success">Now</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header">Where shifts are used</div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3 vstack gap-2">
                        <li>The <strong>meter reading</strong> form shows these shifts and picks the one running now.</li>
                        <li><strong>Nozzle meters</strong> shows one dot per shift: filled once its reading is in.</li>
                        <li>Tank and stock ledgers list sales <strong>per shift</strong>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
