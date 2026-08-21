{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _webdav_connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Generisches WebDAV-Backupziel anbinden (Feature 123, MVP-612).

  Eigener Dialog statt des Nextcloud-Bausteins: Dort heisst das Feld
  „App-Passwort" (ein Nextcloud-Begriff), und die Collection-URL eines
  beliebigen Servers braucht zusätzlich einen optionalen Unterordner.
--}}
{{-- @var \App\Models\Backup\BackupTargetConnection|null $connection --}}
<x-modal
    :title="__('backup_targets.webdav.connect_title')"
    icon="cloud_upload"
    tone="primary"
    :action="route('admin.backup-targets.webdav.connect')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('backup_targets.webdav.connect_submit')"
>
    @if ($connection !== null)
        <input type="hidden" name="connection" value="{{ $connection->sqid }}">
    @endif

    <x-form-group :legend="__('backup_targets.webdav.connect_legend')" icon="cloud_upload" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="wdb-name">{{ __('backup_targets.webdav.field.name') }}</label>
            <input id="wdb-name" type="text" name="name" required maxlength="190"
                   value="{{ old('name', $connection->name ?? 'WebDAV') }}"
                   class="input input-bordered w-full">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="wdb-url">{{ __('backup_targets.webdav.field.server_url') }}</label>
            <input id="wdb-url" type="url" name="server_url" required maxlength="512"
                   value="{{ old('server_url', $connection->server_url ?? '') }}"
                   class="input input-bordered w-full font-mono"
                   placeholder="https://dav.example.com/remote.php/dav/files/backup/">
            <p class="text-xs text-base-content/60">{{ __('backup_targets.webdav.field.server_url_help') }}</p>
            @error('server_url')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="wdb-user">{{ __('backup_targets.webdav.field.username') }}</label>
            <input id="wdb-user" type="text" name="username" required maxlength="190" autocomplete="off"
                   value="{{ old('username', $connection->username ?? '') }}"
                   class="input input-bordered w-full">
            @error('username')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="wdb-pass">{{ __('backup_targets.webdav.field.password') }}</label>
            <input id="wdb-pass" type="password" name="password" required maxlength="512" autocomplete="new-password"
                   class="input input-bordered w-full font-mono" placeholder="••••••••">
            <p class="text-xs text-base-content/60">{{ __('backup_targets.webdav.field.password_help') }}</p>
            @error('password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="wdb-path">{{ __('backup_targets.webdav.field.base_path') }}</label>
            <input id="wdb-path" type="text" name="base_path" maxlength="255"
                   value="{{ old('base_path', '') }}"
                   class="input input-bordered w-full font-mono" placeholder="workdiary">
            <p class="text-xs text-base-content/60">{{ __('backup_targets.webdav.field.base_path_help') }}</p>
            @error('base_path')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <p class="text-xs text-base-content/70">{{ __('backup_targets.webdav.selftest_hint') }}</p>
</x-modal>
