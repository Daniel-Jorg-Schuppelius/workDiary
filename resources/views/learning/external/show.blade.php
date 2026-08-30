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
        <div class="alert alert-success text-sm">
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
        @php $isDone = in_array($unit->id, $completedUnitIds, true); @endphp
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="{{ $isDone ? 'check_circle' : 'radio_button_unchecked' }}"
                            class="{{ $isDone ? 'text-success' : 'text-muted' }}" />
                    {{ $unit->title }}
                </h2>
                @unless ($isDone)
                    <form method="POST" action="{{ route('learning.external.units.complete', $unit) }}">
                        @csrf
                        <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.complete_unit') }}</x-icon-btn>
                    </form>
                @endunless
            </div>

            @foreach ($unit->blocks() as $block)
                @if (($block['type'] ?? null) === 'text' && isset($block['text']))
                    <p class="mt-3 whitespace-pre-line text-sm text-base-content/80">{{ $block['text'] }}</p>
                @elseif (($block['type'] ?? null) === 'heading' && isset($block['text']))
                    <h3 class="mt-3 text-sm font-semibold">{{ $block['text'] }}</h3>
                @elseif (($block['type'] ?? null) === 'checklist' && isset($block['items']))
                    <ul class="mt-3 list-disc pl-5 text-sm text-base-content/80">
                        @foreach ($block['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        </x-card>
    @endforeach
</div>
@endsection
