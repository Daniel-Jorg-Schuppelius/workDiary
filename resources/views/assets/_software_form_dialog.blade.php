{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _software_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset $asset */
    /** @var \App\Models\SoftwareInstallation|null $installation */
    $isEdit = $installation?->exists ?? false;

    if ($isOperatingSystem) {
        $action = $isEdit
            ? route('assets.software-installations.update', [$asset, $installation])
            : route('assets.software-installations.store', $asset);
        $method = $isEdit ? 'PUT' : 'POST';
        $title = $isEdit ? __('Betriebssystem ersetzen') : __('Betriebssystem zuweisen');
        $submitLabel = $isEdit ? __('Speichern') : __('Zuweisen');
        $icon = 'desktop_windows';
    } else {
        $action = route('assets.software-installations.store', $asset);
        $method = 'POST';
        $title = __('Software zuweisen');
        $submitLabel = __('Hinzufügen');
        $icon = 'apps';
    }
@endphp

<x-modal
    :title="$title"
    :eyebrow="$asset->name ?: $asset->asset_no"
    :icon="$icon"
    tone="primary"
    size="md"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$submitLabel">

    <input type="hidden" name="is_operating_system" value="{{ $isOperatingSystem ? 1 : 0 }}" />

    @unless ($isEdit)
        <x-select-field name="software_id" :label="__('Software')" required>
            @forelse ($softwareCatalog as $sw)
                <option value="{{ $sw->sqid }}" @selected(old('software_id') === $sw->sqid)>{{ $sw->name }}@if ($sw->vendor) — {{ $sw->vendor }}@endif</option>
            @empty
                <option value="" disabled>{{ __('Kein passender Software-Katalog vorhanden.') }}</option>
            @endforelse
        </x-select-field>
    @endunless

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="version" :label="__('Version')" :value="old('version', $installation?->version)" />
        <x-input-field name="seats" type="number" min="1" :label="__('Sitze')" :value="old('seats', $installation?->seats)" />
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('Lizenzschlüssel') }}</span>
            <input type="text" name="license_key" value="{{ old('license_key', $installation?->license_key) }}" class="input input-bordered w-full">
        </label>
        <x-input-field name="installed_on" type="date" :label="__('Installiert am')" :value="old('installed_on', $installation?->installed_on?->format('Y-m-d'))" />
        <x-input-field name="expires_on" type="date" :label="__('Läuft ab')" :value="old('expires_on', $installation?->expires_on?->format('Y-m-d'))" />
    </div>
</x-modal>
