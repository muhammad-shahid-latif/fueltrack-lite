<x-layouts.app title="WhatsApp" subtitle="Daily closing summary sent to the owner's WhatsApp through Meta's official Cloud API">
    <div class="row g-4">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('whatsapp.update') }}" novalidate class="card"
                  x-data="{ enabled: @js((bool) old('enabled', $enabled)), mode: @js(old('mode', $mode)) }">
                @csrf
                @method('PUT')

                <div class="card-body p-4">
                    <label class="wa-switch" :class="enabled && 'is-on'" for="wa_enabled">
                        <span class="wa-switch-icon"><i class="bi bi-whatsapp"></i></span>
                        <span class="flex-grow-1">
                            <strong class="d-block">Send the daily summary on WhatsApp</strong>
                            <span class="small text-secondary">Every time a day is closed: fuel sold, sales, cash counted and stock left.</span>
                        </span>
                        <input type="hidden" name="enabled" value="0">
                        <span class="form-check form-switch m-0">
                            <input class="form-check-input access-switch" type="checkbox" role="switch" id="wa_enabled" name="enabled" value="1" x-model="enabled">
                        </span>
                    </label>

                    <div class="mt-4">
                        <label for="recipients" class="form-label fw-semibold">Send to these numbers</label>
                        <textarea id="recipients" name="recipients" rows="2" class="form-control ft-num @error('recipients') is-invalid @enderror"
                                  placeholder="0300-1234567">{{ old('recipients', collect($recipients)->map(fn ($n) => '+'.$n)->join("\n")) }}</textarea>
                        @error('recipients') <div class="invalid-feedback">{{ $message }}</div> @else
                            <div class="form-text">One number per line. 03xx numbers are treated as Pakistani (+92).</div>
                        @enderror
                    </div>

                    <hr class="my-4">

                    <div class="small fw-semibold text-secondary text-uppercase mb-3" style="letter-spacing: .06em;">Connection to Meta (from WhatsApp → API Setup)</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="phone_number_id" class="form-label">Phone Number ID</label>
                            <input type="text" id="phone_number_id" name="phone_number_id" value="{{ old('phone_number_id', $phoneNumberId) }}" inputmode="numeric" autocomplete="off"
                                   class="form-control ft-num @error('phone_number_id') is-invalid @enderror" placeholder="e.g. 106540352242922">
                            @error('phone_number_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="token" class="form-label">Access token</label>
                            <input type="password" id="token" name="token" autocomplete="new-password"
                                   class="form-control @error('token') is-invalid @enderror" placeholder="{{ $tokenHint ? 'Saved ('.$tokenHint.'). Leave empty to keep' : 'Paste the permanent token' }}">
                            @error('token') <div class="invalid-feedback">{{ $message }}</div> @else
                                <div class="form-text"><i class="bi bi-lock-fill me-1"></i>Stored encrypted. Never shown again.</div>
                            @enderror
                        </div>
                    </div>

                    <div class="small fw-semibold text-secondary text-uppercase mt-4 mb-2" style="letter-spacing: .06em;">Message type</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="wa-mode h-100" :class="mode === 'template' && 'active'">
                                <input type="radio" name="mode" value="template" x-model="mode" class="form-check-input me-2">
                                <strong>Approved template</strong> <span class="badge text-bg-success ms-1">Recommended</span>
                                <span class="d-block small text-secondary mt-1">Arrives every day, any time. Needs a template approved by Meta (guide below).</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="wa-mode h-100" :class="mode === 'text' && 'active'">
                                <input type="radio" name="mode" value="text" x-model="mode" class="form-check-input me-2">
                                <strong>Free text</strong>
                                <span class="d-block small text-secondary mt-1">For quick testing. Only arrives if the owner messaged the business number in the last 24 hours.</span>
                            </label>
                        </div>
                    </div>

                    <div class="row g-3 mt-1" x-show="mode === 'template'">
                        <div class="col-md-8">
                            <label for="template" class="form-label">Template name</label>
                            <input type="text" id="template" name="template" value="{{ old('template', $template) }}" class="form-control @error('template') is-invalid @enderror">
                            @error('template') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="language" class="form-label">Language</label>
                            <input type="text" id="language" name="language" value="{{ old('language', $language) }}" class="form-control @error('language') is-invalid @enderror">
                            @error('language') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-transparent d-flex flex-wrap align-items-center gap-2 p-4">
                    <span class="small text-secondary me-auto"><i class="bi bi-info-circle me-1"></i>Meta may charge per message. See Meta's WhatsApp pricing.</span>
                    <button type="submit" class="btn btn-warning px-4"><i class="bi bi-check2-circle me-1"></i>Save</button>
                </div>
            </form>

            <div class="card mt-4">
                <div class="card-header">How to connect (one time, about 15 minutes)</div>
                <div class="card-body small">
                    <ol class="wa-steps mb-0">
                        <li>Open <strong>developers.facebook.com</strong> → <em>My Apps</em> → <em>Create app</em> → type <em>Business</em>, then add the <strong>WhatsApp</strong> product.</li>
                        <li>In <em>WhatsApp → API Setup</em>, copy the <strong>Phone Number ID</strong> into the box above. While testing with Meta's free test number, add the owner's number under <em>To</em> and confirm the code sent to it.</li>
                        <li>Make a token that does not expire: <strong>business.facebook.com</strong> → <em>Business settings → Users → System users</em> → add an admin system user → <em>Assign assets</em> (the app and the WhatsApp account) → <em>Generate token</em> with <code>whatsapp_business_messaging</code> and <code>whatsapp_business_management</code>. Paste it above.</li>
                        <li>Create the template: <em>WhatsApp Manager → Message templates → Create</em> → category <strong>Utility</strong>, name <code>{{ $template }}</code>, language <strong>English</strong>, and this body (then submit; approval usually takes minutes):
                            <pre class="wa-template mt-2 mb-0">{{ $templateBody }}</pre>
                        </li>
                        <li>Switch on, <strong>Save</strong>, then press <strong>Send test now</strong>.</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <span>Message preview</span>
                    @if (\App\Support\WhatsAppSettings::ready())
                        <span class="badge text-bg-success ms-auto"><i class="bi bi-check2 me-1"></i>Active</span>
                    @elseif ($enabled)
                        <span class="badge text-bg-warning ms-auto">Incomplete</span>
                    @else
                        <span class="badge text-bg-secondary ms-auto">Off</span>
                    @endif
                </div>
                <div class="card-body wa-chat">
                    @if ($preview)
                        <div class="wa-bubble">{!! preg_replace('/\*(.+?)\*/', '<strong>$1</strong>', e($preview)) !!}<span class="wa-time">{{ now()->format('h:i A') }}</span></div>
                        <div class="small text-secondary mt-2 text-center">Last closed day ({{ $latest->business_date->format('d M Y') }}). The template carries the same facts.</div>
                    @else
                        <div class="text-center text-secondary small py-4">Close a day to see what the owner will receive.</div>
                    @endif
                </div>
                <div class="card-footer bg-transparent p-3">
                    <form method="POST" action="{{ route('whatsapp.test') }}">
                        @csrf
                        <button type="submit" class="btn btn-success w-100" @disabled(! \App\Support\WhatsAppSettings::configured() || $recipients === [] || ! $latest)>
                            <i class="bi bi-send me-1"></i>Send test now
                        </button>
                    </form>
                    @if (! \App\Support\WhatsAppSettings::configured() || $recipients === [])
                        <div class="small text-secondary text-center mt-2">Save a number, the Phone Number ID and the token first.</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">Recent messages</div>
                <ul class="list-group list-group-flush">
                    @forelse ($logs as $log)
                        <li class="list-group-item d-flex gap-3 align-items-start px-4 py-3">
                            <i @class(['bi fs-5', 'bi-check2-all text-success' => $log->sent(), 'bi-x-octagon text-danger' => ! $log->sent()])></i>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="fw-semibold">+{{ $log->recipient }} <span class="small text-secondary fw-normal">· {{ $log->purpose === 'test' ? 'Test' : 'Closing' }} {{ $log->business_date?->format('d M') }}</span></div>
                                <div class="small text-secondary">{{ $log->created_at->format('d M Y, h:i A') }}</div>
                                @if ($log->error)<div class="small text-danger mt-1">{{ $log->error }}</div>@endif
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary small px-4 py-3">Nothing sent yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
