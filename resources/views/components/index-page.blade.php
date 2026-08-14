{{--
  Created on   : Tue May 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index-page.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'title'    => null,
    'subtitle' => null,
    'badge'    => null,
    'badgeTone' => 'primary',
    'badgeTitle' => null,
    'gap'      => 4,
    'overflow' => 'auto',
    'height'   => 'standard',
])

{{--
    <x-index-page> — Standard-Skeleton für Index-/Listenseiten (Corporate Design).

    Bündelt <x-page-shell> + <x-page-toolbar> und stellt sicher, dass jede
    Index-Seite identisch aufgebaut ist: oben die Toolbar-Karte mit kurzer
    Beschreibung (subtitle) und Aktionen (Slot `actions`); darunter der Inhalt.

    Pflicht: kein eigener Titel im Body (Seitentitel kommt aus @section('nav-title')).

    Props (Toolbar):
      - title     : optionaler Titel in der Toolbar (i. d. R. via @section('nav-title'))
      - subtitle  : kurze Seitenbeschreibung (Pflicht im Standard)
      - badge     : optionaler Status-/Kontext-Badge
      - badgeTone : Tone für Badge (primary|success|warning|error|info)

    Props (Shell, durchgereicht an x-page-shell):
      - gap       : Lücke zwischen Karten (Tailwind-Spacing, Default 4)
      - overflow  : auto (Default) | clip
      - height    : standard (Default) | content

    Slots:
      - actions (named) : rechte Toolbar-Aktionen (z. B. x-icon-btn „Anlegen")
      - note (named)    : optionaler Beschreibungstext unter dem Subtitle in der Toolbar
      - default         : Karten/Inhalt (Filter-Card, Tabellen, Empty-States …)

    Beispiel (ohne Filter):
        <x-index-page :subtitle="__('Kunden des Mandanten :org verwalten.', ['org' => $org->name])">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            :href="route('customers.create')"
                            show-label>{{ __('Kunde anlegen') }}</x-icon-btn>
            </x-slot:actions>

            @if ($customers->isEmpty())
                <x-empty-state framed
                    icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>' />
            @else
                <x-table>…</x-table>
            @endif
        </x-index-page>
--}}

<x-page-shell :gap="$gap" :overflow="$overflow" :height="$height">
    <x-slot:toolbar>
        <x-page-toolbar :title="$title" :subtitle="$subtitle" :badge="$badge" :badgeTone="$badgeTone" :badgeTitle="$badgeTitle">
            @isset($actions)
                <x-slot:actions>{{ $actions }}</x-slot:actions>
            @endisset
            @isset($note){{ $note }}@endisset
        </x-page-toolbar>
    </x-slot:toolbar>

    {{ $slot }}
</x-page-shell>
