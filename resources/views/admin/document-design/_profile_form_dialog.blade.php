{{-- Dialog: Renderprofil anlegen (Feature 076, MVP-300) --}}
<x-modal
    :title="__('document_design.profile.create')"
    icon="design_services"
    tone="primary"
    :action="route('admin.document-design.profiles.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')"
>
    <div class="space-y-3">
        <label class="form-control w-full">
            <span class="label-text">{{ __('document_design.profile.name') }} *</span>
            <input type="text" name="name" required maxlength="120" value="{{ old('name') }}"
                   class="input input-bordered input-sm w-full">
        </label>

        <fieldset class="space-y-1">
            <legend class="label-text">{{ __('document_design.profile.kinds') }}</legend>
            @foreach ($kinds as $kind)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="document_kinds[]" value="{{ $kind->value }}" class="checkbox checkbox-sm">
                    {{ $kind->label() }}
                </label>
            @endforeach
        </fieldset>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_default" value="1" class="checkbox checkbox-sm">
            {{ __('document_design.profile.set_default') }}
        </label>
    </div>
</x-modal>
