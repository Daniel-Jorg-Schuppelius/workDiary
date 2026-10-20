{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Portal-Objektakte Detail (Feature 027, Rang 50): kundensichtbarer Schnitt —
  Stammdaten, Prüf-/Wartungstermine, abgeschlossene Wartungen, unterschriebene
  freigegebene Protokolle mit PDF. Interne Defekt-Details bleiben bewusst draußen.
--}}
@extends('customer.layout')

@section('content')
    <x-page-toolbar :title="$asset->name" :subtitle="$asset->serial_no ? __('Seriennummer') . ': ' . $asset->serial_no : null"
                    :back="route('customer.assets.index')" :back-label="__('Objekte')" class="mb-4" />

    <div class="space-y-6">
        <x-card as="section" :title="__('Prüf- & Wartungstermine')" icon="event">
            @if ($asset->maintenancePlans->isEmpty())
                <x-empty-state icon="event" :title="__('Keine Termine hinterlegt.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('Bezeichnung') }}</x-table.th>
                            <x-table.th>{{ __('Nächste Fälligkeit') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($asset->maintenancePlans as $plan)
                        <tr>
                            <td>{{ $plan->title }}</td>
                            <td class="tabular-nums">{{ optional($plan->next_due_on)->fdate() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card as="section" :title="__('Abgeschlossene Wartungen')" icon="build">
            @if ($timeline === [])
                <x-empty-state icon="build" :title="__('Noch keine abgeschlossenen Wartungen.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($timeline as $event)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span>{{ $event['payload']['title'] ?? __('Wartung abgeschlossen') }}</span>
                            <span class="opacity-70 tabular-nums">{{ $event['occurred_at'] !== null ? \Illuminate\Support\Carbon::parse($event['occurred_at'])->fdate() : '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card as="section" :title="__('Protokolle')" icon="description">
            @if ($asset->protocols->isEmpty())
                <x-empty-state icon="description" :title="__('Keine freigegebenen Protokolle.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($asset->protocols as $protocol)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span class="min-w-0">{{ $protocol->title }}</span>
                            <span class="flex items-center gap-2">
                                <span class="opacity-70">{{ $protocol->status->label() }} · {{ optional($protocol->occurred_at)->fdate() }}</span>
                                <x-button :href="route('customer.assets.protocols.pdf', [$asset, $protocol])" tone="outline" size="sm" icon="download">{{ __('PDF herunterladen') }}</x-button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
