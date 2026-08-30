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
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-xl font-semibold">{{ $course?->title }}</h1>
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                    :href="route('customer.learning.index')"
                    show-label>{{ __('learning.action.back') }}</x-icon-btn>
    </div>

    @if ($enrollment->status === \App\Enums\Learning\LearningEnrollmentStatus::Completed)
        <div class="alert alert-success text-sm">
            <x-icon name="verified" />
            <span>{{ __('learning.external.completed') }}</span>
        </div>
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
                    <form method="POST" action="{{ route('customer.learning.units.complete', [$enrollment, $unit]) }}">
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
                @endif
            @endforeach
        </x-card>
    @endforeach
</div>
@endsection
