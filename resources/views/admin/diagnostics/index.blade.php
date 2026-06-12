@extends('layouts.app')

@section('title', __('Diagnose') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Diagnose'))

@php
    /** @var \App\Services\Diagnostics\DiagnosticsReport $report */
    $statusToBadge = [
        'ok' => 'badge-success',
        'warn' => 'badge-warning',
        'critical' => 'badge-error',
        'unknown' => 'badge-ghost',
    ];
    $statusToLabel = [
        'ok' => __('Ok'),
        'warn' => __('Warnung'),
        'critical' => __('Kritisch'),
        'unknown' => __('Unbekannt'),
    ];
    $sectionTitles = [
        'version' => __('Version'),
        'license' => __('Lizenz'),
        'queue' => __('Queue'),
        'scheduler' => __('Scheduler'),
        'mail' => __('Mail'),
        'storage' => __('Storage'),
        'backup' => __('Backupstatus'),
    ];
    $sectionIcons = [
        'version' => 'info',
        'license' => 'verified',
        'queue' => 'queue',
        'scheduler' => 'schedule',
        'mail' => 'mail',
        'storage' => 'sd_storage',
        'backup' => 'backup',
    ];
@endphp

@section('content')
<x-index-page
    :subtitle="__('Erzeugt: :at', ['at' => $report->generatedAt->translatedFormat('d.m.Y H:i:s')])"
    :badge="$statusToLabel[$report->overallStatus()->value] ?? null"
    :badge-tone="match ($report->overallStatus()->value) {
        'ok' => 'success', 'warn' => 'warning', 'critical' => 'error', default => 'ghost',
    }"
>
    <x-slot:actions>
        <a href="{{ route('admin.diagnostics.json') }}" class="btn btn-sm btn-ghost">{{ __('JSON') }}</a>
        @can(\App\Enums\User\Permission::PlatformDiagnosticsRunCheck->value)
            <form method="POST" action="{{ route('admin.diagnostics.test-mail') }}"
                  onsubmit="event.preventDefault(); fetch(this.action, {method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json'}, credentials:'same-origin'}).then(r=>r.json()).then(p=>alert(p.ok ? 'Mail abgesetzt.' : 'Fehler: ' + (p.error || '?'))).catch(()=>alert('Fehler beim Senden.'));">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline">{{ __('Test-Mail senden') }}</button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($report->sections as $section)
            @php
                $sv = $section->status->value;
                $badge = $statusToBadge[$sv] ?? 'badge-ghost';
                $label = $statusToLabel[$sv] ?? $sv;
                $title = $sectionTitles[$section->code] ?? $section->code;
                $icon = $sectionIcons[$section->code] ?? 'help';
            @endphp
            <article class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-3">
                    <header class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <x-icon :name="$icon" />
                            <h2 class="font-['Space_Grotesk'] text-base font-semibold text-base-content truncate">{{ $title }}</h2>
                        </div>
                        <span class="badge badge-outline {{ $badge }}">{{ $label }}</span>
                    </header>

                    @if (count($section->metrics) > 0)
                        <dl class="grid grid-cols-1 gap-1 text-sm">
                            @foreach ($section->metrics as $key => $value)
                                <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1 last:border-0">
                                    <dt class="text-base-content/60">{{ $key }}</dt>
                                    <dd class="text-right font-mono text-xs text-base-content/80 truncate">
                                        @if ($value === null)
                                            <span class="italic text-base-content/40">—</span>
                                        @elseif (is_bool($value))
                                            {{ $value ? 'true' : 'false' }}
                                        @else
                                            {{ (string) $value }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if (count($section->messages) > 0)
                        <ul class="space-y-1 text-xs text-base-content/70">
                            @foreach ($section->messages as $msg)
                                <li class="flex items-start gap-2">
                                    <x-icon name="info" />
                                    <span>{{ $msg }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($section->checkedAt)
                        <footer class="border-t border-base-200/70 pt-2 text-xs text-base-content/50">
                            {{ __('Letzter Check: :at', ['at' => $section->checkedAt->translatedFormat('H:i:s')]) }}
                        </footer>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</x-index-page>
@endsection
