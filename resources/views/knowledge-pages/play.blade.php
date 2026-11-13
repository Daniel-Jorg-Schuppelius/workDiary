{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : play.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  „Abspielen“ einer Bildanleitung (Feature 191, MVP-1668): ein Schritt je
  Ansicht mit vor und zurück. Variablen: $page, $content, $index, $total,
  $step.
--}}
@extends('layouts.app')

@section('title', $content['title'] . ' — ' . __('knowledge_pages.guide.play'))
@section('nav-title', $content['title'])

@section('content')
<x-page-shell>
    <x-slot:toolbar>
    <x-page-toolbar :subtitle="__('knowledge_pages.guide.play_progress', ['index' => $index, 'total' => $total])" :back="route('knowledge.pages.show', $page)" />
    </x-slot:toolbar>
    <progress class="progress progress-primary w-full" value="{{ $index }}" max="{{ $total }}" aria-label="{{ __('knowledge_pages.guide.play_progress', ['index' => $index, 'total' => $total]) }}"></progress>
    <x-card as="section" :title="__('knowledge_pages.guide.step_number', ['number' => $index])" icon="photo_library">
        <div class="space-y-3" data-guide-player>
            @include('knowledge-pages._step_image', ['image' => $step['image'], 'markers' => $step['markers'], 'alt' => __('knowledge_pages.guide.step_number', ['number' => $index])])
            @if ($step['body'])
                <p class="whitespace-pre-line text-base">{{ $step['body'] }}</p>
            @endif
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            @if ($index > 1)
                <x-icon-btn icon="chevron_left" size="sm" tone="outline" show-label :href="route('knowledge.pages.play', ['page' => $page, 'schritt' => $index - 1])">{{ __('knowledge_pages.guide.previous') }}</x-icon-btn>
            @else
                <span></span>
            @endif
            @if ($index < $total)
                <x-icon-btn icon="chevron_right" size="sm" tone="primary" show-label :href="route('knowledge.pages.play', ['page' => $page, 'schritt' => $index + 1])">{{ __('knowledge_pages.guide.next') }}</x-icon-btn>
            @else
                <x-icon-btn icon="done_all" size="sm" tone="success" show-label :href="route('knowledge.pages.show', $page)">{{ __('knowledge_pages.guide.finish') }}</x-icon-btn>
            @endif
        </div>
    </x-card>
</x-page-shell>
@endsection
