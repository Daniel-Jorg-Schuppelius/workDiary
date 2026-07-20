{{--
  Created on   : Sun Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('gobd.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('gobd.title'))

@section('content')
<x-index-page :subtitle="__('gobd.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="fact_check" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('finance.gobd.check', ['from' => $from, 'to' => $to, 'sections' => $selected])"
                    show-label>{{ __('gobd.preflight.check') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Vorprüfungs-Ergebnis für den im Dialog gewählten Zeitraum + Export (POST) --}}
    @if ($preflight !== null)
        <x-card :title="__('gobd.preflight.title')" icon="fact_check">
            <x-slot:actions>
                <span class="inline-flex items-center gap-1.5 rounded-box bg-base-200 px-3 py-1 text-sm font-medium text-base-content/80">
                    <x-icon name="date_range" class="text-base-content/60" />
                    <span class="tabular-nums">{{ \Illuminate\Support\Carbon::parse($from)->format('d.m.Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('d.m.Y') }}</span>
                </span>
            </x-slot:actions>

            {{-- Datensatz-Zähler je Datenbereich als Chips --}}
            <div class="flex flex-wrap gap-2">
                @foreach ($preflight['counts'] as $key => $count)
                    <div @class([
                        'flex items-baseline gap-2 rounded-box border px-3 py-2',
                        'border-base-300 bg-base-100' => $count > 0,
                        'border-dashed border-base-300 bg-base-200/40 opacity-70' => $count === 0,
                    ])>
                        <span class="text-xs text-base-content/60">{{ __('gobd.section.' . $key) }}</span>
                        <strong class="font-['Space_Grotesk'] tabular-nums">{{ number_format((int) $count, 0, ',', '.') }}</strong>
                    </div>
                @endforeach
            </div>

            @if (! empty($preflight['warnings']))
                <div class="alert alert-warning mt-4" role="alert">
                    <x-icon name="warning" aria-hidden="true" />
                    <div>
                        <p class="font-medium">{{ __('gobd.preflight.warnings') }}</p>
                        <ul class="ms-5 list-disc text-sm">
                            @foreach ($preflight['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('finance.gobd.export') }}"
                  class="mt-4 flex flex-wrap items-end justify-between gap-3 border-t border-base-300 pt-4">
                @csrf
                <input type="hidden" name="from" value="{{ $from }}">
                <input type="hidden" name="to" value="{{ $to }}">
                @foreach ($selected as $key)
                    <input type="hidden" name="sections[]" value="{{ $key }}">
                @endforeach
                <fieldset class="fieldset max-w-xs">
                    <legend class="fieldset-label">{{ __('gobd.encoding') }}</legend>
                    <select name="encoding" class="select select-bordered select-sm" aria-label="{{ __('gobd.encoding') }}">
                        @foreach ($encodings as $enc)
                            <option value="{{ $enc }}">{{ strtoupper($enc) }}{{ $enc === 'cp1252' ? ' (ANSI)' : '' }}</option>
                        @endforeach
                    </select>
                </fieldset>
                <x-icon-btn icon="download" tone="primary" size="sm" type="submit"
                            show-label>{{ __('gobd.export') }}</x-icon-btn>
            </form>
        </x-card>
    @endif

    {{-- Revisionssicherer Nachweis: bisherige Exporte --}}
    <x-card padding="p-0" :title="__('gobd.recent.title')">
        <x-table :caption="__('gobd.recent.title')" bare>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('gobd.period') }}</x-table.th>
                    <x-table.th align="right">{{ __('gobd.recent.records') }}</x-table.th>
                    <x-table.th>{{ __('gobd.recent.package_hash') }}</x-table.th>
                    <x-table.th>{{ __('gobd.recent.created') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($recent as $export)
                <tr>
                    <td class="text-sm tabular-nums">{{ $export->period_from->fdate() }} – {{ $export->period_to->fdate() }}</td>
                    <td class="text-right tabular-nums">{{ $export->record_count }}</td>
                    <td class="font-mono text-xs opacity-70">{{ \Illuminate\Support\Str::limit($export->package_sha256, 16) }}</td>
                    <td class="text-sm">{{ $export->created_at?->fdatetime() }}{{ $export->creator ? ' · ' . $export->creator->name : '' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4"
                               icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>'
                               :title="__('gobd.recent.none')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
