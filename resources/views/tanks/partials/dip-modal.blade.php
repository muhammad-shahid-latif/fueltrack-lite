{{--
    Dip-reading popup shared by every tank card. Driven by tankBoard() on tanks/index.
    Teleported to <body> so the page's 3D transforms cannot trap the fixed modal.
--}}
<template x-teleport="body">
    <div class="modal fade dip-modal" id="dipModal" tabindex="-1" aria-labelledby="dipModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" novalidate @submit.prevent="saveDip()">
                <div class="modal-header">
                    <div style="min-width: 0;">
                        <h2 class="modal-title h5 mb-0 text-truncate" id="dipModalTitle">
                            <i class="bi bi-rulers me-2"></i>Dip reading<span x-show="dip.tank" x-text="dip.tank ? ' · ' + dip.tank.name : ''"></span>
                        </h2>
                        <div class="small text-secondary" x-show="dip.tank" x-text="dip.tank ? `${dip.tank.fuel} · full capacity ${dip.tank.capacity_label}` : ''"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div x-show="dip.loading && !dip.tank" class="text-center py-5 text-secondary">
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Reading system stock…
                    </div>

                    {{-- x-if, not x-show: Bootstrap's !important display utilities would override x-show. --}}
                    <template x-if="dip.loadError">
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-0">
                            <i class="bi bi-wifi-off"></i>
                            <div class="flex-grow-1" x-text="dip.loadError"></div>
                            <button type="button" class="btn btn-sm btn-light" @click="loadBook()">Retry</button>
                        </div>
                    </template>

                    <div x-show="dip.tank && !dip.loadError">
                        <template x-if="dip.dayClosed">
                            <div class="alert alert-warning small d-flex gap-2">
                                <i class="bi bi-lock-fill"></i>
                                <div>Today's business day is closed, so a dip cannot be saved. You can still compare the reading below.</div>
                            </div>
                        </template>

                        <div class="row g-3 align-items-stretch">
                            <div class="col-6">
                                <div class="led-screen h-100">
                                    <div class="led-label d-flex justify-content-between align-items-center">
                                        <span>System stock now</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 dip-refresh" title="Read again" aria-label="Read system stock again"
                                                @click="loadBook()" :disabled="dip.loading || dip.saving">
                                            <i class="bi bi-arrow-clockwise" :class="{ 'spin': dip.loading }"></i>
                                        </button>
                                    </div>
                                    <div class="led-value" x-text="dip.bookLabel"></div>
                                    <div class="led-label mt-1" x-text="dip.at ? 'as of ' + dip.at : ''"></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <label for="dipMeasured" class="form-label small fw-semibold mb-1">Measured by dip rod (L)</label>
                                <input type="text" inputmode="decimal" autocomplete="off" id="dipMeasured"
                                       class="form-control form-control-lg ft-num dip-input" placeholder="e.g. 900"
                                       x-model="dip.measured" :readonly="dip.result !== null"
                                       :class="{ 'is-invalid': dip.error || (preview && preview.overCapacity) }">
                                <div class="form-text">From the tank's calibration chart.</div>
                            </div>
                        </div>

                        {{-- Verdict: live preview while typing, server result after saving. --}}
                        <div class="dip-verdict mt-3" x-show="preview && !dip.result" :class="preview && !preview.overCapacity ? verdictClass(preview.status) : 'is-loss'">
                            <template x-if="preview && preview.overCapacity">
                                <div><i class="bi bi-exclamation-octagon-fill me-2"></i>More than the full capacity of <span x-text="dip.tank.capacity_label"></span>. Check the reading.</div>
                            </template>
                            <template x-if="preview && !preview.overCapacity && preview.status === 'match'">
                                <div><i class="bi bi-check-circle-fill me-2"></i><strong>Readings match.</strong> Your dip equals the system stock.</div>
                            </template>
                            <template x-if="preview && !preview.overCapacity && preview.status !== 'match'">
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <div>
                                        <i class="bi me-2" :class="preview.status === 'gain' ? 'bi-arrow-up-circle-fill' : 'bi-arrow-down-circle-fill'"></i>
                                        <strong x-text="preview.status === 'gain' ? 'Difference: more fuel than system' : 'Difference: less fuel than system'"></strong>
                                    </div>
                                    <div class="text-end ft-num">
                                        <div class="fs-5 fw-bold" x-text="liters(preview.variance, true)"></div>
                                        <div class="small" x-show="preview.percent !== null" x-text="(preview.percent > 0 ? '+' : '') + preview.percent.toFixed(2) + '%'"></div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="dip-verdict mt-3" x-show="dip.result" :class="dip.result ? verdictClass(dip.result.status) : ''">
                            <div class="d-flex gap-2">
                                <i class="bi" :class="dip.result && dip.result.status === 'match' ? 'bi-check-circle-fill' : 'bi-clipboard-check-fill'"></i>
                                <div>
                                    <div class="fw-semibold" x-text="dip.result ? 'Saved · ' + dip.result.message : ''"></div>
                                    <div class="small mt-1" x-show="dip.result && dip.result.status !== 'match'">
                                        Recorded as a <span x-text="dip.result ? dip.result.status : ''"></span> in the tank ledger.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <template x-if="dip.error">
                            <div class="invalid-feedback d-block" x-text="dip.error"></div>
                        </template>

                        <div class="mt-3" x-show="!dip.result">
                            <label for="dipNotes" class="form-label small text-secondary mb-1">Note (optional)</label>
                            <input type="text" id="dipNotes" maxlength="500" class="form-control form-control-sm" x-model="dip.notes" placeholder="e.g. Morning dip before opening">
                            <div class="form-text">Saving keeps this dip in the ledger and corrects the system stock to your reading.</div>
                        </div>

                        <template x-if="dip.history.length">
                            <div class="mt-4">
                                <div class="small fw-semibold text-secondary mb-2">Recent dips</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 dip-history">
                                        <thead><tr><th>When</th><th class="text-end">Dip</th><th class="text-end">System</th><th class="text-end">Difference</th></tr></thead>
                                        <tbody>
                                            <template x-for="row in dip.history" :key="row.id">
                                                <tr>
                                                    <td class="small"><span x-text="row.date_label"></span> <span class="text-secondary" x-text="row.time_label"></span></td>
                                                    <td class="text-end ft-num" x-text="row.measured_label"></td>
                                                    <td class="text-end ft-num text-secondary" x-text="row.book_label"></td>
                                                    <td class="text-end ft-num fw-semibold" :class="{ 'amount-in': row.status === 'gain', 'amount-out': row.status === 'loss', 'text-success': row.status === 'match' }"
                                                        x-text="row.status === 'match' ? 'Match' : row.variance_label"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="modal-footer">
                    <template x-if="!dip.result">
                        <div class="d-flex gap-2 w-100 justify-content-end">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning" :disabled="!canSave">
                                <span x-show="dip.saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                <i x-show="!dip.saving" class="bi bi-check2-circle me-1"></i>Save dip reading
                            </button>
                        </div>
                    </template>
                    <template x-if="dip.result">
                        <div class="d-flex gap-2 w-100 justify-content-end">
                            <button type="button" class="btn btn-light" @click="newReading()"><i class="bi bi-arrow-repeat me-1"></i>Take another reading</button>
                            <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Done</button>
                        </div>
                    </template>
                </div>
            </form>
        </div>
    </div>
</template>
