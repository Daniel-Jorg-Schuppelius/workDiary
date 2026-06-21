{{-- In-App-Hilfe (MVP-051 + Feature 039 Inkrement 1). Wird einmal pro Seite --}}
{{-- eingebunden und über data-help-trigger / [data-help-topic] bzw. den --}}
{{-- Seitenkontext (body[data-help-context]) gefüllt. JS in resources/js/help-drawer.js. --}}
{{-- Desktop (lg+): nicht-modale rechte Sidebar unterhalb des Headers, ohne --}}
{{-- Backdrop — der Seiteninhalt bekommt über body.help-sidebar-open rechts --}}
{{-- Platz (.with-help-pad im Layout) und bleibt voll bedienbar. --}}
{{-- Mobil: Drawer mit Backdrop wie bisher. --}}
<div id="help-drawer"
     class="wd-badge fixed inset-y-0 right-0 z-[60] hidden w-full max-w-md translate-x-full transform overflow-hidden border-l border-base-300 bg-base-100 shadow-lg transition-transform lg:top-[var(--app-header-h)] lg:bottom-[var(--app-footer-h)] lg:z-40 lg:w-[var(--help-sidebar-w)] lg:max-w-[var(--help-sidebar-w)] lg:shadow-xl"
     data-help-drawer
     role="complementary"
     tabindex="-1"
     aria-label="{{ __('Hilfe zur aktuellen Seite') }}"
     aria-labelledby="help-drawer-title"
     data-text-error="{{ __('Hilfe konnte nicht geladen werden.') }}"
     data-text-missing="{{ __('Kein Hilfetext verfügbar.') }}">
    <div class="flex h-full flex-col">
        {{-- Schlanker Header: links Label, rechts Schließen — beide gleich
             hoch (items-center). Die Topic-Überschrift sitzt im
             Inhaltsbereich darunter. --}}
        <header class="flex h-12 shrink-0 items-center justify-between gap-3 border-b border-base-300 px-4">
            <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Hilfe') }}</p>
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
            <p class="text-base-content/60">{{ __('Wird geladen…') }}</p>
        </div>

        <footer class="shrink-0 border-t border-base-300 px-4 py-3" data-help-footer>
            <p class="mb-2 text-xs uppercase tracking-wider text-base-content/60">{{ __('War das hilfreich?') }}</p>
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
                <span class="ml-2 text-xs text-base-content/60 hidden" data-help-feedback-thanks>{{ __('Danke für dein Feedback.') }}</span>
            </div>
            <div class="mt-3 hidden" data-help-related>
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Verwandte Themen') }}</p>
                <ul class="mt-1 space-y-1 text-sm" data-help-related-list></ul>
            </div>
        </footer>
    </div>
</div>

{{-- Minimierte Hilfe-Rail (Feature 039): ab lg IMMER sichtbare schmale Schiene
     rechts. Klick (data-help-trigger ohne Topic → JS öffnet Seitenkontext-Hilfe)
     klappt die volle Hilfe-Sidebar auf; deren Schließen-Button minimiert wieder
     auf die Rail. Auf Mobil ausgeblendet — dort bleibt der Header-Button. --}}
<div id="help-rail"
     class="wd-badge fixed z-40 hidden flex-col items-center gap-2 py-3 shadow-xl lg:flex"
     aria-label="{{ __('Hilfe') }}">
    <x-icon-btn icon="help"
                tone="ghost"
                size="sm"
                class="btn-square"
                label="{{ __('Hilfe öffnen') }}"
                data-help-trigger
                aria-haspopup="dialog"
                aria-controls="help-drawer" />
</div>

{{-- Backdrop nur mobil (<lg): Desktop-Sidebar ist nicht-modal. Bezieht sich –
     wie der Sidebar-Menü-Backdrop – nur auf den CONTENT-Bereich (zwischen
     Header und Footer), damit Header/Footer frei bleiben. --}}
<div id="help-drawer-backdrop"
     class="fixed inset-x-0 z-[55] hidden bg-base-300/40 backdrop-blur-[2px] lg:hidden!"
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
                <x-icon name="search" class="text-base-content/50" />
                <input type="search" name="q" class="grow" minlength="2"
                       placeholder="{{ __('Hilfethemen durchsuchen…') }}"
                       aria-label="{{ __('Hilfethemen durchsuchen') }}">
            </label>
            <x-button type="submit" tone="outline" size="sm">{{ __('Suchen') }}</x-button>
        </form>
        <ul class="space-y-1 text-sm" data-help-search-results></ul>
    </div>
</template>
