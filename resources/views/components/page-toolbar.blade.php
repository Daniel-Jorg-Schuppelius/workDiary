{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : page-toolbar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'badge' => null,
    'badgeTone' => 'primary',
    'badgeTitle' => null,
    'back' => null,
    'backRoute' => null,
    'backParams' => [],
    'backLabel' => null,
])

{{--
    <x-page-toolbar> — Einheitliche Toolbar-Karte oben auf Content-Pages.

    Corporate-Design-Standard (Index-Seiten):
      - KEIN `title` (der Seitentitel kommt aus @section('nav-title') im Layout).
      - `:subtitle` als kurze Beschreibung ist Pflicht — reiner Text; Markup
        gehört in den Standard-Slot darunter (der Untertitel wird zusätzlich
        als Tooltip gesetzt).
      - Rechte Aktionen via Slot `actions` (z. B. <x-icon-btn icon="add">).

    Für Index-Seiten bevorzugt <x-index-page> verwenden, das diese Toolbar
    bereits in <x-page-shell> einbettet.

    Einzeilig mit Überlaufmenü (MVP-966): Aktionen stehen beschriftet in der
    Leiste, solange Platz ist; der Rest wandert in „⋯“
    (resources/js/toolbar-overflow.js). Steuerung je Aktion über `placement`
    an x-icon-btn/x-button bzw. `data-toolbar-placement`: `bar` (Hauptaktion,
    bleibt stehen), `menu` (immer im Menü), `danger` (Löschen, Stornieren …,
    immer im Menü, abgesetzt). Unter `md` stehen die Aktionen in eigener Zeile.

    Rückpfeil vor dem Titel: `back-route` (Liste samt gemerkter Filter) oder
    `back` (URL, z. B. Elternobjekt); `back-label` benennt das Ziel.
    Statusabzeichen neben dem Titel über den Slot `badges`, nie im Aktionsslot.
--}}
@php
    $backUrl = $back ?? ($backRoute !== null ? \App\Http\Middleware\RememberListUrl::urlFor($backRoute, (array) $backParams) : null);
    $backText = $backLabel ?? __('Zurück');
    $hasSlot = trim($slot) !== '';
    $hasTitleRow = $title || $badge || isset($badges);
    $hasHead = $backUrl !== null || $hasTitleRow || $subtitle || $hasSlot;
@endphp

<div {{ $attributes->class([
    // shrink-0: in Voll-Höhe-Flex-Seiten darf der Kopf nicht von hohem
    // Inhalt (z. B. großen Tabellen) zusammengestaucht werden.
    'flex min-h-16 shrink-0 flex-wrap items-center justify-between gap-3 rounded-[var(--panel-radius)] border border-base-300 bg-base-100 p-4 shadow-xs md:flex-nowrap',
]) }} data-toolbar>
    @if ($hasHead)
        <div class="flex min-w-0 items-center gap-2 max-md:basis-full md:min-w-[min(14rem,45%)] md:flex-1" data-toolbar-head>
            @if ($backUrl !== null)
                <a href="{{ $backUrl }}" class="btn btn-ghost btn-sm btn-square shrink-0"
                   title="{{ $backText }}" aria-label="{{ $backText }}"><x-icon name="arrow_back" /></a>
            @endif
            <div class="min-w-0 flex flex-col gap-0.5">
                @if ($hasTitleRow)
                    <div class="flex items-center gap-2 min-w-0">
                        @if ($title)
                            <h2 class="font-['Space_Grotesk'] text-base font-semibold text-base-content truncate">{{ $title }}</h2>
                        @endif
                        @if ($badge)
                            {{-- badgeTitle: optionaler Volltext als Tooltip (z. B. Modul-Hinweis) --}}
                            <span class="badge badge-sm badge-{{ $badgeTone }} shrink-0"
                                  @if ($badgeTitle) title="{{ $badgeTitle }}" @endif>{{ $badge }}</span>
                        @endif
                        @isset($badges)
                            <div class="flex shrink-0 items-center gap-1">{{ $badges }}</div>
                        @endisset
                    </div>
                @endif
                @if ($subtitle)
                    {{-- Der Tooltip zeigt denselben Text, wenn die Zeile abgeschnitten wird.
                         Er muss reiner Text sein: ein Slot mit Markup (Htmlable) landete sonst
                         roh im Attribut, beendete es am ersten Anführungszeichen und der Rest
                         erschien sichtbar auf der Seite. --}}
                    <p class="text-xs text-muted md:truncate" title="{{ trim(strip_tags((string) $subtitle)) }}">{{ $subtitle }}</p>
                @endif
                @if ($hasSlot)
                    <div class="text-sm text-base-content/70">{{ $slot }}</div>
                @endif
            </div>
        </div>
    @endif

    @isset($actions)
        <div class="ms-auto flex min-w-0 flex-wrap items-center gap-2 max-md:basis-full md:justify-end" data-toolbar-actions>
            {{ $actions }}
            <details class="dropdown dropdown-end" data-toolbar-more data-menu hidden>
                <summary class="btn btn-ghost btn-sm btn-square" title="{{ __('Weitere Aktionen') }}"
                         aria-label="{{ __('Weitere Aktionen') }}"><x-icon name="more_horiz" /></summary>
                <div class="dropdown-content wd-toolbar-menu mt-1 flex max-h-[min(70vh,32rem)] w-64 max-w-[calc(100vw-2rem)] flex-col overflow-y-auto rounded-box border border-base-300 bg-base-100 p-1.5 shadow-lg">
                    <div class="flex flex-col gap-0.5" data-toolbar-menu-main></div>
                    <div class="mt-1 flex flex-col gap-0.5 border-t border-base-300 pt-1" data-toolbar-menu-danger hidden></div>
                </div>
            </details>
        </div>
    @endisset
</div>
