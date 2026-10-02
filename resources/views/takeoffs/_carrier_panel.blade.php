{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _carrier_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aufmaße am Träger (MVP-1058). Erwartet: $carrierType (diary|project|boq), $carrier; optional $class. --}}
@php
    $carrierTakeoffs = \App\Models\Takeoff\Takeoff::query()
        ->where(['diary' => 'diary_entry_id', 'project' => 'project_id', 'boq' => 'bill_of_quantity_id'][$carrierType], $carrier->getKey())
        ->withCount('lines')
        ->orderByDesc('measured_on')->orderByDesc('id')
        ->limit(25)->get();
    $canCreateTakeoff = \Illuminate\Support\Facades\Gate::allows('createFor', [\App\Models\Takeoff\Takeoff::class, $carrier]);
@endphp
@if ($carrierTakeoffs->isNotEmpty() || $canCreateTakeoff)
    <x-card :title="__('takeoff.carrier.section')" icon="straighten" padding="p-0" :class="$class ?? ''">
        @if ($canCreateTakeoff)
            <x-slot:actions>
                <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger :href="route('takeoffs.create', ['carrier' => $carrierType, 'id' => $carrier->sqid])" show-label>{{ __('takeoff.action.create') }}</x-icon-btn>
            </x-slot:actions>
        @endif
        <ul class="divide-y divide-base-300">
            @forelse ($carrierTakeoffs as $carrierTakeoff)
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                    <a class="link min-w-0 flex-1 truncate" href="{{ route('takeoffs.show', $carrierTakeoff) }}">{{ $carrierTakeoff->title }}</a>
                    <span class="text-xs text-muted">{{ $carrierTakeoff->measured_on?->fdate() }} · {{ trans_choice('takeoff.carrier.lines', $carrierTakeoff->lines_count, ['count' => $carrierTakeoff->lines_count]) }}</span>
                    <x-status-badge size="xs" outline :tone="$carrierTakeoff->status->tone()">{{ $carrierTakeoff->status->label() }}</x-status-badge>
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-muted">{{ __('takeoff.carrier.none') }}</li>
            @endforelse
        </ul>
    </x-card>
@endif
