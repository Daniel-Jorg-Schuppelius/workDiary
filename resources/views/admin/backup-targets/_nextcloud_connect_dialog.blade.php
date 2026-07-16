{{--
  Created on   : Wed Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _nextcloud_connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Nextcloud-Backupziel per Zugangsdaten anbinden (Feature 017, MVP-383) --}}
@php
    /** @var \App\Models\Backup\BackupTargetConnection|null $connection */
    $isEdit = $connection !== null;
@endphp
<x-modal
    :title="__('backup_targets.nextcloud.connect_title')"
    icon="cloud_upload"
    tone="primary"
    :action="route('admin.backup-targets.nextcloud.connect')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('backup_targets.nextcloud.connect_submit')"
>
    @if ($isEdit)
        <input type="hidden" name="connection" value="{{ $connection->sqid }}">
    @endif

    <x-form-group :legend="__('backup_targets.nextcloud.connect_legend')" icon="cloud_upload" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="ncb-name">{{ __('backup_targets.nextcloud.field.name') }}</label>
            <input id="ncb-name" type="text" name="name" required maxlength="190"
                   value="{{ old('name', $connection->name ?? 'Nextcloud') }}"
                   class="input input-bordered w-full">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ncb-server">{{ __('backup_targets.nextcloud.field.server_url') }}</label>
            <input id="ncb-server" type="url" name="server_url" required maxlength="512"
                   value="{{ old('server_url', $connection->server_url ?? '') }}"
                   class="input input-bordered w-full font-mono" placeholder="https://cloud.example.com">
            <p class="text-xs text-base-content/60">{{ __('backup_targets.nextcloud.field.server_url_help') }}</p>
            @error('server_url')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ncb-user">{{ __('backup_targets.nextcloud.field.username') }}</label>
            <input id="ncb-user" type="text" name="username" required maxlength="190" autocomplete="off"
                   value="{{ old('username', $connection->username ?? '') }}"
                   class="input input-bordered w-full">
            @error('username')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ncb-pass">{{ __('backup_targets.nextcloud.field.app_password') }}</label>
            <input id="ncb-pass" type="password" name="app_password" required maxlength="512" autocomplete="new-password"
                   class="input input-bordered w-full font-mono" placeholder="••••••••">
            <p class="text-xs text-base-content/60">{{ __('backup_targets.nextcloud.field.app_password_help') }}</p>
            @error('app_password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>
