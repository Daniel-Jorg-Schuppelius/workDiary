@extends('layouts.app')
@section('title', __('gobd.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('gobd.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('gobd.title')" :subtitle="__('gobd.subtitle')" />
    </x-slot:toolbar>

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

            <button type="submit" class="btn btn-sm">
                <span class="material-symbols-outlined text-base" aria-hidden="true">fact_check</span>
                {{ __('gobd.preflight.check') }}
            </button>
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
                <label class="form-control mb-3 max-w-xs">
                    <span class="label-text">{{ __('gobd.encoding') }}</span>
                    <select name="encoding" class="select select-bordered select-sm">
                        @foreach ($encodings as $enc)
                            <option value="{{ $enc }}">{{ strtoupper($enc) }}{{ $enc === 'cp1252' ? ' (ANSI)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">download</span>
                    {{ __('gobd.export') }}
                </button>
            </form>
        </x-card>
    @endif

    {{-- Revisionssicherer Nachweis: bisherige Exporte --}}
    <x-card padding="p-0">
        <div class="px-4 pt-4">
            <h2 class="font-semibold">{{ __('gobd.recent.title') }}</h2>
        </div>
        @if ($recent->isEmpty())
            <p class="text-sm opacity-60 p-4">{{ __('gobd.recent.none') }}</p>
        @else
            <x-table>
                <x-slot:head>
                    <th>{{ __('gobd.period') }}</th>
                    <th class="text-right">{{ __('gobd.recent.records') }}</th>
                    <th>{{ __('gobd.recent.package_hash') }}</th>
                    <th>{{ __('gobd.recent.created') }}</th>
                </x-slot:head>
                @foreach ($recent as $export)
                    <tr>
                        <td class="text-sm tabular-nums">{{ $export->period_from->format('d.m.Y') }} – {{ $export->period_to->format('d.m.Y') }}</td>
                        <td class="text-right tabular-nums">{{ $export->record_count }}</td>
                        <td class="font-mono text-xs opacity-70">{{ \Illuminate\Support\Str::limit($export->package_sha256, 16) }}</td>
                        <td class="text-sm">{{ $export->created_at?->format('d.m.Y H:i') }}{{ $export->creator ? ' · ' . $export->creator->name : '' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
