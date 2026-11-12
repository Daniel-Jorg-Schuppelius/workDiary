{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _return_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset\Asset $asset */
    /** @var \App\Models\Asset\AssetAssignment $assignment */
@endphp

<x-modal
    :title="__('asset.return.title')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="login"
    tone="success"
    size="md"
    :action="route('assets.checkout.return', [$asset, $assignment])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Zurücknehmen')">

    @if ($assignment->condition_out)
        <p class="mb-2 text-sm text-base-content/70">{{ __('Zustand bei Ausgabe') }}: {{ $assignment->condition_out }}</p>
    @endif

    <x-input-field name="condition_in" :label="__('asset.return.condition_in')" maxlength="180"
                   :value="old('condition_in')" :placeholder="__('asset.return.condition_placeholder')" />

    <x-textarea-field name="return_note" :label="__('Notiz')" rows="2" maxlength="2000" :value="old('return_note')" />
</x-modal>
