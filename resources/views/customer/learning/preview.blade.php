{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kursvorschau ohne Einschreibung (Feature 149, MVP-788): nur Einheiten
  mit Vorschau-Kennzeichen und darin nur Textblöcke — Medien, Prüfungen und
  Aufgaben hängen an einer Einschreibung.
--}}
@extends('customer.layout')
@section('title', $course->title)
@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $course->title }}</h1>
            <p class="text-sm text-muted">{{ __('learning.title.preview') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($enrollment)
                <x-icon-btn icon="play_arrow" tone="primary" size="sm"
                            :href="route('customer.learning.show', $enrollment)"
                            show-label>{{ __('learning.action.open_course') }}</x-icon-btn>
            @else
                <form method="POST" action="{{ route('customer.learning.enroll', $course) }}">
                    @csrf
                    <x-icon-btn icon="school" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.enroll') }}</x-icon-btn>
                </form>
            @endif
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('customer.learning.index')"
                        show-label>{{ __('learning.action.back') }}</x-icon-btn>
        </div>
    </div>

    @if ($course->description)
        <x-card>
            <p class="whitespace-pre-line text-sm text-base-content/80">{{ $course->description }}</p>
        </x-card>
    @endif

    @forelse ($units as $unit)
        <x-card>
            <h2 class="flex items-center gap-2 text-sm font-semibold">
                <x-icon name="visibility" class="text-muted" />
                {{ $unit->title }}
            </h2>
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
    @empty
        <x-empty-state icon="visibility_off" :title="__('learning.empty.preview')" />
    @endforelse
</div>
@endsection
