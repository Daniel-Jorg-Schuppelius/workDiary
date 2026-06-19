{{-- Erwartet: $isDialog --}}
@php $isDialog = $isDialog ?? false; @endphp

<x-modal
    :title="__('manufacturing.capacity.add')"
    :eyebrow="__('manufacturing.capacity.title')"
    icon="precision_manufacturing"
    tone="primary"
    :action="route('work-centers.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('work-centers.create') . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="precision_manufacturing" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('manufacturing.capacity.work_center') }} *</label>
            <input name="name" type="text" required maxlength="255" class="input input-bordered w-full" value="{{ old('name') }}">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('manufacturing.capacity.code') }}</label>
            <input name="code" type="text" maxlength="32" class="input input-bordered w-full" value="{{ old('code') }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('manufacturing.capacity.capacity') }} (min) *</label>
            <input name="capacity_minutes" type="number" min="1" required class="input input-bordered w-full" value="{{ old('capacity_minutes', 480) }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('manufacturing.capacity.setup') }} (min)</label>
            <input name="setup_minutes" type="number" min="0" class="input input-bordered w-full" value="{{ old('setup_minutes', 0) }}">
        </div>
    </x-form-group>
</x-modal>
