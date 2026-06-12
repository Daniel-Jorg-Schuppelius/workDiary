{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _token_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  „Prüfer-Link erstellen"-Dialog (in #entry-modal geladen): Label
  (z. B. „Auditor Müller") + Gültigkeit in Tagen (1-90). Der vollständige
  Link wird nach der Erstellung genau EINMAL als Flash angezeigt —
  gespeichert wird nur der SHA-256-Hash des Tokens.
  Variablen: $package (IsmsAuditPackage, mit scope)
--}}

<x-modal
    :title="__('isms.action.create_token')"
    :eyebrow="$package->displayNo() . ' · ' . $package->title"
    icon="key"
    tone="primary"
    size="md"
    :action="route('isms.packages.tokens.store', $package)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('isms.action.create_token')">

    <x-form-group :legend="__('isms.group.token')" icon="key" tone="primary" cols="1">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.token_label') }} *</span>
            <input type="text" name="label" required maxlength="120"
                   class="input input-bordered w-full"
                   placeholder="{{ __('isms.hint.token_label') }}"
                   value="{{ old('label') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.token_days') }} *</span>
            <input type="number" name="days" required min="1" max="90" step="1"
                   class="input input-bordered w-full"
                   value="{{ old('days', 14) }}">
            <span class="label-text-alt text-base-content/60">{{ __('isms.hint.token_days') }}</span>
        </label>
        <p class="text-xs text-base-content/60">{{ __('isms.package.token_once_hint') }}</p>
    </x-form-group>
</x-modal>
