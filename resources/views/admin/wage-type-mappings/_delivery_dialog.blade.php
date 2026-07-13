{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _delivery_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: automatische Export-Lieferung je Profil (A21 · MVP-019) --}}
@php
    /** @var \App\Models\TimeExportDeliveryConfig $config */
    $mailEnabled = (bool) old('mail_enabled', $config->mail_enabled);
    $sftpEnabled = (bool) old('sftp_enabled', $config->sftp_enabled);
    $recipientsRaw = old('mail_recipients_raw', implode("\n", $config->mailRecipients()));
@endphp
<x-modal
    :title="__('wage_types.title.delivery_edit', ['profile' => $profileLabel])"
    icon="outgoing_mail"
    tone="primary"
    :action="route('admin.wage-type-mappings.delivery.update', ['profile' => $profile])"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('wage_types.action.save')"
>
    <div x-data="{ mail: @js($mailEnabled), sftp: @js($sftpEnabled) }" class="space-y-4">
        <x-form-group :legend="__('wage_types.field.mail')" icon="mail" tone="primary" cols="1">
            <div class="fieldset">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="mail_enabled" value="0">
                    <input type="checkbox" name="mail_enabled" value="1" class="toggle toggle-primary"
                           x-model="mail" @checked($mailEnabled)>
                    <span class="label-text">{{ __('wage_types.field.mail_toggle') }}</span>
                </label>
                @error('mail_enabled')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="mail" x-cloak>
                <label class="fieldset-label" for="tedc-recipients">{{ __('wage_types.field.mail_recipients') }}</label>
                <textarea id="tedc-recipients" name="mail_recipients_raw" rows="3"
                          class="textarea textarea-bordered w-full font-mono text-sm"
                          placeholder="lohn@example.org">{{ $recipientsRaw }}</textarea>
                <p class="text-xs text-base-content/60">{{ __('wage_types.field.mail_recipients_help') }}</p>
                @error('mail_recipients')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                @error('mail_recipients.*')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('wage_types.field.sftp')" icon="cloud_upload" tone="primary" cols="2">
            <div class="fieldset md:col-span-2">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="sftp_enabled" value="0">
                    <input type="checkbox" name="sftp_enabled" value="1" class="toggle toggle-primary"
                           x-model="sftp" @checked($sftpEnabled)>
                    <span class="label-text">{{ __('wage_types.field.sftp_toggle') }}</span>
                </label>
                @error('sftp_enabled')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="sftp" x-cloak>
                <label class="fieldset-label" for="tedc-host">{{ __('wage_types.field.sftp_host') }}</label>
                <input id="tedc-host" type="text" name="sftp_host" maxlength="190"
                       value="{{ old('sftp_host', $config->sftp_host) }}"
                       class="input input-bordered w-full font-mono" placeholder="sftp.example.org">
                @error('sftp_host')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="sftp" x-cloak>
                <label class="fieldset-label" for="tedc-port">{{ __('wage_types.field.sftp_port') }}</label>
                <input id="tedc-port" type="number" name="sftp_port" min="1" max="65535"
                       value="{{ old('sftp_port', $config->sftp_port ?? 22) }}"
                       class="input input-bordered w-full tabular-nums">
                @error('sftp_port')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="sftp" x-cloak>
                <label class="fieldset-label" for="tedc-username">{{ __('wage_types.field.sftp_username') }}</label>
                <input id="tedc-username" type="text" name="sftp_username" maxlength="190"
                       value="{{ old('sftp_username', $config->sftp_username) }}"
                       class="input input-bordered w-full font-mono" autocomplete="off">
                @error('sftp_username')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="sftp" x-cloak>
                <label class="fieldset-label" for="tedc-password">{{ __('wage_types.field.sftp_password') }}</label>
                <input id="tedc-password" type="password" name="sftp_password" maxlength="255"
                       class="input input-bordered w-full" autocomplete="new-password">
                @if ($config->exists && ($config->sftp_password ?? '') !== '')
                    <p class="text-xs text-base-content/60">{{ __('wage_types.field.sftp_password_help') }}</p>
                @endif
                @error('sftp_password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset md:col-span-2" x-show="sftp" x-cloak>
                <label class="fieldset-label" for="tedc-root">{{ __('wage_types.field.sftp_root') }}</label>
                <input id="tedc-root" type="text" name="sftp_root" maxlength="190"
                       value="{{ old('sftp_root', $config->sftp_root) }}"
                       class="input input-bordered w-full font-mono" placeholder="/upload">
                <p class="text-xs text-base-content/60">{{ __('wage_types.field.sftp_root_help') }}</p>
                @error('sftp_root')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        </x-form-group>
    </div>
</x-modal>
