{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _checkout_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset $asset */
@endphp

<x-modal
    :title="__('Asset ausgeben')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="logout"
    tone="primary"
    size="md"
    :action="route('assets.checkout.store', $asset)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Ausgeben')">

    <p class="mb-2 text-sm text-base-content/70">
        {{ __('Gib das Asset an eine Person oder ein Team aus. Mindestens eines von beiden ist erforderlich.') }}
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="assigned_to_user_id" :label="__('An Person')">
            <option value="">{{ __('— keine —') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected(old('assigned_to_user_id') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="assigned_to_team_id" :label="__('An Team')">
            <option value="">{{ __('— keines —') }}</option>
            @foreach ($teams as $t)
                <option value="{{ $t->sqid }}" @selected(old('assigned_to_team_id') === $t->sqid)>{{ $t->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="diary_entry_id" :label="__('Auftragsbezug')">
            <option value="">{{ __('— kein Auftrag —') }}</option>
            @foreach ($diaryEntries as $entry)
                <option value="{{ $entry->sqid }}" @selected(old('diary_entry_id') === $entry->sqid)>
                    {{ $entry->title ?: ('#' . $entry->id) }}
                </option>
            @endforeach
        </x-select-field>
        <x-input-field type="datetime-local" name="expected_return_at" :label="__('Erwartete Rückgabe')"
                       :value="old('expected_return_at')" />
    </div>

    <x-input-field name="condition_out" :label="__('Zustand bei Ausgabe')" maxlength="180"
                   :value="old('condition_out')" :placeholder="__('z. B. vollständig, keine sichtbaren Mängel')" />

    <x-textarea-field name="note" :label="__('Notiz')" rows="2" :value="old('note')" />
</x-modal>
