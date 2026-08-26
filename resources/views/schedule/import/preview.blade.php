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
<div class="w-full px-4 py-8">

    <div class="mb-4 flex items-center gap-3">
        <x-button :href="route('schedule.import')" tone="ghost">← {{ __('Zurück') }}</x-button>
    </div>

    {{-- ── File info ── --}}
    <div class="mb-4 flex items-center gap-3 rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm">
        <span class="text-muted">{{ __('Datei:') }}</span>
        <span class="font-medium">{{ basename($filePath) }}</span>
        <span class="text-muted">·</span>
        <span class="text-muted">{{ count($rows) }} {{ __('Zeilen erkannt') }}</span>
    </div>

    {{-- ── Preview table ── --}}
    <div class="mb-6 overflow-x-auto rounded-box border border-base-300">
        <x-table>
            <x-slot:head>
                <tr>
                    @foreach ($headers as $h)
                        <th class="bg-base-200 font-mono text-xs">{{ $h }}</th>
                    @endforeach
                </tr>
            </x-slot:head>
                @foreach (array_slice($rows, 0, 5) as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="max-w-48 truncate text-xs" title="{{ $cell }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
        </x-table>
        @if (count($rows) > 5)
            <p class="px-4 py-2 text-xs text-muted">… {{ count($rows) - 5 }} {{ __('weitere Zeilen') }}</p>
        @endif
    </div>

    {{-- ── Column mapping form ── --}}
    <form method="POST" action="{{ route('schedule.import.confirm') }}" class="card bg-base-100 shadow-sm border border-base-300">
        @csrf
        <input type="hidden" name="file_path" value="{{ $filePath }}">
        <input type="hidden" name="default_status" value="{{ $defaultStatus }}">

        <div class="card-body">
            <h2 class="card-title text-base mb-4">{{ __('Welche Spalte enthält was?') }}</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                {{-- Date --}}
                <div class="form-control">
                    <label class="label"><span class="label-text font-medium">{{ __('Datum') }} *</span></label>
                    <select name="col_date" class="select select-bordered select-sm" required>
                        <option value="">— {{ __('Spalte wählen') }} —</option>
                        @foreach ($headers as $i => $h)
                            <option value="{{ $i }}" @selected(str_contains(strtolower($h), 'datum') || str_contains(strtolower($h), 'date'))>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- User --}}
                <div class="form-control">
                    <label class="label"><span class="label-text font-medium">{{ __('Mitarbeiter') }} *</span></label>
                    <select name="col_user" class="select select-bordered select-sm" required>
                        <option value="">— {{ __('Spalte wählen') }} —</option>
                        @foreach ($headers as $i => $h)
                            <option value="{{ $i }}" @selected(str_contains(strtolower($h), 'name') || str_contains(strtolower($h), 'user') || str_contains(strtolower($h), 'mitarbeiter'))>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Shift type --}}
                <div class="form-control">
                    <label class="label"><span class="label-text">{{ __('Schichttyp') }}</span></label>
                    <select name="col_shift_type" class="select select-bordered select-sm">
                        <option value="">— {{ __('nicht vorhanden') }} —</option>
                        @foreach ($headers as $i => $h)
                            <option value="{{ $i }}" @selected(str_contains(strtolower($h), 'schicht') || str_contains(strtolower($h), 'typ') || str_contains(strtolower($h), 'type'))>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Start time --}}
                <div class="form-control">
                    <label class="label"><span class="label-text">{{ __('Beginn (Von)') }}</span></label>
                    <select name="col_start" class="select select-bordered select-sm">
                        <option value="">— {{ __('nicht vorhanden') }} —</option>
                        @foreach ($headers as $i => $h)
                            <option value="{{ $i }}" @selected(str_contains(strtolower($h), 'von') || str_contains(strtolower($h), 'start') || str_contains(strtolower($h), 'beginn'))>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- End time --}}
                <div class="form-control">
                    <label class="label"><span class="label-text">{{ __('Ende (Bis)') }}</span></label>
                    <select name="col_end" class="select select-bordered select-sm">
                        <option value="">— {{ __('nicht vorhanden') }} —</option>
                        @foreach ($headers as $i => $h)
                            <option value="{{ $i }}" @selected(str_contains(strtolower($h), 'bis') || str_contains(strtolower($h), 'end') || str_contains(strtolower($h), 'ende'))>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Note --}}
                <div class="form-control">
                    <label class="label"><span class="label-text">{{ __('Notiz / Bemerkung') }}</span></label>
                    <select name="col_note" class="select select-bordered select-sm">
                        <option value="">— {{ __('nicht vorhanden') }} —</option>
                        @foreach ($headers as $i => $h)
                            <option value="{{ $i }}" @selected(str_contains(strtolower($h), 'notiz') || str_contains(strtolower($h), 'note') || str_contains(strtolower($h), 'bemerkung'))>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>

            </div>

            <div class="card-actions mt-6 justify-between">
                <x-icon-btn icon="close" size="sm" :href="route('schedule.import')" show-label>{{ __('Abbrechen') }}</x-icon-btn>
                <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Import durchführen') }}</x-icon-btn>
            </div>
        </div>
    </form>

</div>
@endsection
