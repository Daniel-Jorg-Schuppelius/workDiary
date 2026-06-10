{{-- Anlage-Dialog technische/organisatorische Maßnahme (in #entry-modal geladen). Variablen: $categories --}}
<x-modal
    :title="__('Neue Maßnahme')"
    :eyebrow="__('TOM-Katalog')"
    icon="shield"
    tone="primary"
    :action="route('dataprotection.tom.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="label" for="name">{{ __('Bezeichnung') }}</label>
            <input id="name" name="name" class="input input-bordered w-full" value="{{ old('name') }}" required>
        </div>
        <div>
            <label class="label" for="category">{{ __('Maßnahmenbereich') }}</label>
            <select id="category" name="category" class="select select-bordered w-full">
                @foreach ($categories as $c)<option value="{{ $c->value }}" @selected(old('category') === $c->value)>{{ $c->label() }}</option>@endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="label" for="description">{{ __('Beschreibung') }}</label>
        <textarea id="description" name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
    </div>
    <div>
        <label class="label" for="addressed_risks">{{ __('Adressierte Risiken') }}</label>
        <textarea id="addressed_risks" name="addressed_risks" rows="2" class="textarea textarea-bordered w-full">{{ old('addressed_risks') }}</textarea>
    </div>
    <div>
        <label class="label" for="evidence">{{ __('Nachweise (Richtlinien, Protokolle, Zertifikate …)') }}</label>
        <textarea id="evidence" name="evidence" rows="2" class="textarea textarea-bordered w-full">{{ old('evidence') }}</textarea>
    </div>
</x-modal>
