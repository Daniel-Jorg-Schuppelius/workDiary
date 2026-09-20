{{--
  Created on   : Sat Sep 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : knowledge-tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Typleiste des Wissensbereichs (MVP-820): links der Einstieg „Wissen" mit
  allen Inhalten, rechts die Fachlisten als Typansichten. Vorher standen sie
  als gleichrangige Menüpunkte nebeneinander und zeigten teils denselben
  Bestand — die Sidebar führt jetzt nur noch den Einstieg.

  Der Wechsel nimmt die Filter mit (MVP-821): Wer im Einstieg einen Kunden
  wählt und auf „Dokumente" geht, sieht dessen Dokumente. Mitgenommen wird
  je Ziel nur, was es auch auswertet — die Zuordnung steht in
  CollectableTypes, nicht hier.

  Jeder Tab erscheint nur mit Recht UND Modul, wie die Sidebar es prüft.
  Lerninhalte fehlen bewusst: sie haben ihre eigene Sektion und sind im
  Einstieg über den Typfilter erreichbar.
--}}
@php
    $tabsUser = auth()->user();
    $tabsTypes = app(\App\Services\Collections\CollectableTypes::class);
    $tabsAvailable = $tabsUser instanceof \App\Models\User ? $tabsTypes->availableKeys($tabsUser) : [];
    $tabsQuery = request()->query();
    $tabsCarry = fn (?string $key): array => $tabsTypes->carry(
        $key === null ? \App\Services\Collections\CollectableTypes::SHARED_FILTERS : $tabsTypes->listFilters($key),
        $tabsQuery,
    );
    $tabsShows = fn (string $key): bool => in_array($key, $tabsAvailable, true) && $tabsTypes->listRoute($key) !== null;
@endphp

@if ($tabsAvailable !== [])
    <x-tab-nav data-knowledge-tabs :items="[
        [
            'route' => 'knowledge-hub.index',
            'params' => $tabsCarry(null),
            'routeIs' => ['knowledge-hub.*', 'collections.*', 'knowledge-imports.*'],
            'icon' => 'hub',
            'label' => __('collections.hub.all_contents'),
        ],
        [
            'when' => $tabsShows('note'),
            'route' => $tabsTypes->listRoute('note'),
            'params' => $tabsCarry('note'),
            'routeIs' => 'communication-notes.*',
            'icon' => 'sticky_note_2',
            'label' => __('communication.title.notes'),
        ],
        [
            'when' => $tabsShows('knowledge_article'),
            'route' => $tabsTypes->listRoute('knowledge_article'),
            'params' => $tabsCarry('knowledge_article'),
            'routeIs' => 'knowledge.*',
            'icon' => 'school',
            'label' => __('knowledge.title.index'),
        ],
        [
            'when' => $tabsShows('idea_map'),
            'route' => $tabsTypes->listRoute('idea_map'),
            'params' => $tabsCarry('idea_map'),
            'routeIs' => 'ideas.*',
            'icon' => 'emoji_objects',
            'label' => __('ideas.title.index'),
        ],
        [
            'when' => $tabsShows('document'),
            'route' => $tabsTypes->listRoute('document'),
            'params' => $tabsCarry('document'),
            'routeIs' => 'documents.*',
            'icon' => 'folder_open',
            'label' => __('document.title.index'),
        ],
    ]" />
@endif
