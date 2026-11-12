{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kursansicht im Kundenportal (Feature 149, MVP-742).
--}}
@extends('customer.layout')
@section('title', $course?->title ?? __('learning.title.portal'))
@section('content')
<div class="space-y-4">
    <x-page-toolbar :title="$course?->title" :back="route('customer.learning.index')" :back-label="__('learning.action.back')">
        <x-slot:badges>
            <x-status-badge :tone="$enrollment->status->tone()" size="sm">{{ $enrollment->status->label() }}</x-status-badge>
        </x-slot:badges>
    </x-page-toolbar>

    @if ($enrollment->status === \App\Enums\Learning\LearningEnrollmentStatus::Completed)
        <div role="status" class="alert alert-success text-sm">
            <x-icon name="verified" />
            <span>{{ __('learning.external.completed') }}</span>
        </div>
    @endif

    @foreach ($course?->units ?? [] as $unit)
        @php
            $isDone = in_array($unit->id, $completedUnitIds, true);
            $isLocked = ! $isDone && ! $unit->isReleasedFor($enrollment, null, $completedUnitIds);
            // Pflichtanteil der Videos (MVP-1598): die Sperre sitzt im Dienst; hier Anzeige und Knopf.
            $videoGated = ! $isDone && ! $isLocked && ! $unit->reportsOwnResult()
                && app(\App\Services\Learning\LearningVideoProgressService::class)->unmetFor($enrollment, $unit, $videoProgress) !== [];
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
                @if (! $isDone && ! $isLocked && $unit->reportsOwnResult())
                    {{-- Prüfung, Termin, Prozedur, Umfrage, Extern: im Portal nicht abschließbar. --}}
                    <p class="w-full text-xs text-muted" role="status">{{ __('learning.external.unit_unavailable') }}</p>
                @endif
                @unless ($isDone || $isLocked || $unit->reportsOwnResult())
                    <form method="POST" action="{{ route('customer.learning.units.complete', [$enrollment, $unit]) }}" data-video-gate="{{ $unit->sqid }}">
                        @csrf
                        <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" :disabled="$videoGated" show-label>{{ __('learning.action.complete_unit') }}</x-icon-btn>
                    </form>
                    @if ($videoGated)
                        <p class="w-full text-right text-xs text-warning" data-video-gate-hint="{{ $unit->sqid }}">{{ __('learning.help.video_gate') }}</p>
                    @endif
                @endunless
            </div>

            @unless ($isLocked)
                @include('learning._blocks', [
                    'blocks' => $unit->blocks(),
                    'mediaState' => $mediaState,
                    'mediaUrl' => fn (int $id): ?string => ($a = $unit->attachments->firstWhere('id', $id))
                        ? route('customer.learning.units.media', [$enrollment->sqid, $unit->sqid, $a->sqid])
                        : null,
                    'videoProgress' => $videoProgress,
                    'videoGate' => $unit->sqid,
                    'videoProgressUrl' => fn (int $id): ?string => ! $enrollment->status->isFinal() && ! $unit->reportsOwnResult() && ($a = $unit->attachments->firstWhere('id', $id))
                        ? route('customer.learning.units.video-progress', [$enrollment->sqid, $unit->sqid, $a->sqid])
                        : null,
                    'portal' => true,
                ])
            @endunless
        </x-card>
    @endforeach
</div>
@endsection
