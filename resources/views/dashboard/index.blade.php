{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel-Dashboard: der Kopf ist die Standard-Toolbar (x-page-toolbar im
  toolbar-Slot, also stehendes Panel wie auf allen Seiten), alles darunter
  kommt aus der Widget-Registry. Was gezeigt wird, entscheidet der Nutzer
  unter „Dashboard anpassen" (dashboard.customize); die Organisation kann
  eine Vorgabe setzen.

  Bereiche (Tabs) sind optional: ohne angelegte Bereiche rendert die Seite
  eine einzige Kachelfläche — Aufteilung ist Wahl, nicht Vorgabe. Kacheln
  ohne Bereich stehen über der Leiste und sind in jedem Bereich sichtbar.
--}}
@extends('layouts.app')
@section('title', __('Dashboard') . ' — WorkDiary')
@section('nav-title', __('Dashboard'))

@section('content')
    @php
        /** @var \Carbon\CarbonImmutable $now */
        /** @var \Illuminate\Support\Collection<int, \App\Support\Dashboard\DashboardLayoutItem> $tiles */
        /** @var list<array{key:string,label:string,icon:?string}> $tabs */
        /** @var \Illuminate\Support\Collection<int, \App\Support\Dashboard\DashboardLayoutItem> $always */
        /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Support\Dashboard\DashboardLayoutItem>>|null $grouped */
        $dashboardUser = Auth::user();
    @endphp

    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Hallo') . ', ' . $dashboardUser->name . ' · ' . $now->translatedFormat('l, d.m.Y H:i')">
                <x-slot:actions>
                    <x-icon-btn icon="calendar_view_week" size="sm" :href="route('week.index')" show-label>{{ __('Wochenansicht') }}</x-icon-btn>
                    <x-icon-btn icon="menu_book" size="sm" :href="route('diary.index')" show-label>{{ __('Auftragsbuch') }}</x-icon-btn>
                    <x-icon-btn icon="tune" size="sm" :href="route('dashboard.customize')" show-label>{{ __('Anpassen') }}</x-icon-btn>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('diary.create')"
                                show-label>{{ __('Neuer Eintrag') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if ($tiles->isEmpty())
            <x-empty-state framed icon="dashboard_customize"
                           :title="__('Keine Kacheln ausgewählt')"
                           :message="__('Alle Kacheln sind ausgeblendet. Unter „Anpassen“ lassen sie sich wieder einblenden.')">
                <x-slot:action>
                    <x-button href="{{ route('dashboard.customize') }}" tone="primary" size="sm" icon="tune">{{ __('Dashboard anpassen') }}</x-button>
                </x-slot:action>
            </x-empty-state>
        @elseif ($grouped !== null)
            {{-- Kacheln ohne Bereich: stehen über der Leiste, also in jedem Bereich. --}}
            @if ($always->isNotEmpty())
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($always as $tile)
                        <div class="{{ $tile->width->columnClass() }} [&>*]:h-full">
                            {{ $tile->widget->render($dashboardUser) }}
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Bereichsleiste: Auswahl bleibt je Gerät erhalten (data-tab-persist). --}}
            <div x-data="tabs('{{ $tabs[0]['key'] }}')"
                 data-tab-persist="wd-dash-tab"
                 data-tab-allowed="{{ implode(',', array_column($tabs, 'key')) }}"
                 class="space-y-4">
                <div role="tablist" class="tabs tabs-box w-full flex-nowrap overflow-x-auto">
                    @foreach ($tabs as $tab)
                        <button type="button" role="tab"
                                class="tab gap-1.5 whitespace-nowrap"
                                :class="tabClass('{{ $tab['key'] }}')"
                                @click="setTab('{{ $tab['key'] }}')">
                            @if ($tab['icon'])
                                <x-icon :name="$tab['icon']" />
                            @endif
                            <span>{{ $tab['label'] }}</span>
                            <span class="badge badge-ghost badge-sm tabular-nums">{{ $grouped->get($tab['key'], collect())->count() }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach ($tabs as $tab)
                    <div x-show="isTab('{{ $tab['key'] }}')" x-cloak>
                        @php $tabTiles = $grouped->get($tab['key'], collect()); @endphp
                        @if ($tabTiles->isEmpty())
                            <x-empty-state framed icon="dashboard_customize"
                                           :title="__('Bereich ohne Kacheln')"
                                           :message="__('Diesem Bereich ist noch keine Kachel zugeordnet.')" />
                        @else
                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach ($tabTiles as $tile)
                                    <div class="{{ $tile->width->columnClass() }} [&>*]:h-full">
                                        {{ $tile->widget->render($dashboardUser) }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($tiles as $tile)
                    {{-- [&>*]:h-full: Grid-Items sind gleich hoch, die Kachel darin soll mitziehen. --}}
                    <div class="{{ $tile->width->columnClass() }} [&>*]:h-full">
                        {{ $tile->widget->render($dashboardUser) }}
                    </div>
                @endforeach
            </div>
        @endif
    </x-page-shell>
@endsection
