{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : action-menu.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'label',
    'icon'      => null,
    'tone'      => 'ghost',   // ghost | primary | secondary | info | success | warning | outline
    'size'      => 'sm',      // xs | sm | md
    'align'     => 'end',     // end | start
    'iconOnly'  => false,     // nur Symbol, Label als Tooltip und aria-label (z. B. in Tabellenzeilen)
    'placement' => null,      // Seitenkopf: bar | menu | danger (sonst auto, MVP-966)
])

{{--
    <x-action-menu> — Aktionen hinter einem Knopf „Label ▾“ (MVP-967), etwa
    „Export ▾“ oder „+ Hinzufügen ▾“. Der Slot nimmt dieselben Bausteine wie
    der Aktionsslot des Seitenkopfs (x-icon-btn mit show-label, x-button,
    x-action-form); im Menü erscheinen sie als Einträge. Überschriften als
    <p class="wd-menu-heading">.

    Im ⋯-Menü des Seitenkopfs wird das Menü zum Abschnitt mit dem Label als
    Überschrift; mit nur einer Aktion ersetzt diese das Menü
    (resources/js/action-menu.js).

    Beispiel:
        <x-action-menu icon="download" :label="__('Export')">
            <x-icon-btn icon="receipt" size="sm" :href="route('invoices.einvoice', $invoice)" show-label>{{ __('invoicing.einvoice.button') }}</x-icon-btn>
            <x-icon-btn icon="receipt" size="sm" :href="route('invoices.zugferd', $invoice)" show-label>{{ __('invoicing.einvoice.zugferd.button') }}</x-icon-btn>
        </x-action-menu>
--}}

@php
    $sizeClass = match ($size) {
        'xs' => 'btn-xs',
        'md' => '',
        default => 'btn-sm',
    };
    $toneClass = match ($tone) {
        'primary'   => 'btn-primary',
        'secondary' => 'btn-secondary',
        'info'      => 'btn-info',
        'success'   => 'btn-success',
        'warning'   => 'btn-warning',
        'outline'   => 'btn-outline',
        default     => 'btn-ghost',
    };
    $placementAttr = in_array($placement, ['bar', 'menu', 'danger'], true) ? $placement : null;
@endphp

<details {{ $attributes->class(['dropdown', 'dropdown-end' => $align !== 'start']) }}
         data-menu data-action-menu
         @if ($placementAttr) data-toolbar-placement="{{ $placementAttr }}" @endif>
    <summary class="btn {{ $sizeClass }} {{ $toneClass }} gap-1"
             @if ($iconOnly) title="{{ $label }}" aria-label="{{ $label }}" @endif>
        @if ($icon)
            <x-icon :name="$icon" />
        @endif
        @unless ($iconOnly)
            <span>{{ $label }}</span>
            <x-icon name="expand_more" />
        @endunless
    </summary>
    <div class="dropdown-content wd-action-menu mt-1 flex w-64 max-w-[calc(100vw-2rem)] flex-col gap-0.5 rounded-box border border-base-300 bg-base-100 p-1.5 shadow-lg" data-action-menu-items>{{ $slot }}</div>
</details>
