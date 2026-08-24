{{--
  Created on   : Wed Jul 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : nextcloud-connect-dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Gemeinsamer Dialog „Nextcloud per Zugangsdaten anbinden" (Vollreview W4.3):
  genutzt vom Backup-Ziel (Feature 017, MVP-383) und der Cloud-Intake-Quelle
  (Feature 080, MVP-382). Feldstruktur, Validierungsattribute und Hilfetexte
  leben genau einmal; Varianten unterscheiden sich nur über die Props.
--}}
@props([
    'connection' => null,
    'action',
    'icon' => 'cloud',
    'idPrefix' => 'nc',
    'langPrefix',
    'nameKey' => null,
])
@php
    $isEdit = $connection !== null;
    $nameKey ??= $langPrefix . '.field.name';
@endphp
<x-modal
    :title="__($langPrefix . '.connect_title')"
    :icon="$icon"
    tone="primary"
    :action="$action"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__($langPrefix . '.connect_submit')"
>
    @if ($isEdit)
        <input type="hidden" name="connection" value="{{ $connection->sqid }}">
    @endif

    <x-form-group :legend="__($langPrefix . '.connect_legend')" :icon="$icon" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="{{ $idPrefix }}-name">{{ __($nameKey) }}</label>
            <input id="{{ $idPrefix }}-name" type="text" name="name" required maxlength="190"
                   value="{{ old('name', $connection->name ?? 'Nextcloud') }}"
                   class="input input-bordered w-full">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="{{ $idPrefix }}-server">{{ __($langPrefix . '.field.server_url') }}</label>
            <input id="{{ $idPrefix }}-server" type="url" name="server_url" required maxlength="512"
                   value="{{ old('server_url', $connection->server_url ?? '') }}"
                   class="input input-bordered w-full font-mono" placeholder="https://cloud.example.com">
            <p class="text-xs text-muted">{{ __($langPrefix . '.field.server_url_help') }}</p>
            @error('server_url')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="{{ $idPrefix }}-user">{{ __($langPrefix . '.field.username') }}</label>
            <input id="{{ $idPrefix }}-user" type="text" name="username" required maxlength="190" autocomplete="off"
                   value="{{ old('username', $connection->username ?? '') }}"
                   class="input input-bordered w-full">
            @error('username')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="{{ $idPrefix }}-pass">{{ __($langPrefix . '.field.app_password') }}</label>
            <input id="{{ $idPrefix }}-pass" type="password" name="app_password" required maxlength="512" autocomplete="new-password"
                   class="input input-bordered w-full font-mono" placeholder="••••••••">
            <p class="text-xs text-muted">{{ __($langPrefix . '.field.app_password_help') }}</p>
            @error('app_password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>
