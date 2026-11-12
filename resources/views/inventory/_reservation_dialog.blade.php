{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reservation_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reservierung ändern (MVP-1585): Priorität und Teilfreigabe. Erwartet: $reservation --}}
@php
    $qty = static fn ($value): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((string) $value, 4, withThousandsSeparator: true, trimTrailingZeros: true);
    $unit = $reservation->variant?->article?->base_unit;
@endphp
<x-modal
    :title="__('inventory.reservation.edit')"
    :eyebrow="trim(($reservation->variant?->article?->name ?? '') . ' · ' . ($reservation->warehouse?->name ?? ''), ' ·')"
    icon="event_available"
    tone="primary"
    :action="route('inventory.reservations.update', $reservation)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <input type="hidden" name="_dialog_url" value="{{ route('inventory.reservations.edit', $reservation) }}?dialog=1">

    <x-detail-grid>
        <x-detail-grid.row :label="__('inventory.reservation.reserved')">{{ $qty($reservation->quantity) }} {{ $unit }}</x-detail-grid.row>
        <x-detail-grid.row :label="__('inventory.reservation.consumed')">{{ $qty($reservation->consumed_qty) }} {{ $unit }}</x-detail-grid.row>
        <x-detail-grid.row :label="__('inventory.reservation.open')">{{ $qty($reservation->openQuantity()) }} {{ $unit }}</x-detail-grid.row>
        <x-detail-grid.row :label="__('inventory.reservation.reserved_at')">{{ $reservation->reserved_at?->fdatetime() }}</x-detail-grid.row>
    </x-detail-grid>

    <x-form-group :legend="__('inventory.reservation.edit')" icon="event_available" tone="primary" cols="2" class="mt-4">
        <x-input-field name="priority" id="reservation-priority" type="number" min="1" max="999" step="1" required inputmode="numeric"
                       :label="__('inventory.overview.priority')" :hint="__('inventory.reservation.priority_hint')"
                       :value="old('priority', $reservation->priority)" />
        <x-input-field name="release_qty" id="reservation-release-qty" type="number" min="0.0001" step="0.0001" inputmode="decimal"
                       :label="__('inventory.reservation.release_qty')" :hint="__('inventory.reservation.release_hint')"
                       :value="old('release_qty')" />
    </x-form-group>

    @if (($redistributionTargets ?? collect())->isNotEmpty())
        {{-- Umverteilen (MVP-1626): offene Menge an eine andere Reservierung desselben Bestands abgeben. --}}
        <x-form-group :legend="__('inventory.reservation.redistribute')" :description="__('inventory.reservation.redistribute_hint')" icon="swap_horiz" tone="primary" cols="2" class="mt-4">
            <x-select-field name="redistribute_to" id="reservation-redistribute-to" :label="__('inventory.reservation.redistribute_to')">
                <option value="">—</option>
                @foreach ($redistributionTargets as $target)
                    <option value="{{ $target->sqid }}" @selected(old('redistribute_to') === $target->sqid)>
                        {{ __('inventory.reservation.redistribute_option', ['priority' => $target->priority, 'open' => $qty($target->openQuantity()), 'unit' => $unit, 'date' => $target->reserved_at?->fdate()]) }}@if ($target->source_type) · {{ \App\Support\EntityType::label($target->source_type) }}@endif
                    </option>
                @endforeach
            </x-select-field>
            <x-input-field name="redistribute_qty" id="reservation-redistribute-qty" type="number" min="0.0001" step="0.0001" inputmode="decimal"
                           :label="__('inventory.reservation.redistribute_qty')" :hint="__('inventory.reservation.redistribute_qty_hint')"
                           :value="old('redistribute_qty')" />
        </x-form-group>
    @endif
</x-modal>
