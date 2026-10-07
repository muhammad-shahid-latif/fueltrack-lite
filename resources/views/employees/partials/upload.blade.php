{{-- One image upload with a live preview. Picking a file replaces the current one on save. --}}
@php($current = $employee->documentUrl($field))

<div x-data="{ preview: @js($current), removed: false }">
    <span class="form-label d-block">{{ $label }}</span>
    <label for="{{ $field }}" class="doc-drop is-{{ $shape }} @error($field) border-danger @enderror">
        <template x-if="preview && ! removed">
            <img :src="preview" alt="{{ $label }} preview">
        </template>
        <span x-show="! preview || removed">
            <i class="bi bi-{{ $icon }} d-block"></i>
            <span class="small fw-semibold">{{ $hint }}</span>
        </span>
    </label>
    <input type="file" id="{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png,image/webp" class="visually-hidden"
           @change="if ($event.target.files[0]) { preview = URL.createObjectURL($event.target.files[0]); removed = false }">
    @error($field) <div class="text-danger small mt-1">{{ $message }}</div> @enderror

    @if ($current)
        <div class="form-check small mt-2">
            <input class="form-check-input" type="checkbox" name="remove_{{ $field }}" value="1" id="remove_{{ $field }}" x-model="removed">
            <label class="form-check-label" for="remove_{{ $field }}">Remove</label>
        </div>
    @endif
</div>
