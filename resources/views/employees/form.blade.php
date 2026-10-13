@php($editing = $employee->exists)

<x-layouts.app :title="$editing ? 'Edit '.$employee->name : 'Add employee'" subtitle="Salary changes apply from the next salary sheet">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card mb-4">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ $editing ? route('employees.update', $employee) : route('employees.store') }}" enctype="multipart/form-data" novalidate
                          x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        @if ($editing) @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label">Full name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $employee->name) }}" maxlength="100"
                                       class="form-control form-control-lg @error('name') is-invalid @enderror" required autofocus>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="designation" class="form-label">Job</label>
                                <input type="text" id="designation" name="designation" list="designations" value="{{ old('designation', $employee->designation) }}" maxlength="60"
                                       class="form-control @error('designation') is-invalid @enderror" placeholder="e.g. Pump attendant" required>
                                <datalist id="designations">
                                    @foreach (\App\Models\Employee::DESIGNATIONS as $designation)
                                        <option value="{{ $designation }}">
                                    @endforeach
                                </datalist>
                                @error('designation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="phone" class="form-label">Phone <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="tel" id="phone" name="phone" value="{{ old('phone', $employee->phone) }}" maxlength="20"
                                       class="form-control @error('phone') is-invalid @enderror" placeholder="0300-1234567">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="monthly_salary" class="form-label">Monthly salary</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="text" inputmode="decimal" id="monthly_salary" name="monthly_salary"
                                           value="{{ old('monthly_salary', $editing ? \App\Support\Format::input($employee->monthly_salary) : '') }}"
                                           class="form-control ft-num @error('monthly_salary') is-invalid @enderror" required>
                                    @error('monthly_salary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <label for="joined_on" class="form-label">Joining date</label>
                                <input type="date" id="joined_on" name="joined_on" value="{{ old('joined_on', $employee->joined_on?->toDateString()) }}"
                                       max="{{ today()->toDateString() }}" class="form-control @error('joined_on') is-invalid @enderror" required>
                                @error('joined_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes', $employee->notes) }}" maxlength="500"
                                       class="form-control @error('notes') is-invalid @enderror" placeholder="e.g. Night shift, bank account details">
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12"><hr class="my-2"></div>

                            <div class="col-12">
                                <div class="fw-semibold mb-1">Photo and CNIC</div>
                                <div class="small text-secondary mb-3">JPG, PNG or WEBP up to 5 MB. Kept private: only the owner can open them.</div>
                            </div>

                            <div class="col-sm-auto">
                                @include('employees.partials.upload', ['field' => 'photo', 'label' => 'Photo', 'shape' => 'photo', 'icon' => 'person-bounding-box', 'hint' => 'Add photo'])
                            </div>

                            <div class="col-sm align-self-center">
                                <label for="cnic_number" class="form-label">CNIC number <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" inputmode="numeric" id="cnic_number" name="cnic_number" maxlength="15"
                                       value="{{ old('cnic_number', $employee->cnic_number) }}" placeholder="12345-1234567-1"
                                       x-data @input="$el.value = $el.value.replace(/\D/g, '').slice(0, 13).replace(/^(\d{5})(\d)/, '$1-$2').replace(/^(\d{5}-\d{7})(\d)/, '$1-$2')"
                                       class="form-control ft-num @error('cnic_number') is-invalid @enderror">
                                @error('cnic_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="w-100"></div>

                            <div class="col-sm-6">
                                @include('employees.partials.upload', ['field' => 'cnic_front', 'label' => 'CNIC front', 'shape' => 'card', 'icon' => 'person-vcard', 'hint' => 'Front side'])
                            </div>
                            <div class="col-sm-6">
                                @include('employees.partials.upload', ['field' => 'cnic_back', 'label' => 'CNIC back', 'shape' => 'card', 'icon' => 'credit-card-2-back', 'hint' => 'Back side'])
                            </div>

                            @if ($editing)
                                <div class="col-12">
                                    <input type="hidden" name="is_active" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $employee->is_active))>
                                        <label class="form-check-label" for="is_active">Active. Inactive staff are left out of new salary sheets.</label>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ $editing ? route('employees.show', $employee) : route('employees.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                {{ $editing ? 'Save changes' : 'Add employee' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($editing)
                <div class="card border-danger-subtle">
                    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">Remove employee</div>
                            <p class="small text-secondary mb-0">Only possible before any salary or advance is recorded. Otherwise switch them to inactive.</p>
                        </div>
                        <form method="POST" action="{{ route('employees.destroy', $employee) }}" x-data="{ sure: false }">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true"><i class="bi bi-trash3 me-1"></i> Remove</button>
                            <span x-show="sure" x-cloak><span class="d-flex flex-wrap align-items-center gap-2">
                                <button type="submit" class="btn btn-danger">Yes, remove</button>
                                <button type="button" class="btn btn-light" @click="sure = false">Keep</button>
                            </span></span>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
