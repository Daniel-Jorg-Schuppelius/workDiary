{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Anmelderichtlinie bearbeiten (Feature 205, MVP-1654–1656) --}}
@php
    /** @var \App\Models\Auth\OrganizationLoginPolicy $policy */
    /** @var array<string, string> $hourRoleOptions */
    /** @var array<string, string> $passkeyRoleOptions */
    $hours = $policy->hoursByWeekday();
    $selectedHourRoles = (array) old('hours_roles', $policy->hourRoleNames());
    $selectedPasskeyRoles = (array) old('passkey_roles', $policy->passkeyRoleNames());
    $monday = \Carbon\CarbonImmutable::now()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
@endphp
<x-modal
    :title="__('login_policy.title.edit')"
    icon="admin_panel_settings"
    tone="primary"
    size="wide"
    :action="route('admin.login-policy.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('login_policy.action.save')"
>
    {{-- Anmeldezeiten (MVP-1654) --}}
    <x-form-group :legend="__('login_policy.section.hours')" :description="__('login_policy.hint.hours')" icon="schedule" tone="primary" cols="2">
        <x-select-field name="hours_mode" :label="__('login_policy.field.mode')">
            @foreach (\App\Enums\Auth\LoginPolicyMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('hours_mode', $policy->hours_mode->value) === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </x-select-field>

        <div class="fieldset">
            <span class="fieldset-label">{{ __('login_policy.field.hours_roles') }}</span>
            <div class="flex flex-wrap gap-3">
                @foreach ($hourRoleOptions as $value => $label)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="hours_roles[]" value="{{ $value }}" class="checkbox checkbox-sm" @checked(in_array($value, $selectedHourRoles, true))>
                        <span class="label-text text-sm">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs opacity-70">{{ __('login_policy.hint.hours_roles') }}</p>
            @error('hours_roles.*')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-2">
            <span class="fieldset-label">{{ __('login_policy.field.windows') }}</span>
            <div class="grid gap-2 sm:grid-cols-2">
                @for ($day = 1; $day <= 7; $day++)
                    @php $window = $hours[$day] ?? ['from' => '', 'until' => '']; @endphp
                    <div class="flex items-center gap-2">
                        <span class="w-28 text-sm">{{ $monday->addDays($day - 1)->translatedFormat('l') }}</span>
                        <input type="time" name="hours[{{ $day }}][from]" value="{{ old("hours.$day.from", $window['from']) }}"
                               aria-label="{{ __('login_policy.field.from') }}" class="input input-sm input-bordered w-28">
                        <span class="text-muted">–</span>
                        <input type="time" name="hours[{{ $day }}][until]" value="{{ old("hours.$day.until", $window['until'] === \App\Services\Auth\LoginPolicy\LoginHours::END_OF_DAY ? '00:00' : $window['until']) }}"
                               aria-label="{{ __('login_policy.field.until') }}" class="input input-sm input-bordered w-28">
                    </div>
                    @error("hours.$day")<p class="text-sm text-error sm:col-span-2">{{ $message }}</p>@enderror
                @endfor
            </div>
            <p class="mt-1 text-xs opacity-70">{{ __('login_policy.hint.windows') }}</p>
            @error('hours')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    {{-- Netzbereiche (MVP-1655) --}}
    <x-form-group :legend="__('login_policy.section.networks')" :description="__('login_policy.hint.networks')" icon="lan" tone="info" cols="2">
        <x-select-field name="network_mode" :label="__('login_policy.field.mode')">
            @foreach (\App\Enums\Auth\LoginPolicyMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('network_mode', $policy->network_mode->value) === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </x-select-field>

        <x-textarea-field name="networks" :label="__('login_policy.field.networks')" rows="5"
                          :value="old('networks', implode(PHP_EOL, $policy->networkEntries()))"
                          :hint="__('login_policy.hint.networks_format')" />
    </x-form-group>

    {{-- Passkey-Pflicht (MVP-1656) --}}
    <x-form-group :legend="__('login_policy.section.passkey')" :description="__('login_policy.hint.passkey')" icon="key" tone="ghost">
        <div class="fieldset">
            <span class="fieldset-label">{{ __('login_policy.field.passkey_roles') }}</span>
            <div class="flex flex-wrap gap-3">
                @foreach ($passkeyRoleOptions as $value => $label)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="passkey_roles[]" value="{{ $value }}" class="checkbox checkbox-sm" @checked(in_array($value, $selectedPasskeyRoles, true))>
                        <span class="label-text text-sm">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('passkey_roles.*')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>
