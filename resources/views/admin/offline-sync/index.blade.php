{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Offline-Synchronisierung'))
@section('nav-title', __('Offline-Synchronisierung'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $commands */
    /** @var array<string, string> $filters */
    /** @var \Illuminate\Support\Collection $counts */
    /** @var \Illuminate\Support\Collection $typeOptions */
    use App\Enums\Sync\SyncCommandStatus;
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Jede Zeile ein Offline-Befehl der Geräte-Outbox mit seinem Ergebnis. Abgewiesene und Konflikt-Befehle sind nicht im Bestand gelandet — dort muss jemand nachfassen.')">
    <x-filter-bar :action="route('admin.offline-sync.index')" :reset="route('admin.offline-sync.index')">
        <x-date-range class="w-80 shrink-0" :label="false"
                      from-name="from" to-name="to"
                      :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''"
                      :from-label="__('von')" :to-label="__('bis')" />
        <select name="status" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('Ergebnis') }}">
            <option value="">{{ __('Alle Ergebnisse') }}</option>
            @foreach (SyncCommandStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                    {{ $status->label() }} ({{ $counts[$status->value] ?? 0 }})
                </option>
            @endforeach
        </select>
        <select name="type" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('Befehlstyp') }}">
            <option value="">{{ __('Alle Befehlstypen') }}</option>
            @foreach ($typeOptions as $type)
                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($commands->isEmpty())
        <x-empty-state framed
            icon="cloud_sync"
            :title="__('Keine Offline-Befehle im gewählten Ausschnitt.')"
            :message="__('Hier erscheint jeder Befehl, den ein Gerät offline erfasst und später übertragen hat.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Erfasst (offline)') }}</th>
                    <th>{{ __('Übertragen') }}</th>
                    <th>{{ __('Mitarbeiter') }}</th>
                    <th>{{ __('Befehlstyp') }}</th>
                    <th>{{ __('Ergebnis') }}</th>
                    <th>{{ __('Referenz / Fehler') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($commands as $command)
                <tr class="hover">
                    {{-- captured_at = Gerätezeit der Offline-Erfassung; die Spanne
                         zur Übertragung ist die Offline-Latenz. --}}
                    <td class="whitespace-nowrap text-sm">{{ $command->captured_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="whitespace-nowrap text-sm">{{ $command->created_at?->format('d.m.Y H:i') }}</td>
                    <td class="text-sm">{{ $command->user?->name ?? '—' }}</td>
                    <td class="font-mono text-xs">{{ $command->type }}</td>
                    <td><x-status-badge :tone="$command->result_status->tone()" size="sm">{{ $command->result_status->label() }}</x-status-badge></td>
                    <td class="text-xs">
                        @if ($command->result_errors)
                            <span class="text-error">{{ implode('; ', array_map(strval(...), \Illuminate\Support\Arr::flatten($command->result_errors))) }}</span>
                        @elseif ($command->result_ref)
                            <span class="font-mono text-base-content/70">{{ $command->result_ref }}</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$commands" standing />
    @endif
</x-index-page>
@endsection
