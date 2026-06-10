@extends('layouts.app')
@section('title', __('Lückenanalyse'))
@section('nav-title', __('Compliance- & Vertragslücken'))
@php
    $statusMeta = [
        'missing' => ['Fehlt', 'badge-error'],
        'expiring' => ['Läuft ab', 'badge-warning'],
        'required' => ['Erforderlich', 'badge-warning'],
        'in_review' => ['In Prüfung', 'badge-info'],
        'deviation_accepted' => ['Abweichung akzeptiert', 'badge-ghost'],
        'present' => ['Vorhanden', 'badge-success'],
        'not_applicable' => ['Nicht anwendbar', 'badge-ghost'],
    ];
    $statusTone = [
        'missing' => 'error',
        'expiring' => 'warning',
        'required' => 'warning',
        'in_review' => 'info',
        'deviation_accepted' => 'ghost',
        'present' => 'success',
        'not_applicable' => 'ghost',
    ];
    $statusOptions = ['present' => __('Vorhanden'), 'in_review' => __('In Prüfung'), 'not_applicable' => __('Nicht anwendbar'), 'deviation_accepted' => __('Abweichung akzeptiert'), 'missing' => __('Wieder offen')];
@endphp
@section('content')
    <x-index-page :subtitle="__('Lücken in Verträgen und Compliance-Anforderungen aufdecken und bewerten.')">
        <x-slot:actions>
            @can('manage', \App\Models\Privacy\ComplianceFinding::class)
                <form method="post" action="{{ route('dataprotection.compliance.run') }}">@csrf
                    <x-icon-btn icon="rule" tone="primary" size="sm" type="submit" show-label>{{ __('Analyse jetzt ausführen') }}</x-icon-btn>
                </form>
            @endcan
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        {{-- Ampel --}}
        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($statusMeta as $s => [$label, $cls])
                    @if (($counts[$s] ?? 0) > 0)
                        <x-status-badge :tone="$statusTone[$s] ?? 'ghost'" size="lg" class="gap-2">{{ __($label) }} <span class="font-bold">{{ $counts[$s] }}</span></x-status-badge>
                    @endif
                @endforeach
                @if ($findings->isEmpty())
                    <span class="text-sm text-base-content/60">{{ __('Noch keine Analyse ausgeführt.') }}</span>
                @endif
            </div>
        </x-card>

        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Anforderung') }}</x-table.th>
                        <x-table.th>{{ __('Status') }}</x-table.th>
                        <x-table.th>{{ __('Auslöser') }}</x-table.th>
                        <x-table.th>{{ __('Bezug') }}</x-table.th>
                        @can('manage', \App\Models\Privacy\ComplianceFinding::class)<x-table.th>{{ __('Entscheidung') }}</x-table.th>@endcan
                    </tr>
                </x-slot:head>
                @forelse ($findings as $f)
                    <tr>
                        <td>{{ $f->label }}</td>
                        <td><x-status-badge :tone="$statusTone[$f->status] ?? 'ghost'" size="sm">{{ __($statusMeta[$f->status][0] ?? $f->status) }}</x-status-badge></td>
                        <td class="text-sm">{{ $f->trigger ?? '—' }}</td>
                        <td class="text-sm">
                            @if ($f->activity)<a class="link" href="{{ route('dataprotection.activities.show', $f->activity) }}">{{ $f->activity->name }}</a>
                            @elseif ($f->agreement)<a class="link" href="{{ route('dataprotection.agreements.show', $f->agreement) }}">{{ $f->agreement->title }}</a>
                            @elseif ($f->processor)<a class="link" href="{{ route('dataprotection.processors.show', $f->processor) }}">{{ $f->processor->name }}</a>
                            @else — @endif
                        </td>
                        @can('manage', \App\Models\Privacy\ComplianceFinding::class)
                            <td>
                                <form method="post" action="{{ route('dataprotection.compliance.update', $f) }}" class="flex flex-wrap items-center gap-1">
                                    @csrf @method('PUT')
                                    <select name="status" class="select select-xs select-bordered">
                                        @foreach ($statusOptions as $v => $l)<option value="{{ $v }}" @selected($f->status === $v)>{{ $l }}</option>@endforeach
                                    </select>
                                    <input name="justification" class="input input-xs input-bordered" placeholder="{{ __('Begründung') }}" value="{{ $f->justification }}">
                                    <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('OK') }}</x-icon-btn>
                                </form>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('Keine Befunde – Analyse ausführen.')" />
                @endforelse
            </x-table>
        </x-card>
    </x-index-page>
@endsection
