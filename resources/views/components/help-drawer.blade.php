{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : help-drawer.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- In-App-Hilfe (MVP-051 + Feature 039 Inkrement 1). Wird einmal pro Seite --}}
{{-- eingebunden und über data-help-trigger / [data-help-topic] bzw. den --}}
{{-- Seitenkontext (body[data-help-context]) gefüllt. JS in resources/js/help-drawer.js. --}}
{{-- Desktop (lg+): nicht-modale rechte Sidebar unterhalb des Headers, ohne --}}
{{-- Backdrop — der Seiteninhalt bekommt über body.help-sidebar-open rechts --}}
{{-- Platz (.with-help-pad im Layout) und bleibt voll bedienbar. Zugeklappt --}}
{{-- ist der Drawer selbst die schmale Rail; die Breite animiert wie bei der --}}
{{-- linken Sidebar (width-Transition in layout.css). --}}
{{-- Mobil: Drawer mit Slide-in + Backdrop wie bisher. --}}
<div id="help-drawer"
     class="wd-badge fixed inset-y-0 right-0 z-60 w-full max-w-md translate-x-full transform overflow-hidden border-l border-base-300 bg-base-100 shadow-lg lg:top-(--app-header-h) lg:bottom-(--app-footer-h) lg:z-40 lg:shadow-xl"
     data-help-drawer
     role="complementary"
     tabindex="-1"
     aria-label="{{ __('Hilfe zur aktuellen Seite') }}"
     aria-labelledby="help-drawer-title"
     data-text-error="{{ __('Hilfe konnte nicht geladen werden.') }}"
     data-text-missing="{{ __('Kein Hilfetext verfügbar.') }}">
    <div class="flex h-full flex-col" data-help-main>
        {{-- Schlanker Header: links Label, rechts Schließen — beide gleich
             hoch (items-center). Die Topic-Überschrift sitzt im
             Inhaltsbereich darunter. --}}
        <header class="flex h-12 shrink-0 items-center justify-between gap-3 border-b border-base-300 px-4">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Hilfe') }}</p>
            {{-- Rot, damit der Schließen-Button sich klar abhebt (analog zur
                 roten Schließen-Optik der Dialoge). Outline + Farbe, weil
                 nacktes btn-outline auf dem dunklen wd-badge-Grund zu blass
                 wäre. --}}
            <x-icon-btn icon="close" tone="outline" size="sm"
                        class="btn-square btn-error"
                        label="{{ __('Schließen') }}" data-help-close />
        </header>

        <div class="shrink-0 px-4 pt-3">
            <h2 id="help-drawer-title" class="font-['Space_Grotesk'] text-base font-semibold text-base-content" data-help-title>{{ __('Wird geladen…') }}</h2>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 text-sm leading-relaxed text-base-content" data-help-body>
            <p class="text-muted">{{ __('Wird geladen…') }}</p>
        </div>

        {{-- Footer mit Feedback/Aktionen. Auf niedrigen Bildschirmen frisst er
             den Platz für den Hilfetext — daher ist der Inhalt einklappbar
             (Toggle-Leiste). Der Zustand wird gemerkt; bei geringer Viewport-
             Höhe klappt er standardmäßig ein (JS in help-drawer.js). --}}
        <footer class="shrink-0 border-t border-base-300" data-help-footer>
            <button type="button"
                    class="flex w-full items-center justify-between gap-2 px-4 py-2 text-xs uppercase tracking-wider text-muted transition-colors hover:text-base-content"
                    data-help-footer-toggle
                    aria-expanded="true"
                    aria-controls="help-footer-content">
                <span>{{ __('Feedback & Aktionen') }}</span>
                <x-icon name="expand_more" class="shrink-0 transition-transform" data-help-footer-chevron />
            </button>
            <div id="help-footer-content" class="px-4 pb-3" data-help-footer-content>
                <p class="mb-2 text-xs uppercase tracking-wider text-muted">{{ __('War das hilfreich?') }}</p>
                {{-- Outline + Akzentfarbe (grün/rot): nacktes btn-outline ist auf
                     dem dunklen wd-badge-Grund kaum sichtbar – die leuchtenden
                     success/error-Farben heben sich klar ab (wie der Schließen-
                     Button oben). --}}
                <div class="flex flex-wrap items-center gap-2">
                    <x-button type="button" tone="outline" size="sm" icon="thumb_up" class="btn-success" data-help-feedback="1">
                        {{ __('Ja') }}
                    </x-button>
                    <x-button type="button" tone="outline" size="sm" icon="thumb_down" class="btn-error" data-help-feedback="0">
                        {{ __('Nein') }}
                    </x-button>
                    <span class="ml-2 text-xs text-muted hidden" data-help-feedback-thanks>{{ __('Danke für dein Feedback.') }}</span>
                </div>
                {{-- Tastenkürzel-Übersicht (Feature 037, MVP-721): Topic-Link, gleiche Naht wie jeder Help-Trigger. --}}
                <button type="button"
                        class="mt-2 link link-primary text-sm"
                        data-help-trigger
                        data-help-topic="account.shortcuts">
                    {{ __('Tastenkürzel') }}
                </button>
                <div class="mt-3 hidden" data-help-related>
                    <p class="text-xs uppercase tracking-wider text-muted">{{ __('Verwandte Themen') }}</p>
                    <ul class="mt-1 space-y-1 text-sm" data-help-related-list></ul>
                </div>
                {{-- „Problem melden" (Feature 041, MVP-053): primärer Einstieg des
                     Fehlermeldesystems — übernimmt Route/URL/Help-Topic der Seite. --}}
                @auth
                    <div class="mt-3 border-t border-base-300 pt-3">
                        <x-button tone="outline" size="sm" icon="flag" class="btn-warning w-full"
                                  data-entry-modal-trigger
                                  :href="route('problem-reports.create', array_filter([
                                      'route' => \Illuminate\Support\Facades\Route::currentRouteName(),
                                      'url' => url()->full(),
                                      'topic' => app(\App\Services\Help\HelpContextResolver::class)->currentTopicFor(request()),
                                  ]))">
                            {{ __('errors.report_problem') }}
                        </x-button>
                        <a href="{{ route('problem-reports.index') }}" class="mt-1 block text-center text-xs text-muted hover:text-base-content">
                            {{ __('problemreport.title.index') }}
                        </a>
                    </div>
                @endauth
            </div>
        </footer>
    </div>

    {{-- Minimierte Rail-Ansicht (Feature 039 + Neuigkeiten-MVP): ab lg ist der
         zugeklappte Drawer selbst die schmale Schiene. Anders als zuvor darf
         die gesamte Rail kein einzelner Button mehr sein: die optionale RSS-
         Schlagzeile ist ein eigener externer Link, Hilfe und Pause sind echte
         getrennte Aktionen (kein verschachteltes interaktives Markup). --}}
    {{-- px-3/py-4 spiegeln die Innenabstände der eingeklappten Menü-Sidebar
         (sidebar-header/-footer px-3 py-4) — Inhalte kleben sonst an den
         Panelkanten. --}}
    @php
        $newsItems = app(\App\Services\UI\SidebarNewsFeedService::class)->items();
        $newsRotationMs = app(\App\Services\UI\SidebarNewsFeedService::class)->rotationIntervalMilliseconds();
    @endphp
    <div class="absolute inset-y-0 right-0 hidden w-(--help-rail-w) flex-col items-center gap-2 px-3 py-4 lg:flex"
         data-help-railmode>
        @if ($newsItems !== [])
            <x-icon-btn icon="help" tone="ghost" size="sm"
                        :label="__('Hilfe öffnen')"
                        data-help-trigger aria-haspopup="dialog" aria-controls="help-drawer" />

            <div class="flex min-h-0 w-full flex-1 flex-col items-center gap-2 rounded-xl border border-base-300/60 bg-base-100/40 py-2"
                 data-help-news data-news-rotation-ms="{{ $newsRotationMs }}"
                 data-label-pause="{{ __('Neuigkeiten pausieren') }}"
                 data-label-resume="{{ __('Neuigkeiten fortsetzen') }}">
                <x-icon name="newspaper" class="shrink-0 text-muted" />
                <div class="relative min-h-0 w-full flex-1 overflow-hidden" data-help-news-items>
                    @foreach ($newsItems as $index => $newsItem)
                        @php($newsLabel = __('Neuigkeit von :source: :title', ['source' => $newsItem['source'], 'title' => $newsItem['title']]))
                        <a href="{{ $newsItem['url'] }}"
                           target="_blank" rel="noopener noreferrer"
                           @class([
                               'absolute inset-0 flex items-center justify-center overflow-hidden rounded-lg px-1 py-2 text-xs text-base-content/80 transition-opacity hover:bg-base-content/10 hover:text-base-content focus-visible:ring-2 focus-visible:ring-primary/60',
                               'opacity-100' => $index === 0,
                               'pointer-events-none opacity-0' => $index !== 0,
                           ])
                           data-help-news-item
                           @if ($index !== 0) aria-hidden="true" tabindex="-1" @endif
                           title="{{ $newsLabel }}" aria-label="{{ $newsLabel }}">
                            <span class="wd-help-news-title" aria-hidden="true">{{ $newsItem['title'] }}</span>
                        </a>
                    @endforeach
                </div>
                @if (count($newsItems) > 1)
                    <button type="button"
                            class="btn btn-xs btn-ghost btn-square shrink-0"
                            data-help-news-toggle
                            aria-pressed="false"
                            title="{{ __('Neuigkeiten pausieren') }}"
                            aria-label="{{ __('Neuigkeiten pausieren') }}">
                        <x-icon name="pause" data-help-news-toggle-icon />
                    </button>
                @endif
            </div>

            <x-icon-btn icon="chevron_left" tone="primary" size="sm"
                        class="w-full justify-center"
                        :label="__('Hilfe aufklappen')"
                        data-help-trigger aria-haspopup="dialog" aria-controls="help-drawer" />
        @else
            <button type="button"
                    class="flex h-full w-full flex-col items-center justify-between rounded-xl text-xs uppercase tracking-wider text-muted transition-colors hover:bg-base-content/10 hover:text-base-content"
                    data-help-trigger aria-haspopup="dialog" aria-controls="help-drawer"
                    title="{{ __('Hilfe öffnen') }}" aria-label="{{ __('Hilfe öffnen') }}">
                <span class="btn btn-sm btn-ghost btn-square pointer-events-none" aria-hidden="true">
                    <x-icon name="help" />
                </span>
                <span class="wd-help-news-title" aria-hidden="true">{{ __('Hilfe') }}</span>
                <span class="btn btn-sm btn-primary pointer-events-none w-full justify-center" aria-hidden="true">
                    <x-icon name="chevron_left" />
                </span>
            </button>
        @endif
    </div>
