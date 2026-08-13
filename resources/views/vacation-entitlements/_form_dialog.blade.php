{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $entitlement, $isEdit, $isDialog, $year, $assignableUsers --}}
@php
    $isEdit   = $isEdit   ?? false;
    $isDialog = $isDialog ?? true;
    $action   = $isEdit ? route('vacation-entitlements.update', $entitlement) : route('vacation-entitlements.store');
    $dialogUrl = $isEdit
        ? route('vacation-entitlements.edit', $entitlement) . '?dialog=1'
        : route('vacation-entitlements.create', ['year' => $year, 'dialog' => 1]);
    $selectedUser = (string) old('user_id', $entitlement ? \App\Support\Sqid::encode(\App\Models\User::class, $entitlement->user_id) : '');
@endphp

<x-modal
    :title="$isEdit ? __('Urlaubsanspruch bearbeiten') : __('Urlaubsanspruch anlegen')"
    :eyebrow="__('Urlaubskonto')"
    icon="beach_access"
    tone="success"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary">
        @if ($isEdit)
            <p class="text-sm text-base-content/70">
                {{ $entitlement->user?->name ?? '—' }} · {{ $entitlement->year }}
            </p>
        @else
            <div class="fieldset">
                <label class="fieldset-label" for="ve-user">{{ __('Mitarbeiter') }} *</label>
                <select id="ve-user" name="user_id" class="select select-bordered w-full" required>
                    <option value="">{{ __('Bitte wählen') }}</option>
                    @foreach ($assignableUsers as $u)
                        @php($uid = (int) ($u['id'] ?? $u->id))
                        @php($usqid = \App\Support\Sqid::encode(\App\Models\User::class, $uid))
                        <option value="{{ $usqid }}" @selected($selectedUser === $usqid)>{{ $u['name'] ?? $u->name }}</option>
                    @endforeach
                </select>
                @error('user_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label" for="ve-year">{{ __('Jahr') }} *</label>
                <input id="ve-year" type="number" name="year" min="2000" max="2100" step="1"
                       class="input input-bordered w-full"
                       value="{{ old('year', $year) }}" required>
                @error('year')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        @endif
    </x-form-group>

    <x-form-group :legend="__('Anspruch')" icon="event_available" tone="success">
        <div class="fieldset">
            <label class="fieldset-label" for="ve-days">{{ __('Jahresanspruch (Arbeitstage)') }} *</label>
            <input id="ve-days" type="number" name="entitled_days" min="0" max="365" step="0.5"
                   class="input input-bordered w-full"
                   value="{{ old('entitled_days', $entitlement?->entitled_days) }}" required>
            @error('entitled_days')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        {{-- MVP-535: getrennte Anspruchskomponenten (Q1 S. 70/90). --}}
        <div class="fieldset">
            <label class="fieldset-label" for="ve-disabled">{{ __('Zusatzurlaub Schwerbehinderung (Tage)') }}</label>
            <input id="ve-disabled" type="number" name="severely_disabled_days" min="0" max="365" step="0.5"
                   class="input input-bordered w-full"
                   value="{{ old('severely_disabled_days', $entitlement?->severely_disabled_days ?? 0) }}">
            <p class="text-xs text-base-content/60">{{ __('SGB IX § 208 — wird zum Gesamtanspruch addiert, aber getrennt ausgewiesen.') }}</p>
            @error('severely_disabled_days')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ve-other">{{ __('Sonstiger Anspruch (Tage)') }}</label>
            <input id="ve-other" type="number" name="other_days" min="0" max="365" step="0.5"
                   class="input input-bordered w-full"
                   value="{{ old('other_days', $entitlement?->other_days ?? 0) }}">
            <p class="text-xs text-base-content/60">{{ __('z. B. Betriebsvereinbarung oder Jubiläum.') }}</p>
            @error('other_days')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ve-carryover">{{ __('Übertrag aus Vorjahr (Tage)') }}</label>
            <input id="ve-carryover" type="number" name="carryover_days" min="0" max="365" step="0.5"
                   class="input input-bordered w-full"
                   value="{{ old('carryover_days', $entitlement?->carryover_days ?? 0) }}">
            @error('carryover_days')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ve-expires">{{ __('Übertrag verfällt am') }}</label>
            <input id="ve-expires" type="date" name="carryover_expires_on"
                   class="input input-bordered w-full"
                   value="{{ old('carryover_expires_on', $entitlement?->carryover_expires_on?->format('Y-m-d')) }}">
            <p class="text-xs text-base-content/60">{{ __('Üblich: 31.03. des Anspruchsjahres; leer = kein Verfall.') }}</p>
            @error('carryover_expires_on')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('Begründung')" icon="description" tone="ghost">
        <div class="fieldset">
            <label class="fieldset-label" for="ve-note">{{ __('Notiz') }}</label>
            <textarea id="ve-note" name="note" rows="2" class="textarea textarea-bordered w-full">{{ old('note', $entitlement?->note) }}</textarea>
        </div>
    </x-form-group>

    <x-validation-errors />
</x-modal>
