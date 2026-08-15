{{--
  Created on   : Sat Aug 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _adopt_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $isDialog, $source, $counts{items, groups} --}}
@php
    $isDialog = $isDialog ?? false;
@endphp

<x-modal
    :title="__('procurement.catalog.adopt.title')"
    :eyebrow="__('procurement.catalog.title')"
    icon="library_add"
    tone="primary"
    :action="route('supplier-catalogs.adopt', $source)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('procurement.catalog.action.adopt')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('supplier-catalogs.adopt-form', $source) . '?dialog=1' }}">
    @endif

    <p class="text-sm">{{ __('procurement.catalog.adopt.summary', ['items' => $counts['items'], 'groups' => $counts['groups']]) }}</p>
    <p class="text-xs opacity-60">{{ __('procurement.catalog.adopt.hint') }}</p>
</x-modal>