</div>

{{-- Backdrop nur mobil (<lg): Desktop-Sidebar ist nicht-modal. Bezieht sich –
     wie der Sidebar-Menü-Backdrop – nur auf den CONTENT-Bereich (zwischen
     Header und Footer), damit Header/Footer frei bleiben. --}}
<div id="help-drawer-backdrop"
     class="help-backdrop-hidden fixed inset-x-0 z-55 bg-base-300/40 backdrop-blur-[2px] lg:hidden!"
     style="top: var(--app-header-h); bottom: var(--app-footer-h);"
     data-help-backdrop></div>

{{-- Fallback-Panel (Feature 039): erscheint, wenn die Seite keinen --}}
{{-- Hilfe-Kontext hat oder ein Topic fehlt. Texte serverseitig übersetzt, --}}
{{-- JS klont nur den Inhalt (kein Inline-JS, CSP-freundlich). --}}
<template data-help-fallback
          data-fallback-title="{{ __('Hilfe') }}"
          data-empty-results="{{ __('Keine passenden Hilfethemen gefunden.') }}">
    <div class="space-y-3">
        <p class="text-base-content/70" data-help-fallback-message>{{ __('Für diese Seite gibt es noch keine Hilfe.') }}</p>
        <form data-help-search-form role="search" class="flex items-center gap-2">
            <label class="input input-sm input-bordered flex grow items-center gap-2">
                <x-icon name="search" class="text-muted" />
                <input type="search" name="q" class="grow" minlength="2"
                       placeholder="{{ __('Hilfethemen durchsuchen…') }}"
                       aria-label="{{ __('Hilfethemen durchsuchen') }}">
            </label>
            <x-button type="submit" tone="outline" size="sm">{{ __('Suchen') }}</x-button>
        </form>
        <ul class="space-y-1 text-sm" data-help-search-results></ul>
    </div>
</template>
