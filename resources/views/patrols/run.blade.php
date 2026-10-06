{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : run.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Mobile Lauf-Sicht eines Rundgangs: Scan-Feld oben (Kamera-Scanner tippen
  als Tastatur), darunter der Fortschritt je Kontrollpunkt.
--}}

@extends('layouts.app')

@section('title', __('Rundgang: :name', ['name' => $run->route?->name]))
@section('nav-title', __('Rundgang'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :back="route('patrols.show', $run->route)" :back-label="__('Zur Route')">
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium">{{ $run->route?->name }}</span>
                <span class="text-sm text-muted">{{ __('gestartet :time', ['time' => $run->started_at->orgTz()->format('H:i')]) }}</span>
                <x-status-badge class="shrink-0" :tone="$run->status === \App\Enums\Patrol\PatrolRunStatus::Aborted ? 'warning' : 'ghost'">{{ $run->status->label() }}</x-status-badge>
            </div>
            <x-slot:actions>
                @if ($run->status !== \App\Enums\Patrol\PatrolRunStatus::Running)
                    <x-icon-btn icon="picture_as_pdf" size="sm"
                                :href="route('patrols.runs.show', [$run, 'export' => 'pdf'])"
                                show-label>{{ __('Bericht (PDF)') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($run->status === \App\Enums\Patrol\PatrolRunStatus::Aborted)
        <div class="alert alert-warning text-sm" role="status">
            <x-icon name="cancel" />
            <div>
                <p class="font-semibold">{{ __('Abgebrochen von :name am :date', ['name' => $run->abortedBy?->name ?? '—', 'date' => $run->finished_at?->fdatetime() ?? '—']) }}</p>
                <p>{{ $run->abort_reason }}</p>
            </div>
        </div>
    @endif

    @if ($run->status === \App\Enums\Patrol\PatrolRunStatus::Running)
        <x-card>
            <form method="POST" action="{{ route('patrols.runs.scan', $run) }}" class="flex gap-2">
                @csrf
                <input type="text" name="token" required maxlength="64" autofocus autocomplete="off"
                       class="input input-bordered w-full font-mono"
                       placeholder="{{ __('Token scannen oder eingeben') }}" aria-label="{{ __('Token') }}">
                <x-button type="submit" size="md" class="shrink-0">{{ __('Bestätigen') }}</x-button>
            </form>
        </x-card>
    @endif

    <x-card :title="__('Kontrollpunkte')">
        <ol class="space-y-2 text-sm">
            @foreach ($run->route?->checkpoints ?? [] as $checkpoint)
                @php($scan = $scans->get($checkpoint->id))
                <li class="flex items-center justify-between gap-3 rounded-lg border p-3
                           @if ($scan && $scan->in_window) border-success/40 bg-success/5
                           @elseif ($scan) border-warning/40 bg-warning/5
                           @else border-base-300 @endif">
                    <div class="min-w-0">
                        <span class="font-medium">{{ $checkpoint->position }}. {{ $checkpoint->label }}</span>
                        <span class="block text-xs text-muted">
                            {{ __('Soll: +:offset min ± :tol', ['offset' => $checkpoint->expected_offset_minutes, 'tol' => $checkpoint->tolerance_minutes]) }}
                        </span>
                    </div>
                    <div class="shrink-0 text-right text-xs">
                        @if ($scan)
                            <span class="font-medium">{{ $scan->scanned_at->orgTz()->format('H:i') }}</span>
                            @unless ($scan->in_window)
                                {{-- Abweichung wird gezeigt, nie geglättet. --}}
                                <span class="block text-warning">{{ $scan->delta_minutes > 0 ? '+' : '' }}{{ $scan->delta_minutes }} min</span>
                            @endunless
                        @else
                            <span class="text-muted">{{ $run->status === \App\Enums\Patrol\PatrolRunStatus::Aborted ? __('offen bei Abbruch') : __('ausstehend') }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </x-card>

    @if ($run->status === \App\Enums\Patrol\PatrolRunStatus::Running)
        <x-card :title="__('Abschließen')">
            @if ($missed->isNotEmpty())
                <p class="mb-2 text-sm text-warning">{{ __(':count Kontrollpunkte sind noch offen — der Abschluss braucht dann eine Begründung.', ['count' => $missed->count()]) }}</p>
            @endif
            <form method="POST" action="{{ route('patrols.runs.complete', $run) }}" class="space-y-3">
                @csrf
                <x-input-field name="deviation_note" :label="__('Begründung bei Abweichungen')" />
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-button type="submit">{{ __('Rundgang abschließen') }}</x-button>
                    <x-button tone="ghost" icon="cancel" data-entry-modal-trigger
                              :href="route('patrols.runs.abort.create', $run)">{{ __('Rundgang abbrechen') }}</x-button>
                </div>
            </form>
        </x-card>
    @elseif ($run->deviation_note)
        <x-card :title="__('Abweichungen')">
            <p class="text-sm">{{ $run->deviation_note }}</p>
        </x-card>
    @endif
</x-page-shell>
@endsection
