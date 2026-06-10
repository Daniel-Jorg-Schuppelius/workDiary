{{-- Anlage-Dialog Verarbeitungstätigkeit (in #entry-modal geladen). Variablen: $roles --}}
<x-modal
    :title="__('Neue Verarbeitungstätigkeit')"
    :eyebrow="__('Verzeichnis von Verarbeitungstätigkeiten')"
    icon="fact_check"
    tone="primary"
    :action="route('dataprotection.activities.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="label" for="name">{{ __('Bezeichnung') }}</label>
            <input id="name" name="name" class="input input-bordered w-full" value="{{ old('name') }}" required>
        </div>
        <div>
            <label class="label" for="controller_role">{{ __('Verantwortungsrolle') }}</label>
            <select id="controller_role" name="controller_role" class="select select-bordered w-full">
                @foreach ($roles as $r)
                    <option value="{{ $r->value }}" @selected(old('controller_role') === $r->value)>{{ $r->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="label" for="purpose">{{ __('Zweck der Verarbeitung') }}</label>
        <textarea id="purpose" name="purpose" rows="2" class="textarea textarea-bordered w-full">{{ old('purpose') }}</textarea>
    </div>
    <div>
        <label class="label" for="area">{{ __('Fachbereich (optional)') }}</label>
        <input id="area" name="area" class="input input-bordered w-full" value="{{ old('area') }}">
    </div>

    @include('privacy.activities._payload_fields')
</x-modal>
