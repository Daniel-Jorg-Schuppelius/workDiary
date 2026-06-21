{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _defect_resolve_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset $asset */
    /** @var \App\Models\AssetDefect $defect */
    /** @var string $action */
    $isWriteOff = $action === 'writeOff';
@endphp

<x-modal
    :title="$isWriteOff ? __('Defekt ausbuchen') : __('Defekt erledigen')"
    :eyebrow="$defect->title"
    :icon="$isWriteOff ? 'delete_forever' : 'check'"
    :tone="$isWriteOff ? 'error' : 'success'"
    size="sm"
    :action="route('assets.defects.transition', [$asset, $defect])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isWriteOff ? __('Ausbuchen') : __('Erledigen')">

    <input type="hidden" name="action" value="{{ $action }}" />

    <x-textarea-field name="resolution_note" :label="__('Lösungsnotiz')" required rows="3" :value="old('resolution_note')" />
</x-modal>
