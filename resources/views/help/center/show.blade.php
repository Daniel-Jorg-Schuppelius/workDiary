{{--
  Created on   : Tue Sep 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Hilfecenter-Themenseite (Feature 039, MVP-752): serverseitig gerendertes
     body_html (Markdown, html_input=escape) aus help_topics — dieselbe Zeile,
     die auch der Drawer zeigt. TOC aus den beim Reindex extrahierten
     h2-Ankern; Feedback über den bestehenden JSON-Endpunkt (help-center.js). --}}

@extends('layouts.app')

@section('title', $row->title . ' – ' . __('Hilfecenter'))
@section('nav-title', __('Hilfecenter'))

@section('content')
<x-index-page :subtitle="__('Ausführliche Hilfe mit Hintergrund, Ablauf und nächsten Schritten.')">
    <x-slot:actions>
        <x-button tone="outline" size="sm" icon="arrow_back" :href="route('help.center.index')">
            {{ __('Zur Übersicht') }}
        </x-button>
        <x-button tone="outline" size="sm" icon="flag" class="btn-warning"
                  data-entry-modal-trigger
                  :href="route('problem-reports.create', ['route' => 'help.center.show', 'url' => url()->current(), 'topic' => $row->topic])">
            {{ __('errors.report_problem') }}
        </x-button>
    </x-slot:actions>

    {{-- Breadcrumb in der Toolbar-Karte (Default-Slot der x-page-toolbar). --}}
    <nav aria-label="{{ __('Pfadnavigation') }}" class="flex flex-wrap items-center gap-1.5 text-sm">
        <a href="{{ route('help.center.index') }}" class="link link-primary">{{ __('Hilfecenter') }}</a>
        <x-icon name="chevron_right" class="text-muted" />
        <a href="{{ route('help.center.index', ['bereich' => $sectionKey]) }}" class="link link-primary">{{ $sectionTitle }}</a>
        <x-icon name="chevron_right" class="text-muted" />
        <span class="text-muted">{{ $row->title }}</span>
    </nav>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
        <article class="rounded-(--panel-radius) border border-base-300 bg-base-100 p-6 shadow-xs lg:p-8">
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <span class="badge badge-sm badge-primary badge-outline">{{ $sectionTitle }}</span>
                <span class="text-xs text-muted">
                    {{ __('Version :version', ['version' => $row->version]) }}
                    @if ($row->source_updated_at)
                        &middot; {{ __('aktualisiert am :date', ['date' => $row->source_updated_at->translatedFormat('d.m.Y')]) }}
                    @endif
                </span>
            </div>
            {{-- h2, nicht h1 (View-Gate V2): der Seitentitel lebt im Layout
                 (nav-title); Artikel-Abschnitte aus dem Markdown sind ebenfalls h2. --}}
            <h2 class="mb-4 font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ $row->title }}</h2>

            {{-- Serverseitig gerendertes Markdown (Roh-HTML escaped, unsichere
                 Links deaktiviert) — kein clientseitiges innerHTML. --}}
            {{-- Volle Kartenbreite (Nutzerentscheid 2026-09-01): kein
                 max-w-prose — der Artikel füllt den Container aus. --}}
            <div class="help-article text-sm leading-relaxed text-base-content">
                {!! $row->body_html !!}
            </div>
        </article>

        {{-- help-aside: beim Drucken verschwindet die Spalte (nur der Artikel zählt). --}}
        <div class="help-aside flex flex-col gap-4">
            @if (count($headings) >= 3)
                <nav aria-label="{{ __('Auf dieser Seite') }}"
                     class="rounded-(--panel-radius) border border-base-300 bg-base-100 p-4 shadow-xs lg:sticky lg:top-2">
                    <p class="mb-2 text-xs uppercase tracking-wider text-muted">{{ __('Auf dieser Seite') }}</p>
                    <ul class="flex flex-col gap-0.5 text-sm">
                        @foreach ($headings as $heading)
                            <li>
                                <a href="#{{ $heading['anchor'] }}"
                                   class="block rounded px-2 py-1 text-base-content transition-colors hover:bg-base-200 hover:text-primary">
                                    {{ $heading['text'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            @if ($related !== [])
                <div class="rounded-(--panel-radius) border border-base-300 bg-base-100 p-4 shadow-xs">
                    <p class="mb-2 text-xs uppercase tracking-wider text-muted">{{ __('Verwandte Themen') }}</p>
                    <ul class="flex flex-col gap-1.5 text-sm">
                        @foreach ($related as $entry)
                            <li>
                                <a href="{{ route('help.center.show', ['topic' => $entry['topic']]) }}" class="link link-primary">
                                    {{ $entry['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-(--panel-radius) border border-base-300 bg-base-100 p-4 shadow-xs"
                 data-help-center-feedback
                 data-feedback-url="{{ route('help.topics.feedback', ['topic' => $row->topic]) }}"
                 data-feedback-locale="{{ $row->locale }}">
                <p class="mb-2 text-xs uppercase tracking-wider text-muted">{{ __('War das hilfreich?') }}</p>
                <div class="flex gap-2">
                    <x-button type="button" tone="outline" size="sm" icon="thumb_up" class="btn-success flex-1" data-help-center-vote="1">
                        {{ __('Ja') }}
                    </x-button>
                    <x-button type="button" tone="outline" size="sm" icon="thumb_down" class="btn-error flex-1" data-help-center-vote="0">
                        {{ __('Nein') }}
                    </x-button>
                </div>
                <p class="mt-2 hidden text-xs text-muted" data-help-center-thanks>{{ __('Danke für dein Feedback.') }}</p>
            </div>
        </div>
    </div>
</x-index-page>
@endsection
