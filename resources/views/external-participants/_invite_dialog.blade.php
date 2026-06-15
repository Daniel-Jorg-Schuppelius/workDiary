{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _invite_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  „Externen einladen"-Dialog (in #entry-modal geladen, Feature 033): Name,
  Rolle, Art, optionale E-Mail, erlaubte Aktionen (Checkboxen) und Gültigkeit
  in Tagen. Der vollständige Link wird nach dem Anlegen genau EINMAL als Flash
  angezeigt — gespeichert wird nur der SHA-256-Hash.
  Variablen: $type, $subjectId, $parties, $abilities, $defaultTtl
--}}

<x-modal
    :title="__('external.invite.title')"
    :eyebrow="__('external.invite.eyebrow')"
    icon="badge"
    tone="primary"
    size="md"
    :action="route('external.store', ['type' => $type, 'id' => $subjectId])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('external.invite.submit')">

    <x-form-group :legend="__('external.group.contact')" icon="person" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('external.field.name') }} *</span>
            <input type="text" name="name" required minlength="2" maxlength="160"
                   class="input input-bordered w-full" value="{{ old('name') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('external.field.email') }}</span>
            <input type="email" name="email" maxlength="190"
                   class="input input-bordered w-full" value="{{ old('email') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('external.field.role') }}</span>
            <input type="text" name="role" maxlength="120"
                   class="input input-bordered w-full" value="{{ old('role') }}"
                   placeholder="{{ __('external.hint.role') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('external.field.party') }} *</span>
            <select name="party" class="select select-bordered w-full">
                @foreach ($parties as $party)
                    <option value="{{ $party->value }}" @selected(old('party') === $party->value)>{{ $party->label() }}</option>
                @endforeach
            </select>
        </label>
    </x-form-group>

    <x-form-group :legend="__('external.group.abilities')" icon="lock" tone="primary" cols="1">
        <p class="text-xs text-base-content/60">{{ __('external.hint.abilities') }}</p>
        <div class="flex flex-wrap gap-4">
            @foreach ($abilities as $ability)
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" name="abilities[]" value="{{ $ability->value }}" class="checkbox checkbox-sm">
                    <span class="label-text">{{ $ability->label() }}</span>
                </label>
            @endforeach
        </div>
    </x-form-group>

    <x-form-group :legend="__('external.group.validity')" icon="schedule" tone="primary" cols="1">
        <label class="form-control max-w-xs">
            <span class="label-text">{{ __('external.field.ttl_days') }} *</span>
            <input type="number" name="ttl_days" required min="1" max="180" step="1"
                   class="input input-bordered w-full" value="{{ old('ttl_days', $defaultTtl) }}">
            <span class="label-text-alt text-base-content/60">{{ __('external.hint.ttl_days') }}</span>
        </label>
        <p class="text-xs text-base-content/60">{{ __('external.invite.once_hint') }}</p>
    </x-form-group>
</x-modal>
