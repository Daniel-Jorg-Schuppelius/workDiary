{{--
  Created on   : Mon May 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Import-Vorschau') . ' — WorkDiary')
@section('nav-title', __('Import: Spalten zuordnen'))

@section('content')
@php
    // Feldnamen = Schlüssel, die ScheduleImportController@confirm über map[Spalte] erwartet.
    $fields = [
        'date' => __('Datum'),
        'user' => __('Mitarbeiter'),
        'shift_type' => __('Schichttyp'),
        'start_time' => __('Beginn (Von)'),
        'end_time' => __('Ende (Bis)'),
        'note' => __('Notiz / Bemerkung'),
    ];
    $hints = [
        'date' => ['datum', 'date'],
        'user' => ['mitarbeiter', 'name', 'user'],
        'shift_type' => ['schicht', 'typ', 'type'],
        'start_time' => ['von', 'start', 'beginn'],
        'end_time' => ['bis', 'end', 'ende'],
        'note' => ['notiz', 'note', 'bemerkung'],
    ];
    $guesses = [];
    foreach ($headers as $i => $h) {
        $guesses[$i] = 'skip';
        foreach ($hints as $field => $needles) {
            if (! in_array($field, $guesses, true) && \Illuminate\Support\Str::contains(mb_strtolower((string) $h), $needles)) {
                $guesses[$i] = $field;
                break;
            }
        }
    }
@endphp
<div class="w-full px-4 py-8">

    <div class="mb-4 flex items-center gap-3">
        <x-button :href="route('schedule.import')" tone="ghost">← {{ __('Zurück') }}</x-button>
    </div>

    <div class="mb-4 flex items-center gap-3 rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm">
        <span class="text-muted">{{ $remaining }} {{ __('Zeilen erkannt') }}</span>
    </div>

    <x-card as="form" method="POST" action="{{ route('schedule.import.confirm') }}">
        @csrf
        <h2 class="card-title mb-4 text-base">{{ __('Welche Spalte enthält was?') }}</h2>

        <div class="mb-6 overflow-x-auto rounded-box border border-base-300">
            <x-table>
                <x-slot:head>
                    <tr>
                        @foreach ($headers as $i => $h)
                            <th class="bg-base-200 align-top">
                                <span class="mb-1 block font-mono text-xs">{{ $h }}</span>
                                <select name="map[{{ $i }}]" class="select select-bordered select-xs w-full" aria-label="{{ __('Spalte') }} {{ $h }}">
                                    <option value="skip">{{ __('— nicht importieren —') }}</option>
                                    @foreach ($fields as $key => $label)
                                        <option value="{{ $key }}" @selected($guesses[$i] === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </th>
                        @endforeach
                    </tr>
                </x-slot:head>
                @foreach (array_slice($preview, 0, 5) as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="max-w-48 truncate text-xs" title="{{ $cell }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </x-table>
            @if ($remaining > 5)
                <p class="px-4 py-2 text-xs text-muted">… {{ $remaining - 5 }} {{ __('weitere Zeilen') }}</p>
            @endif
        </div>

        <div class="card-actions justify-between">
            <x-icon-btn icon="close" size="sm" :href="route('schedule.import')" show-label>{{ __('Abbrechen') }}</x-icon-btn>
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Import durchführen') }}</x-icon-btn>
        </div>
    </x-card>

</div>
@endsection
