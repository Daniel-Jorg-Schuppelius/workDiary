{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lernansicht für Externe ohne Benutzerkonto (Feature 149, MVP-742).
  Bewusst reduziert: kein Navigationsrahmen, keine Querverweise in die
  Anwendung — nur der Kurs, für den der Zugang gilt.
--}}
@extends('layouts.guest')
@section('title', $course?->title ?? __('learning.section'))
@section('content')
<div class="w-full space-y-4">
    <div>
        <h1 class="text-xl font-semibold">{{ $course?->title }}</h1>
        @if ($enrollment->externalParticipant)
            <p class="mt-1 text-sm text-base-content/70">{{ $enrollment->externalParticipant->name }}</p>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success text-sm" role="status">
            <x-icon name="check_circle" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($enrollment->status === \App\Enums\Learning\LearningEnrollmentStatus::Completed)
        <div role="status" class="alert alert-success text-sm">
            <x-icon name="verified" />
            <span>{{ __('learning.external.completed') }}</span>
        </div>
    @endif

    @if ($course?->objectives)
        <x-card>
            <h2 class="mb-2 text-sm font-semibold">{{ __('learning.field.objectives') }}</h2>
            <p class="text-sm text-base-content/80">{{ $course->objectives }}</p>
        </x-card>
    @endif

    @foreach ($course?->units ?? [] as $unit)
        @php
            $isDone = in_array($unit->id, $completedUnitIds, true);
            $isLocked = ! $isDone && ! $unit->isReleasedFor($enrollment, null, $completedUnitIds);
        @endphp
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="{{ $isDone ? 'check_circle' : 'radio_button_unchecked' }}"
                            class="{{ $isDone ? 'text-success' : 'text-muted' }}" />
                    {{ $unit->title }}
                </h2>
                @if ($isLocked)
                    <x-status-badge tone="neutral" size="sm" outline>{{ $unit->lockLabelFor($enrollment, $completedUnitIds) }}</x-status-badge>
                @endif
                @if (! $isDone && ! $isLocked && in_array($unit->kind, [\App\Enums\Learning\LearningUnitKind::Procedure, \App\Enums\Learning\LearningUnitKind::Survey, \App\Enums\Learning\LearningUnitKind::External], true) && $unit->reportsOwnResult())
                    <p class="w-full text-xs text-muted" role="status">{{ __('learning.external.unit_unavailable') }}</p>
                @endif
                {{-- Einheiten mit eigener Ergebnisquelle schließt nur diese Quelle ab. --}}
                @unless ($isDone || $isLocked || $unit->reportsOwnResult())
                    @if (app(\App\Services\Learning\LearningVideoProgressService::class)->requirementsFor($unit) !== [])
                        {{-- Dieser Zugang spielt keine Videos; der Pflichtanteil (MVP-1598) ist hier nicht erfüllbar. --}}
                        <p class="w-full text-xs text-warning" role="status">{{ __('learning.external.video_required') }}</p>
                    @else
                        <form method="POST" action="{{ route('learning.external.units.complete', $unit) }}">
                            @csrf
                            <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.complete_unit') }}</x-icon-btn>
                        </form>
                    @endif
                @endunless
            </div>

            {{-- Alle Blockarten wie im Player (MVP-1628): Medien über die Gastroute, ohne Verweise ins Haus. --}}
            @unless ($isLocked)
                @include('learning._blocks', [
                    'blocks' => $unit->blocks(),
                    'portal' => true,
                    'mediaUrl' => fn (int $id): ?string => $unit->attachments->contains('id', $id)
                        ? route('learning.external.units.media', [$unit, $unit->attachments->firstWhere('id', $id)])
                        : null,
                ])
            @endunless
        </x-card>
    @endforeach
</div>
@endsection
