{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _intake_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eingangsakte nach der Übernahme in den Druck (MVP-1076) — erwartet: $intake, $target (PrintOrder). --}}
@php
    /** @var \App\Models\Print\PrintOrder $target */
    $target->loadMissing(['manufacturingOrder', 'documentVersion']);
@endphp
<x-card :title="__('print.intake.panel_title')" icon="print">
    <x-detail-grid class="grid-cols-2">
        <x-detail-grid.row :label="__('Status')">
            <x-status-badge size="sm" outline :tone="$target->status->tone()">{{ $target->status->label() }}</x-status-badge>
        </x-detail-grid.row>
        <x-detail-grid.row :label="__('print.field.file')">{{ $target->documentVersion?->original_name ?? __('print.intake.no_production_file') }}</x-detail-grid.row>
        <x-detail-grid.row :label="__('print.intake.customer_approval')" class="col-span-2">
            @include('print.orders._customer_approval_state', ['order' => $target])
        </x-detail-grid.row>
    </x-detail-grid>
    @can('view', $target)
        <x-button class="mt-3" size="sm" icon="open_in_new" :href="route('print-orders.show', $target)">{{ __('print.intake.open_order') }}</x-button>
    @endcan
</x-card>
