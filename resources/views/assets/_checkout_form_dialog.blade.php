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
        <label class="form-control">
            <span class="label-text">{{ __('An Person') }}</span>
            <select name="assigned_to_user_id" class="select select-bordered w-full">
                <option value="">{{ __('— keine —') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->sqid }}" @selected(old('assigned_to_user_id') === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('An Team') }}</span>
            <select name="assigned_to_team_id" class="select select-bordered w-full">
                <option value="">{{ __('— keines —') }}</option>
                @foreach ($teams as $t)
                    <option value="{{ $t->sqid }}" @selected(old('assigned_to_team_id') === $t->sqid)>{{ $t->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('Auftragsbezug') }}</span>
            <select name="diary_entry_id" class="select select-bordered w-full">
                <option value="">{{ __('— kein Auftrag —') }}</option>
                @foreach ($diaryEntries as $entry)
                    <option value="{{ $entry->sqid }}" @selected(old('diary_entry_id') === $entry->sqid)>
                        {{ $entry->title ?: ('#' . $entry->id) }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('Erwartete Rückgabe') }}</span>
            <input type="datetime-local" name="expected_return_at" value="{{ old('expected_return_at') }}"
                   class="input input-bordered w-full" />
        </label>
    </div>

    <label class="form-control">
        <span class="label-text">{{ __('Zustand bei Ausgabe') }}</span>
        <input type="text" name="condition_out" maxlength="180" value="{{ old('condition_out') }}"
               class="input input-bordered w-full" placeholder="{{ __('z. B. vollständig, keine sichtbaren Mängel') }}" />
    </label>

    <label class="form-control">
        <span class="label-text">{{ __('Notiz') }}</span>
        <textarea name="note" rows="2" class="textarea textarea-bordered w-full">{{ old('note') }}</textarea>
    </label>
</x-modal>
