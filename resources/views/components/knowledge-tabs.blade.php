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

  Jeder Tab erscheint nur mit Recht UND Modul, wie die Sidebar es prüft.
  Lerninhalte fehlen bewusst: sie haben ihre eigene Sektion und sind im
  Einstieg über den Typfilter erreichbar.
--}}
@php
    $tabsUser = auth()->user();
    $tabsTypes = app(\App\Services\Collections\CollectableTypes::class);
    $tabsAvailable = $tabsUser instanceof \App\Models\User ? $tabsTypes->availableKeys($tabsUser) : [];
@endphp

@if ($tabsAvailable !== [])
    <x-tab-nav data-knowledge-tabs :items="[
        [
            'route' => 'knowledge-hub.index',
            'routeIs' => ['knowledge-hub.*', 'collections.*', 'knowledge-imports.*'],
            'icon' => 'hub',
            'label' => __('collections.hub.all_contents'),
        ],
        [
            'when' => in_array('note', $tabsAvailable, true),
            'route' => 'communication-notes.index',
            'routeIs' => 'communication-notes.*',
            'icon' => 'sticky_note_2',
            'label' => __('communication.title.notes'),
        ],
        [
            'when' => in_array('knowledge_article', $tabsAvailable, true),
            'route' => 'knowledge.index',
            'routeIs' => 'knowledge.*',
            'icon' => 'school',
            'label' => __('knowledge.title.index'),
        ],
        [
            'when' => in_array('idea_map', $tabsAvailable, true),
            'route' => 'ideas.index',
            'routeIs' => 'ideas.*',
            'icon' => 'emoji_objects',
            'label' => __('ideas.title.index'),
        ],
        [
            'when' => in_array('document', $tabsAvailable, true),
            'route' => 'documents.index',
            'routeIs' => 'documents.*',
            'icon' => 'folder_open',
            'label' => __('document.title.index'),
        ],
    ]" />
@endif
