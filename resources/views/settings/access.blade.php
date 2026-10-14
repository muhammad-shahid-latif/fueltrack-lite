<x-layouts.app title="Manager access" subtitle="Choose what the manager account can open. The owner always sees everything.">
    @php($modules = collect($groups)->collapse())
    @php($allowed = $modules->keys()->diff($blocked)->values())

    <form method="POST" action="{{ route('access.update') }}" x-data="{ allowed: @js($allowed), all: @js($modules->keys()), toggle(key) { this.allowed = this.allowed.includes(key) ? this.allowed.filter((k) => k !== key) : [...this.allowed, key] }, on(key) { return this.allowed.includes(key) } }">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="access-intro mb-4">
                    <span class="access-intro-icon"><i class="bi bi-person-lock"></i></span>
                    <div style="flex: 1 1 280px; min-width: 0;">
                        <div class="fw-bold">Switch a module off to hide it from the manager</div>
                        <div class="small text-secondary">It leaves the manager's menu, and its pages are blocked even with a direct link. Nothing is deleted: switch it back on any time.</div>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-light" @click="allowed = [...all]"><i class="bi bi-unlock me-1"></i>Allow all</button>
                        <button type="button" class="btn btn-sm btn-light" @click="allowed = []"><i class="bi bi-lock me-1"></i>Lock all</button>
                    </div>
                </div>

                @foreach ($groups as $group => $items)
                    <div class="small fw-semibold text-secondary text-uppercase mb-2" style="letter-spacing: .06em;">{{ $group }}</div>
                    <div class="row g-3 mb-4">
                        @foreach ($items as $key => $module)
                            <div class="col-md-6">
                                <label class="access-card h-100" :class="on('{{ $key }}') ? 'is-on' : 'is-off'" for="access_{{ $key }}">
                                    <span class="access-card-icon"><i class="bi bi-{{ $module['icon'] }}"></i></span>
                                    <span class="flex-grow-1">
                                        <span class="d-flex align-items-center gap-2">
                                            <strong>{{ $module['label'] }}</strong>
                                            <span class="access-state" x-text="on('{{ $key }}') ? 'Allowed' : 'Hidden'"></span>
                                        </span>
                                        <span class="d-block small text-secondary mt-1">{{ $module['can'] }}</span>
                                    </span>
                                    <span class="form-check form-switch m-0">
                                        <input class="form-check-input access-switch" type="checkbox" role="switch" id="access_{{ $key }}"
                                               name="allowed[]" value="{{ $key }}" :checked="on('{{ $key }}')" @change="toggle('{{ $key }}')">
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="col-xl-4">
                <div class="card mb-4 access-preview">
                    <div class="card-header">
                        <div>
                            <div class="fw-bold">Manager's menu</div>
                            <div class="small text-secondary fw-normal">What the manager will see after saving</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="access-preview-item"><i class="bi bi-speedometer2"></i>Dashboard</div>
                        @foreach ($modules as $key => $module)
                            <div class="access-preview-item" :class="!on('{{ $key }}') && 'is-hidden'">
                                <i class="bi bi-{{ $module['icon'] }}"></i>{{ $module['label'] }}
                                <i class="bi bi-eye-slash ms-auto" x-show="!on('{{ $key }}')"></i>
                            </div>
                        @endforeach
                        <div class="small text-secondary mt-3"><i class="bi bi-info-circle me-1"></i>Staff &amp; payroll, profit &amp; loss, audit trail, suppliers and settings are always owner-only.</div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Applies to</div>
                    <div class="card-body">
                        @forelse ($managers as $manager)
                            <div class="d-flex align-items-center gap-2 py-1">
                                <span class="app-user-avatar">{{ str($manager->name)->substr(0, 1)->upper() }}</span>
                                <div><div class="fw-semibold">{{ $manager->name }}</div><div class="small text-secondary">{{ $manager->email }}</div></div>
                            </div>
                        @empty
                            <div class="small text-secondary">No manager accounts yet.</div>
                        @endforelse
                    </div>
                </div>

                <button type="submit" class="btn btn-warning btn-lg w-100"><i class="bi bi-shield-check me-1"></i>Save manager access</button>
            </div>
        </div>
    </form>
</x-layouts.app>
