{{--
  Created on   : Sun Jun 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : action-form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'action',
    'method' => 'POST',
    'confirm' => null,        // Meldungstext → aktiviert data-confirm-dialog
    'confirmIcon' => null,
    'confirmTone' => null,
    'confirmLabel' => null,
])

{{--
    <x-action-form> — kompaktes Inline-Formular für einzelne Aktionen
    (Archivieren, Wiederherstellen, Löschen, …). Setzt @csrf und – bei
    method != GET/POST – automatisch das @method-Spoofing. Mit :confirm
    wird der globale Bestätigungsdialog (data-confirm-*) ausgelöst.

    Beispiel:
        <x-action-form :action="route('customers.archive', $customer)">
            <x-icon-btn icon="archive" size="sm" type="submit" show-label>{{ __('Archivieren') }}</x-icon-btn>
        </x-action-form>

        <x-action-form :action="route('attachments.destroy', $att)" method="DELETE"
                       :confirm="__('Anhang löschen?')" confirm-icon="delete"
                       confirm-tone="error" :confirm-label="__('Löschen')">
            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
        </x-action-form>
--}}

@php
    $method     = strtoupper($method);
    $spoof      = ! in_array($method, ['GET', 'POST'], true);
    $formMethod = $spoof ? 'POST' : $method;
    $hasClass   = $attributes->has('class');
@endphp

<form method="{{ $formMethod }}" action="{{ $action }}"
      {{ $attributes->class($hasClass ? [] : ['inline']) }}
      @if ($confirm) data-confirm-dialog data-confirm-message="{{ $confirm }}" @endif
      @if ($confirmIcon) data-confirm-icon="{{ $confirmIcon }}" @endif
      @if ($confirmTone) data-confirm-tone="{{ $confirmTone }}" @endif
      @if ($confirmLabel) data-confirm-label="{{ $confirmLabel }}" @endif>
    @csrf
    @if ($spoof) @method($method) @endif
    {{ $slot }}
</form>
