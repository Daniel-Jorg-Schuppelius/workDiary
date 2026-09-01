{{--
  Created on   : Tue Sep 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _s3_connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  S3-kompatibles Backupziel anbinden (Feature 123, MVP-726).

  Der Endpoint bleibt leer für AWS S3 — dort bildet das SDK ihn aus der
  Region. Für MinIO & Co. wird er gesetzt, meist zusammen mit Path-Style.
--}}
{{-- @var \App\Models\Backup\BackupTargetConnection|null $connection --}}
@php
    $options = (array) ($connection->options ?? []);
@endphp
<x-modal
    :title="__('backup_targets.s3.connect_title')"
    icon="cloud_upload"
    tone="primary"
    :action="route('admin.backup-targets.s3.connect')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('backup_targets.s3.connect_submit')"
>
    @if ($connection !== null)
        <input type="hidden" name="connection" value="{{ $connection->sqid }}">
    @endif

    <x-form-group :legend="__('backup_targets.s3.connect_legend')" icon="cloud_upload" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="s3-name">{{ __('backup_targets.s3.field.name') }}</label>
            <input id="s3-name" type="text" name="name" required maxlength="190"
                   value="{{ old('name', $connection->name ?? 'S3') }}"
                   class="input input-bordered w-full">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="s3-endpoint">{{ __('backup_targets.s3.field.endpoint') }}</label>
            <input id="s3-endpoint" type="url" name="endpoint" maxlength="512"
                   value="{{ old('endpoint', $connection->server_url ?? '') }}"
                   class="input input-bordered w-full font-mono"
                   placeholder="https://s3.example.com">
            <p class="text-xs text-muted">{{ __('backup_targets.s3.field.endpoint_help') }}</p>
            @error('endpoint')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="s3-region">{{ __('backup_targets.s3.field.region') }}</label>
            <input id="s3-region" type="text" name="region" required maxlength="64"
                   value="{{ old('region', $options['region'] ?? 'us-east-1') }}"
                   class="input input-bordered w-full font-mono" placeholder="eu-central-1">
            <p class="text-xs text-muted">{{ __('backup_targets.s3.field.region_help') }}</p>
            @error('region')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="s3-bucket">{{ __('backup_targets.s3.field.bucket') }}</label>
            <input id="s3-bucket" type="text" name="bucket" required maxlength="255"
                   value="{{ old('bucket', $options['bucket'] ?? '') }}"
                   class="input input-bordered w-full font-mono" placeholder="workdiary-backup">
            @error('bucket')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="s3-key">{{ __('backup_targets.s3.field.access_key') }}</label>
            <input id="s3-key" type="text" name="access_key" required maxlength="190" autocomplete="off"
                   value="{{ old('access_key', $connection->username ?? '') }}"
                   class="input input-bordered w-full font-mono">
            @error('access_key')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="s3-secret">{{ __('backup_targets.s3.field.secret_key') }}</label>
            <input id="s3-secret" type="password" name="secret_key" required maxlength="512" autocomplete="new-password"
                   class="input input-bordered w-full font-mono" placeholder="••••••••">
            <p class="text-xs text-muted">{{ __('backup_targets.s3.field.secret_key_help') }}</p>
            @error('secret_key')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="s3-prefix">{{ __('backup_targets.s3.field.prefix') }}</label>
            <input id="s3-prefix" type="text" name="prefix" maxlength="255"
                   value="{{ old('prefix', '') }}"
                   class="input input-bordered w-full font-mono" placeholder="workdiary">
            <p class="text-xs text-muted">{{ __('backup_targets.s3.field.prefix_help') }}</p>
            @error('prefix')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="path_style" value="0">
                <input type="checkbox" name="path_style" value="1" class="checkbox checkbox-sm"
                       @checked(old('path_style', $options['path_style'] ?? false))>
                <span>{{ __('backup_targets.s3.field.path_style') }}</span>
            </label>
            <p class="text-xs text-muted">{{ __('backup_targets.s3.field.path_style_help') }}</p>
        </div>
    </x-form-group>

    <p class="text-xs text-base-content/70">{{ __('backup_targets.s3.selftest_hint') }}</p>
</x-modal>
