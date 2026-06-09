@extends('layouts.app')
@section('title', __('Lückenanalyse'))
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
    $statusOptions = ['present' => __('Vorhanden'), 'in_review' => __('In Prüfung'), 'not_applicable' => __('Nicht anwendbar'), 'deviation_accepted' => __('Abweichung akzeptiert'), 'missing' => __('Wieder offen')];
@endphp
@section('content')
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('Compliance- & Vertragslücken') }}</h1>
            @can('manage', \App\Models\Privacy\ComplianceFinding::class)
                <form method="post" action="{{ route('dataprotection.compliance.run') }}">@csrf <button class="btn btn-primary btn-sm">{{ __('Analyse jetzt ausführen') }}</button></form>
            @endcan
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        {{-- Ampel --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($statusMeta as $s => [$label, $cls])
                @if (($counts[$s] ?? 0) > 0)
                    <span class="badge {{ $cls }} badge-lg gap-2">{{ __($label) }} <span class="font-bold">{{ $counts[$s] }}</span></span>
                @endif
            @endforeach
            @if ($findings->isEmpty())
                <span class="text-sm text-base-content/60">{{ __('Noch keine Analyse ausgeführt.') }}</span>
            @endif
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Anforderung') }}</th><th>{{ __('Status') }}</th><th>{{ __('Auslöser') }}</th><th>{{ __('Bezug') }}</th>@can('manage', \App\Models\Privacy\ComplianceFinding::class)<th>{{ __('Entscheidung') }}</th>@endcan</tr></thead>
                <tbody>
                    @forelse ($findings as $f)
                        <tr>
                            <td>{{ $f->label }}</td>
                            <td><span class="badge {{ $statusMeta[$f->status][1] ?? 'badge-ghost' }}">{{ __($statusMeta[$f->status][0] ?? $f->status) }}</span></td>
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
                                        <button class="btn btn-xs">{{ __('OK') }}</button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-base-content/60 py-6">{{ __('Keine Befunde – Analyse ausführen.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
