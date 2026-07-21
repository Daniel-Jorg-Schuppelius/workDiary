@extends('layouts.app')
@section('title', __('Geräte zusammenführen'))
@section('nav-title', __('Geräte zusammenführen'))

@section('content')
@if ($target === null)
    <x-index-page :subtitle="__('Wähle das Zielgerät, in das „:name“ überführt werden soll. Alle Verknüpfungen (Sitzungen, Wartungen, Anhänge, Geräte-IDs …) wandern aufs Ziel; das Duplikat wird gelöscht.', ['name' => $source->name ?: $source->asset_no])">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('assets.show', $source)" show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        <form method="GET" action="{{ route('assets.merge.compare') }}"
              class="max-w-xl rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <input type="hidden" name="source" value="{{ $source->sqid }}">
            <div class="flex items-end gap-2">
                <label class="form-control flex-1">
                    <span class="label-text text-xs">{{ __('Zielgerät') }}</span>
                    <select name="target" required class="select select-sm select-bordered w-full">
                        <option value="">{{ __('— Gerät wählen —') }}</option>
                        @foreach ($targets as $candidate)
                            <option value="{{ $candidate->sqid }}">{{ $candidate->name ?: $candidate->asset_no }} ({{ $candidate->asset_no }})</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Vergleichen') }}</button>
            </div>
        </form>
    </x-index-page>
@else
    @php
        /** @var \App\Models\Asset $source */
        /** @var \App\Models\Asset $target */

        // Reine Anzeigefelder (Identität) — nicht übersteuerbar.
        $identityRows = [
            [__('Name'), (string) $target->name, (string) $source->name, null],
            [__('Asset-Nr.'), (string) $target->asset_no, (string) $source->asset_no, null],
        ];

        // Übersteuerbare Felder — Feldnamen müssen zu AssetMergeService::FILLABLE_FROM_SOURCE passen.
        $rows = [
            [__('Kunde'), $target->customer?->name, $source->customer?->name, 'customer_id'],
            [__('Fremdkunde (Endkunde)'), $target->foreignCustomer?->name, $source->foreignCustomer?->name, 'foreign_customer_id'],
            [__('Raum'), $target->room?->name, $source->room?->name, 'room_id'],
            [__('Hersteller'), $target->manufacturer, $source->manufacturer, 'manufacturer'],
            [__('Modell'), $target->model, $source->model, 'model'],
            [__('Seriennummer'), $target->serial_no, $source->serial_no, 'serial_no'],
            [__('Inventarnummer'), $target->inventory_no, $source->inventory_no, 'inventory_no'],
            [__('Standort (Freitext)'), $target->location_text, $source->location_text, 'location_text'],
            [__('In Betrieb seit'), $target->commissioned_on?->format('d.m.Y'), $source->commissioned_on?->format('d.m.Y'), 'commissioned_on'],
            [__('Garantie bis'), $target->warranty_until?->format('d.m.Y'), $source->warranty_until?->format('d.m.Y'), 'warranty_until'],
            [__('Notizen'), $target->notes, $source->notes, 'notes'],
        ];
    @endphp

    <x-index-page :subtitle="__('Wähle pro Feld, ob der Wert des zu löschenden Geräts den Ziel-Wert ersetzen soll. Leere Ziel-Felder werden ohnehin aus der Quelle aufgefüllt; befüllte Ziel-Felder bleiben unangetastet.')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('assets.show', $source)" show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        <form method="POST" action="{{ route('assets.merge') }}"
              data-confirm-dialog
              data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Das Quell-Gerät wird gelöscht.', ['source' => $source->name ?: $source->asset_no, 'target' => $target->name ?: $target->asset_no]) }}"
              data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
            @csrf
            <input type="hidden" name="source" value="{{ $source->sqid }}">
            <input type="hidden" name="target" value="{{ $target->sqid }}">

            <x-table>
                <x-slot:head>
                    <tr>
                        <th class="w-44">{{ __('Feld') }}</th>
                        <th>
                            <span class="badge badge-sm badge-success">{{ __('Bleibt') }}</span>
                            <a href="{{ route('assets.show', $target) }}" class="link ml-1">{{ $target->name ?: $target->asset_no }}</a>
                        </th>
                        <th>
                            <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                            <a href="{{ route('assets.show', $source) }}" class="link ml-1">{{ $source->name ?: $source->asset_no }}</a>
                        </th>
                        <th class="w-40 text-center">{{ __('Wert aus Quelle übernehmen') }}</th>
                    </tr>
                </x-slot:head>

                @foreach (array_merge($identityRows, $rows) as [$label, $tv, $sv, $field])
                    @php
                        $tv = (string) ($tv ?? '');
                        $sv = (string) ($sv ?? '');
                    @endphp
                    @if ($field === null || $tv !== '' || $sv !== '')
                        <tr>
                            <td class="text-base-content/60">{{ $label }}</td>
                            <td class="{{ $tv === '' ? 'text-base-content/30' : '' }}">{{ $tv !== '' ? $tv : '—' }}</td>
                            <td class="{{ $tv !== $sv ? 'text-warning' : 'text-base-content/50' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                            <td class="text-center">
                                @if ($field !== null && $sv !== '' && $tv !== $sv)
                                    <input type="checkbox" class="checkbox checkbox-sm"
                                           name="prefer_source[]" value="{{ $field }}">
                                @else
                                    <span class="text-base-content/30">—</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </x-table>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <a href="{{ route('assets.merge.compare', ['source' => $target->sqid, 'target' => $source->sqid]) }}"
                   class="btn btn-sm btn-outline">{{ __('Richtung tauschen') }}</a>
                <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
            </div>
        </form>
    </x-index-page>
@endif
@endsection
