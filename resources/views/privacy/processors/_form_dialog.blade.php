{{-- Anlage-Dialog Dienstleister (in #entry-modal geladen). Variablen: $roles --}}
<x-modal
    :title="__('Neuer Dienstleister')"
    :eyebrow="__('Dienstleister & AVV')"
    icon="handshake"
    tone="primary"
    :action="route('dataprotection.processors.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="label" for="name">{{ __('Name') }}</label>
            <input id="name" name="name" class="input input-bordered w-full" value="{{ old('name') }}" required>
        </div>
        <div>
            <label class="label" for="role">{{ __('Rolle') }}</label>
            <select id="role" name="role" class="select select-bordered w-full">
                @foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="contact">{{ __('Kontakt') }}</label>
            <input id="contact" name="contact" class="input input-bordered w-full" value="{{ old('contact') }}">
        </div>
        <div>
            <label class="label" for="location">{{ __('Verarbeitungsort') }}</label>
            <input id="location" name="location" class="input input-bordered w-full" value="{{ old('location') }}">
        </div>
    </div>
    <label class="flex items-center gap-2">
        <input type="hidden" name="third_country" value="0">
        <input type="checkbox" name="third_country" value="1" class="checkbox" @checked(old('third_country'))> {{ __('Drittlandtransfer') }}
    </label>
    <div>
        <label class="label" for="notes">{{ __('Notizen') }}</label>
        <textarea id="notes" name="notes" rows="3" class="textarea textarea-bordered w-full">{{ old('notes') }}</textarea>
    </div>
</x-modal>
