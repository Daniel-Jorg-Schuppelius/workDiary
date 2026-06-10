{{-- Anlage-Dialog Betroffenenanfrage (in #entry-modal geladen). Variablen: $types --}}
<x-modal
    :title="__('Neue Betroffenenanfrage')"
    :eyebrow="__('Betroffenenanfragen')"
    icon="contact_mail"
    tone="primary"
    :action="route('dataprotection.requests.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <div>
        <label class="label" for="type">{{ __('Art der Anfrage') }}</label>
        <select id="type" name="type" class="select select-bordered w-full" required>
            @foreach ($types as $t)
                <option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="channel">{{ __('Eingangskanal (optional)') }}</label>
        <input id="channel" name="channel" class="input input-bordered w-full" value="{{ old('channel') }}" placeholder="email, post, telefon …">
    </div>
    <div>
        <label class="label" for="subject">{{ __('Betroffene Person (Identität)') }}</label>
        <textarea id="subject" name="subject" rows="2" class="textarea textarea-bordered w-full" required>{{ old('subject') }}</textarea>
        <p class="text-xs text-base-content/60 mt-1">{{ __('Wird verschlüsselt gespeichert (Crypto-Shredding nach Aufbewahrung).') }}</p>
    </div>
    <div>
        <label class="label" for="content">{{ __('Anliegen / Sachverhalt') }}</label>
        <textarea id="content" name="content" rows="5" class="textarea textarea-bordered w-full" required>{{ old('content') }}</textarea>
    </div>
</x-modal>
