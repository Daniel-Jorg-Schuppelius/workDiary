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
    {{-- Auswahl Zeitraum + Bereiche → Preflight (GET) --}}
    <x-card>
        <form method="GET" action="{{ route('finance.gobd.index') }}" class="space-y-4">
            <x-date-range from-name="from" to-name="to" :from="$from" :to="$to"
                          :label="__('gobd.period')" layout="split" />

            <fieldset class="fieldset">
                <legend class="fieldset-label">{{ __('gobd.sections') }}</legend>
                <div class="flex flex-wrap gap-4">
                    @foreach ($sections as $key)
                        <label class="label cursor-pointer gap-2">
                            <input type="checkbox" name="sections[]" value="{{ $key }}"
                                   class="checkbox checkbox-sm" @checked(in_array($key, $selected, true))>
                            <span class="label-text">{{ __('gobd.section.' . $key) }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <x-icon-btn icon="fact_check" tone="outline" size="sm" type="submit"
                        show-label>{{ __('gobd.preflight.check') }}</x-icon-btn>
        </form>
    </x-card>

    {{-- Preflight-Ergebnis + Export (POST) --}}
    @if ($preflight !== null)
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('gobd.preflight.title') }}</h2>

            <div class="flex flex-wrap gap-x-8 gap-y-1 text-sm mb-3">
                @foreach ($preflight['counts'] as $key => $count)
                    <div>
                        <span class="opacity-60">{{ __('gobd.section.' . $key) }}:</span>
                        <strong class="tabular-nums">{{ __('gobd.preflight.records', ['count' => $count]) }}</strong>
                    </div>
                @endforeach
            </div>

            @if (! empty($preflight['warnings']))
                <div class="alert alert-warning mb-3" role="alert">
                    <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                    <div>
                        <p class="font-medium">{{ __('gobd.preflight.warnings') }}</p>
                        <ul class="list-disc ms-5 text-sm">
                            @foreach ($preflight['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('finance.gobd.export') }}">
                @csrf
                <input type="hidden" name="from" value="{{ $from }}">
                <input type="hidden" name="to" value="{{ $to }}">
                @foreach ($selected as $key)
                    <input type="hidden" name="sections[]" value="{{ $key }}">
                @endforeach
                <fieldset class="fieldset mb-3 max-w-xs">
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
    <x-card padding="p-0">
        <div class="px-4 pt-4">
            <h2 class="font-semibold">{{ __('gobd.recent.title') }}</h2>
        </div>
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
                    <td class="text-sm tabular-nums">{{ $export->period_from->format('d.m.Y') }} – {{ $export->period_to->format('d.m.Y') }}</td>
                    <td class="text-right tabular-nums">{{ $export->record_count }}</td>
                    <td class="font-mono text-xs opacity-70">{{ \Illuminate\Support\Str::limit($export->package_sha256, 16) }}</td>
                    <td class="text-sm">{{ $export->created_at?->format('d.m.Y H:i') }}{{ $export->creator ? ' · ' . $export->creator->name : '' }}</td>
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
