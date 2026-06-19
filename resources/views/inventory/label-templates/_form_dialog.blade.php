{{-- Erwartet: $isDialog, $template (LabelTemplate|null) --}}
@php
    $isDialog = $isDialog ?? false;
    $tpl = $template ?? null;
    $fields = $tpl?->fields ?? \App\Models\LabelTemplate::FIELDS;
    $allFields = \App\Models\LabelTemplate::FIELDS;
@endphp

<x-modal
    :title="$tpl ? __('Bearbeiten') : __('inventory.label_template.add')"
    :eyebrow="__('inventory.label_template.title')"
    icon="label"
    tone="primary"
    :action="$tpl ? route('inventory.label-templates.update', $tpl) : route('inventory.label-templates.store')"
    :method="$tpl ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ ($tpl ? route('inventory.label-templates.edit', $tpl) : route('inventory.label-templates.create')) . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('inventory.label_template.title')" icon="label" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('inventory.label_template.name') }} *</label>
            <input name="name" required maxlength="120" class="input input-bordered w-full" value="{{ old('name', $tpl?->name) }}">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('inventory.label_template.paper_size') }} *</label>
            <select name="paper_size" class="select select-bordered w-full">
                @foreach (['a6', 'a7', 'a8'] as $size)
                    <option value="{{ $size }}" @selected(old('paper_size', $tpl?->paper_size ?? 'a7') === $size)>{{ strtoupper($size) }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('inventory.label_template.orientation') }} *</label>
            <select name="orientation" class="select select-bordered w-full">
                @foreach (['landscape', 'portrait'] as $o)
                    <option value="{{ $o }}" @selected(old('orientation', $tpl?->orientation ?? 'landscape') === $o)>{{ __('inventory.label_template.orientation_' . $o) }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="label cursor-pointer gap-2 justify-start">
                <input type="checkbox" name="with_qr" value="1" class="checkbox checkbox-sm" @checked(old('with_qr', $tpl?->with_qr ?? true))>
                <span>{{ __('inventory.label_template.with_qr') }}</span>
            </label>
            <label class="label cursor-pointer gap-2 justify-start">
                <input type="checkbox" name="is_default" value="1" class="checkbox checkbox-sm" @checked(old('is_default', $tpl?->is_default ?? false))>
                <span>{{ __('inventory.label_template.is_default') }}</span>
            </label>
        </div>
    </x-form-group>

    <x-form-group :legend="__('inventory.label_template.fields')" icon="checklist" cols="2">
        @foreach ($allFields as $f)
            <label class="label cursor-pointer gap-2 justify-start">
                <input type="checkbox" name="fields[]" value="{{ $f }}" class="checkbox checkbox-sm" @checked(in_array($f, old('fields', $fields), true))>
                <span>{{ __('inventory.label_template.field.' . $f) }}</span>
            </label>
        @endforeach
        @error('fields')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </x-form-group>
</x-modal>
