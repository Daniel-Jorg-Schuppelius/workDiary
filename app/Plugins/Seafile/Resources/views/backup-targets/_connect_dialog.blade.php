{{--
  Created on   : Sat Oct 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Seafile als Backupziel anbinden (MVP-1348). Variable: $connection (bestehendes Ziel oder null). --}}
<x-modal
    :title="__('seafile::seafile.backup.connect_title')"
    icon="cloud_upload"
    tone="primary"
    :action="route('admin.backup-targets.seafile.connect')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('seafile::seafile.backup.connect_submit')"
>
    @if ($connection !== null)
        <input type="hidden" name="connection" value="{{ $connection->sqid }}">
    @endif

    <x-form-group :legend="__('seafile::seafile.backup.connect_legend')" icon="cloud_upload" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="sfb-name">{{ __('seafile::seafile.backup.name') }}</label>
            <input id="sfb-name" type="text" name="name" required maxlength="190"
                   value="{{ old('name', $connection->name ?? 'Seafile') }}"
                   class="input input-bordered w-full">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="sfb-server">{{ __('seafile::seafile.backup.server_url') }}</label>
            <input id="sfb-server" type="url" name="server_url" required maxlength="512"
                   value="{{ old('server_url', $connection->server_url ?? '') }}"
                   class="input input-bordered w-full font-mono" placeholder="https://seafile.example.com">
            <p class="text-xs text-muted">{{ __('seafile::seafile.backup.server_url_help') }}</p>
            @error('server_url')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="sfb-token">{{ __('seafile::seafile.backup.api_token') }}</label>
            <input id="sfb-token" type="password" name="api_token" required maxlength="512" autocomplete="new-password"
                   class="input input-bordered w-full font-mono" placeholder="••••••••">
            <p class="text-xs text-muted">{{ __('seafile::seafile.backup.api_token_help') }}</p>
            @error('api_token')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="sfb-library">{{ __('seafile::seafile.backup.library') }}</label>
            <input id="sfb-library" type="text" name="library" required maxlength="255"
                   value="{{ old('library', 'WorkDiary-Backup') }}"
                   class="input input-bordered w-full">
            <p class="text-xs text-muted">{{ __('seafile::seafile.backup.library_help') }}</p>
            @error('library')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>
