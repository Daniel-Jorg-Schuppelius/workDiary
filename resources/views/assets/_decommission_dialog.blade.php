{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _decommission_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset\Asset $asset */
@endphp

<x-modal
    :title="__('asset.decommission.title')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="power_settings_new"
    tone="error"
    size="md"
    :action="route('assets.decommission', $asset)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('asset.decommission.action')">

    <p class="mb-2 text-sm text-base-content/70">{{ __('asset.decommission.intro') }}</p>

    @if ($isCheckedOut)
        <div role="alert" class="alert alert-warning mb-3">
            <x-icon name="warning" />
            <span>{{ __('asset.decommission.error.checked_out') }}</span>
        </div>
    @endif

    @if ($activePlanCount > 0 || $activeDutyCount > 0)
        <ul class="mb-3 list-disc pl-5 text-sm text-base-content/70">
            @if ($activePlanCount > 0)
                <li>{{ __('asset.decommission.plans', ['count' => $activePlanCount]) }}</li>
            @endif
            @if ($activeDutyCount > 0)
                <li>{{ __('asset.decommission.duties', ['count' => $activeDutyCount]) }}</li>
            @endif
        </ul>
    @endif

    <x-input-field type="date" name="decommissioned_on" :label="__('asset.decommission.date')" required
                   :max="now()->toDateString()" :value="old('decommissioned_on', now()->toDateString())" />

    <x-textarea-field name="decommission_reason" :label="__('asset.decommission.reason')" required rows="3" maxlength="500"
                      :value="old('decommission_reason')" />
</x-modal>
