{{--
    "Part given now, rest later" switch for a fuel credit slip.
    Needs a parent x-data with `qty`, `fuel` and `rates`; see resources/js/owed-split.js.
    $compact: smaller layout for the customer page sidebar.
--}}
@php($compact = $compact ?? false)
<div class="owed-split" x-data="owedSplit(@js(old('given_quantity')))" x-show="fuel !== ''" :class="later && 'is-on'">
    <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
        <input class="form-check-input owed-switch" type="checkbox" role="switch" id="owed_later_{{ $compact ? 'c' : 'f' }}" x-model="later" @change="toggle()">
        <label class="form-check-label" for="owed_later_{{ $compact ? 'c' : 'f' }}">
            <span class="fw-semibold">Part of this fuel will be given later</span>
            <span class="d-block small text-secondary">The slip is billed in full; the rest is filled on another day.</span>
        </label>
    </div>

    <div x-show="later" x-cloak class="mt-3">
        <div class="row g-2">
            <div class="{{ $compact ? 'col-6' : 'col-sm-6' }}">
                <label for="given_quantity_{{ $compact ? 'c' : 'f' }}" class="form-label small fw-semibold mb-1"><i class="bi bi-fuel-pump-fill text-success me-1"></i>Given now (L)</label>
                <input type="text" inputmode="decimal" id="given_quantity_{{ $compact ? 'c' : 'f' }}" name="given_quantity" x-ref="givenInput"
                       x-model="given" @input="onGiven()" :disabled="!later || fuel === ''"
                       class="form-control {{ $compact ? '' : 'form-control-lg' }} ft-num owed-given @error('given_quantity') is-invalid @enderror"
                       :class="later && splitProblem && given !== '' && 'is-invalid'" placeholder="e.g. 10">
            </div>
            <div class="{{ $compact ? 'col-6' : 'col-sm-6' }}">
                <label for="owed_input_{{ $compact ? 'c' : 'f' }}" class="form-label small fw-semibold mb-1"><i class="bi bi-hourglass-split text-warning me-1"></i>To give later (L)</label>
                <input type="text" inputmode="decimal" id="owed_input_{{ $compact ? 'c' : 'f' }}" x-model="owedInput" @input="onOwed()"
                       class="form-control {{ $compact ? '' : 'form-control-lg' }} ft-num owed-later" placeholder="e.g. 10">
            </div>
        </div>

        <div class="owed-split-bar mt-3" x-show="slipLiters > 0" aria-hidden="true">
            <span class="is-given" :style="`width: ${givenShare}%`"></span>
        </div>
        <template x-if="slipLiters > 0 && splitReady">
            <div class="d-flex justify-content-between small mt-1 ft-num">
                <span class="text-success fw-semibold" x-text="`${liters(givenLiters)} now · ${money(Math.round(givenLiters * rate() * 100) / 100)}`"></span>
                <span class="owed-later-text fw-semibold" x-text="`${liters(owedLiters)} later · ${money(Math.round(owedLiters * rate() * 100) / 100)}`"></span>
            </div>
        </template>

        <div class="small text-danger mt-2" x-show="splitProblem && given !== ''" x-text="splitProblem"></div>
        @error('given_quantity') <div class="small text-danger mt-2">{{ $message }}</div> @enderror
    </div>
</div>
