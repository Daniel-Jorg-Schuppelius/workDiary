{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import_preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorschau des Schlüsselimports (MVP-1024): Positionen, Vollständigkeit und
  Fehler mit Zeile und Feld — nie ein Schlüsselwert. Übernommen wird erst
  nach Bestätigung und nur eine insgesamt fehlerfreie Datei.
--}}

@extends('layouts.app')

@section('title', __('resale.license.import.title', ['reference' => $batch->reference]))
@section('nav-title', __('resale.title.menu'))

@section('content')
    <x-index-page :subtitle="$batch->product->name . ' · ' . $batch->reference" :back="route('finance.resale.licenses.batches.show', $batch)" :back-label="$batch->reference">
        <x-slot:actions>
            @if ($preview['token'] !== null)
                <x-action-form :action="route('finance.resale.licenses.batches.import.store', $batch)">
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">
                    <x-button type="submit" tone="primary" size="sm" icon="check" placement="bar">{{ trans_choice('resale.license.import.confirm', $preview['changes'], ['count' => $preview['changes']]) }}</x-button>
                </x-action-form>
            @endif
        </x-slot:actions>

        @if ($preview['errors'] !== [])
            <div class="alert alert-error mb-4 text-sm" role="alert">
                <x-icon name="error" />
                <span>{{ __('resale.license.import.blocked', ['count' => count($preview['errors'])]) }}</span>
            </div>
            <x-card :title="__('resale.license.import.errors_title')" icon="error" padding="p-0" class="mb-4">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('resale.license.import.line') }}</th>
                            <th>{{ __('resale.license.import.field') }}</th>
                            <th>{{ __('resale.license.import.message') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($preview['errors'] as $error)
                        <tr>
                            <td class="tabular-nums">{{ $error['line'] }}</td>
                            <td class="text-sm">{{ $error['field'] }}</td>
                            <td class="text-sm">{{ $error['message'] }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @elseif ($preview['changes'] === 0)
            <div class="alert alert-info mb-4 text-sm" role="status">
                <x-icon name="info" />
                <span>{{ __('resale.license.import.nothing') }}</span>
            </div>
        @endif

        <x-card :title="__('resale.license.import.rows_title')" icon="table_rows" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('resale.license.import.line') }}</th>
                        <th>{{ __('resale.license.field.position') }}</th>
                        <th class="text-right">{{ __('resale.license.import.added') }}</th>
                        <th>{{ __('resale.license.field.status') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($preview['rows'] as $row)
                    <tr>
                        <td class="tabular-nums">{{ $row['line'] }}</td>
                        <td class="tabular-nums">#{{ $row['position'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['added'] }}</td>
                        <td>
                            <x-status-badge size="xs" :tone="$row['complete'] ? 'success' : 'warning'"
                                            :label="$row['complete'] ? __('resale.license.import.complete') : __('resale.license.status.incomplete')" />
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" icon="table_rows" :title="__('resale.license.import.no_rows')" compact />
                @endforelse
            </x-table>
        </x-card>
    </x-index-page>
@endsection
