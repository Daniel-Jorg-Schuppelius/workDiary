{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : readings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Meine Lektüre (Feature 191, MVP-1666): offene Pflichtlektüre (je
  freigegebener Fassung) und die Einarbeitungsliste mit Fortschritt.
  Variablen: $open, $onboarding.
--}}
@extends('layouts.app')

@section('title', __('knowledge_pages.reading.mine'))
@section('nav-title', __('knowledge_pages.reading.mine'))

@section('content')
@php
    $onboardingDone = count(array_filter($onboarding, static fn (array $row): bool => $row['confirmed']));
@endphp
<x-page-shell>
    <x-slot:toolbar>
    <x-page-toolbar :subtitle="__('knowledge_pages.reading.subtitle')" />
    </x-slot:toolbar>
    <x-knowledge-tabs />

    <x-card :title="__('knowledge_pages.reading.open_title')" icon="menu_book" :count="count($open)">
        @if ($open === [])
            <x-empty-state compact icon="task_alt" :title="__('knowledge_pages.reading.none_open')" />
        @else
            <ul class="space-y-2" role="list">
                @foreach ($open as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 p-2 text-sm">
                        <span class="flex min-w-0 items-center gap-2">
                            @if ($row['onboarding'])
                                <x-status-badge tone="info" size="xs" icon="school">{{ __('knowledge_pages.reading.onboarding') }}</x-status-badge>
                            @endif
                            <a class="link link-hover truncate font-medium" href="{{ route('knowledge.pages.show', $row['page']) }}">{{ $row['page']->title }}</a>
                            <span class="text-xs text-muted">{{ $row['page']->space?->title }}</span>
                        </span>
                        <span class="text-xs text-muted">
                            {{ $row['confirmed_version'] !== null
                                ? __('knowledge_pages.reading.new_version', ['old' => $row['confirmed_version'], 'version' => $row['page']->approved_version])
                                : __('knowledge_pages.version.label', ['version' => $row['page']->approved_version]) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card :title="__('knowledge_pages.reading.onboarding_title')" icon="school" :count="count($onboarding)">
        @if ($onboarding === [])
            <x-empty-state compact icon="school" :title="__('knowledge_pages.reading.onboarding_empty')" />
        @else
            <progress class="progress progress-primary mb-3 w-full" value="{{ $onboardingDone }}" max="{{ count($onboarding) }}"
                      aria-label="{{ __('knowledge_pages.reading.progress', ['done' => $onboardingDone, 'total' => count($onboarding)]) }}"></progress>
            <p class="mb-2 text-xs text-muted">{{ __('knowledge_pages.reading.progress', ['done' => $onboardingDone, 'total' => count($onboarding)]) }}</p>
            <ol class="list-decimal space-y-1 ps-5 text-sm">
                @foreach ($onboarding as $row)
                    <li>
                        <a class="link link-hover" href="{{ route('knowledge.pages.show', $row['page']) }}">{{ $row['page']->title }}</a>
                        @if ($row['confirmed'])
                            <x-status-badge tone="success" size="xs" icon="task_alt">{{ __('knowledge_pages.reading.done') }}</x-status-badge>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </x-card>
</x-page-shell>
@endsection
