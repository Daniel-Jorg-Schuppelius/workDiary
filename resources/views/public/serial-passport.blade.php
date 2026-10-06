{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : serial-passport.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-public-page :title="__('inventory.serial.verify.title')" main="max-w-md mx-auto p-4 flex flex-col gap-4">
    <x-card class="flex flex-col gap-2">
        <h1 class="card-title">{{ __('inventory.serial.verify.title') }}</h1>
        <p class="text-sm opacity-70">{{ $orgName }}</p>
        <form method="GET" action="{{ route('serials.public-passport', $token) }}" class="flex items-end gap-2 mt-2">
            <input aria-label="{{ __('inventory.serial.verify.placeholder') }}" name="serial" value="{{ $query }}" autofocus
                   placeholder="{{ __('inventory.serial.verify.placeholder') }}"
                   class="input input-bordered w-full font-mono">
            <x-button type="submit" tone="primary">{{ __('inventory.serial.action.search') }}</x-button>
        </form>
    </x-card>

    @if ($searched)
        @if ($serial === null)
            <div role="alert" class="alert alert-error">{{ __('inventory.serial.verify.not_found') }}</div>
        @else
            <x-card class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <span class="font-mono">{{ $serial->serial_no }}</span>
                    <x-status-badge tone="plain" size="md">{{ $serial->status->label() }}</x-status-badge>
                </div>
                {{-- Bewusst ohne personenbezogene Daten (kein Kunde). --}}
                <x-detail-grid class="mt-2">
                    <x-detail-grid.row :label="__('inventory.serial.field.article')">{{ $serial->article?->name }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('inventory.serial.field.source')">{{ $serial->source->label() }}</x-detail-grid.row>
                    @if ($parcel !== null)
                        <x-detail-grid.row :label="__('inventory.serial.field.parcel')">{{ __('shipping.parcel.label', ['no' => $parcel->position, 'of' => $parcelCount]) }}</x-detail-grid.row>
                    @endif
                </x-detail-grid>
            </x-card>
        @endif
    @endif
</x-public-page>
