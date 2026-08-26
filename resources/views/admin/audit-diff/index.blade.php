{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Stammdaten-Versionsvergleich (MVP-528): Timeline aus audit_logs mit
  A/B-Auswahl und Feld-Diff.
--}}

@extends('layouts.app')
@section('title', __('Änderungsverlauf & Versionsvergleich'))
@section('nav-title', __('Änderungsverlauf'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Zwei Änderungsstände eines Datensatzes vergleichen — aus der revisionssicheren Audit-Kette, nur Anzeige.')" />
    </x-slot:toolbar>

    <x-filter-bar :action="route('admin.audit-diff.index')" :reset="route('admin.audit-diff.index')">
        <x-filter-field :label="__('Typ')" for="ad-type">
            <select id="ad-type" name="type" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('— wählen —') }}</option>
                @foreach ($types as $key => $meta)
                    <option value="{{ $key }}" @selected($typeKey === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </x-filter-field>
        @if ($typeKey !== '' && $records->isNotEmpty())
            <x-filter-field :label="__('Datensatz')" for="ad-record">
                <select id="ad-record" name="record" class="select select-sm select-bordered w-64" data-autosubmit>
                    <option value="">{{ __('— wählen —') }}</option>
                    @foreach ($records as $candidate)
                        <option value="{{ \App\Support\Sqid::encode($types[$typeKey]['class'], (int) $candidate->id) }}"
                                @selected($record !== null && (int) $candidate->id === (int) $record->id)>
                            {{ $candidate->name }}
                        </option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
    </x-filter-bar>

    @if ($record !== null && $logs !== null)
        @if ($diff !== null)
            <x-card>
                <h3 class="font-semibold mb-2">{{ __('Unterschiede zwischen Stand A und Stand B') }}</h3>
                @if (empty($diff))
                    <p class="text-muted">{{ __('Keine Feldänderungen zwischen den gewählten Ständen.') }}</p>
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('Feld') }}</x-table.th>
                                <x-table.th>{{ __('Stand A') }}</x-table.th>
                                <x-table.th>{{ __('Stand B') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($diff as $row)
                            <tr>
                                <td class="font-mono text-sm">{{ $row['field'] }}</td>
                                <td class="text-error/80 break-all">{{ $row['before'] }}</td>
                                <td class="text-success break-all">{{ $row['after'] }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>
        @endif

        <x-card>
            <h3 class="font-semibold mb-2">{{ __('Änderungs-Timeline') }} — {{ $record->name }}</h3>
            @if ($logs->isEmpty())
                <p class="text-muted">{{ __('Keine Audit-Einträge zu diesem Datensatz.') }}</p>
            @else
                <form method="GET" action="{{ route('admin.audit-diff.index') }}">
                    <input type="hidden" name="type" value="{{ $typeKey }}">
                    <input type="hidden" name="record" value="{{ $recordSqid }}">
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>A</x-table.th>
                                <x-table.th>B</x-table.th>
                                <x-table.th>{{ __('Zeitpunkt') }}</x-table.th>
                                <x-table.th>{{ __('Ereignis') }}</x-table.th>
                                <x-table.th>{{ __('Benutzer') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($logs as $log)
                            <tr>
                                <td><input type="radio" name="a" value="{{ $log->id }}" class="radio radio-xs"
                                           @checked($selectedA === (int) $log->id)></td>
                                <td><input type="radio" name="b" value="{{ $log->id }}" class="radio radio-xs"
                                           @checked($selectedB === (int) $log->id)></td>
                                <td class="tabular-nums">{{ $log->created_at?->fdatetime() }}</td>
                                <td class="font-mono text-sm">{{ $log->event }}</td>
                                <td>{{ $log->user?->name ?? __('System') }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                    <div class="mt-2 flex justify-end">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Stände vergleichen') }}</button>
                    </div>
                </form>
            @endif
        </x-card>
    @endif
</x-page-shell>
@endsection
