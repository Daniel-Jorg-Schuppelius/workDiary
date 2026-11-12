{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _recommission_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wieder in Betrieb nehmen (MVP-1628). Erwartet: $asset --}}
@php
    /** @var \App\Models\Asset\Asset $asset */
@endphp

<x-modal
    :title="__('asset.recommission.title')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="restart_alt"
    tone="success"
    size="md"
    :action="route('assets.recommission', $asset)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('asset.recommission.action')">

    <p class="mb-2 text-sm text-base-content/70">{{ __('asset.recommission.intro') }}</p>

    @if ($asset->decommissioned_on)
        <p class="mb-3 text-sm text-muted">
            {{ __('asset.decommission.since', ['date' => $asset->decommissioned_on->fdate()]) }}@if ($asset->decommission_reason) — {{ $asset->decommission_reason }}@endif
        </p>
    @endif

    <x-textarea-field name="recommission_reason" :label="__('asset.recommission.reason')" required rows="3" maxlength="500"
                      :value="old('recommission_reason')" />
</x-modal>
