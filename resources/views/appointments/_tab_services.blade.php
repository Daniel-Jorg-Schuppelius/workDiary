{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_services.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter „Buchbare Leistungsarten“. Variablen: $services, $canManage --}}
@if ($services->isEmpty())
    <x-empty-state framed icon="design_services"
        :title="__('Noch keine Leistungsart buchbar — nichts ist automatisch buchbar.')"
        :message="__('Erst eine angelegte Leistungsart erscheint im Kundenportal zur Terminanfrage.')" />
@else
    <x-table scroll="flex" :caption="__('Buchbare Leistungsarten')">
        <x-slot:head>
            <tr>
                <th>{{ __('Leistung') }}</th>
                <th class="text-right">{{ __('Dauer') }}</th>
                <th class="text-right">{{ __('Puffer') }}</th>
                <th class="text-right">{{ __('Vorlauf') }}</th>
                <th class="text-right">{{ __('Stornofrist') }}</th>
                <th>{{ __('Qualifikation') }}</th>
                <th>{{ __('Standort') }}</th>
                <th>{{ __('Status') }}</th>
                @if ($canManage)
                    <th class="text-right">{{ __('Aktionen') }}</th>
                @endif
            </tr>
        </x-slot:head>
        @foreach ($services as $service)
            <tr @class(['hover', 'opacity-60' => ! $service->active])>
                <td class="max-w-80">
                    <div class="font-medium">{{ $service->title }}</div>
                    @if (filled($service->description))
                        <div class="truncate text-xs text-muted" title="{{ $service->description }}">{{ $service->description }}</div>
                    @endif
                </td>
                <td class="whitespace-nowrap text-right tabular-nums">{{ $service->duration_minutes }} min</td>
                <td class="whitespace-nowrap text-right tabular-nums">{{ $service->buffer_minutes }} min</td>
                <td class="whitespace-nowrap text-right tabular-nums">{{ $service->lead_time_hours }} h</td>
                <td class="whitespace-nowrap text-right tabular-nums">{{ $service->cancel_hours }} h</td>
                <td>{{ $service->requiredQualification?->name ?? '—' }}</td>
                <td>{{ $service->site?->name ?? '—' }}</td>
                <td>
                    <x-status-badge :tone="$service->active ? 'success' : 'ghost'" size="sm">{{ $service->active ? __('Aktiv') : __('Inaktiv') }}</x-status-badge>
                </td>
                @if ($canManage)
                    <td class="text-right">
                        <x-action-form :action="route('appointments.services.toggle', $service)">
                            <x-icon-btn :icon="$service->active ? 'pause' : 'play_arrow'" size="sm" type="submit"
                                        :label="$service->active ? __('Deaktivieren') : __('Aktivieren')" />
                        </x-action-form>
                    </td>
                @endif
            </tr>
        @endforeach
    </x-table>
@endif
