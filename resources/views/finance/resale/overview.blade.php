{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : overview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Überblick des Reselling-Registers (Feature 185, MVP-1414): Aufgabenliste
  statt Abo-Liste — was wartet, mit Zähler und Verweis auf die Fachseite;
  daneben der Stand je Anbieter und der letzte Vorschlagslauf.
  Daten: ResaleOverview::build() ($overview), $summary für die Reiter.
--}}
@extends('layouts.app')
@section('title', __('resale.title.index'))
@section('nav-title', __('resale.title.menu'))
@include('partials.page-fill')

@php
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
    $tasks = array_values(array_filter($overview['tasks'], static fn (array $t): bool => ! $t['manage'] || $canManage));
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('resale.overview.subtitle')">
        <x-slot:actions>
            {{-- Einstieg in die Arbeitsmappen (Phase 165, MVP-1445): der nächste offene Empfänger in Arbeitsreihenfolge. --}}
            <x-icon-btn icon="play_arrow" tone="primary" size="sm" placement="bar" :href="route('finance.resale.reconcile.next')" show-label>{{ __('resale.reconcile.worklist.start') }}</x-icon-btn>
            @if ($canManage)
                <x-icon-btn icon="upload" tone="ghost" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.import.create')"
                            show-label>{{ __('resale.import.action') }}</x-icon-btn>
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger placement="bar"
                            :href="route('finance.resale.create')"
                            show-label>{{ __('resale.action.new') }}</x-icon-btn>
                {{-- Einstellungen (Feature 185): Abrechnung und Produkt-Einstufung als Menü statt Reiter. --}}
                <x-action-menu icon="settings" :label="__('resale.settings.menu')">
                    <x-icon-btn icon="event_repeat" tone="ghost" size="sm" data-entry-modal-trigger :href="route('finance.resale.settings.billing')" show-label>{{ __('resale.auto_draft.dialog_title') }}</x-icon-btn>
                    <x-icon-btn icon="inventory_2" tone="ghost" size="sm" :href="route('finance.resale.products')" show-label>{{ __('resale.products.title') }}</x-icon-btn>
                </x-action-menu>
            @endif
        </x-slot:actions>

        @include('finance.resale._tabs')

        {{-- Vier Kennzahlen (Phase 163, MVP-1433): fällig nach Karenz mit Betrag, Prüffälle, ohne Halter — Vorschläge sind keine Aufgabe mehr. --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <x-kpi-tile :label="__('resale.summary.active')" :value="$overview['summary']['active']" :href="route('finance.resale.subscriptions')" />
            <x-kpi-tile :label="__('resale.overview.kpi.open')" :value="$overview['summary']['open']" :tone="$overview['summary']['open'] > 0 ? 'error' : 'success'"
                        :hint="($overview['summary']['open_amount'] !== '' ? $overview['summary']['open_amount'] . ' · ' : '') . __('resale.overview.kpi.open_hint', ['days' => \App\Services\Reselling\Register\ResaleOverview::GRACE_DAYS])"
                        :href="route('finance.resale.periods.index', ['status' => 'problems'])" />
            <x-kpi-tile :label="__('resale.account.summary.conflicts')" :value="$overview['summary']['findings']" :tone="$overview['summary']['findings'] > 0 ? 'warning' : 'neutral'"
                        :href="route('finance.resale.accounts.index')" />
            <x-kpi-tile :label="__('resale.summary.unassigned')" :value="$overview['summary']['unassigned']" :tone="$overview['summary']['unassigned'] > 0 ? 'warning' : 'neutral'"
                        :href="route('finance.resale.subscriptions', ['unassigned' => 1])" />
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 min-h-0 flex-1 overflow-y-auto">
            <x-card :title="__('resale.overview.tasks')" icon="checklist" :count="count($tasks) ?: null" padding="p-0" class="xl:col-span-2 self-start">
                @if ($tasks === [])
                    <x-empty-state compact icon="task_alt" :title="__('resale.overview.all_clear')" :message="__('resale.overview.all_clear_hint')" />
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th class="text-right w-20">{{ __('resale.overview.col.count') }}</x-table.th>
                                <x-table.th>{{ __('resale.overview.col.task') }}</x-table.th>
                                <x-table.th class="text-right"></x-table.th>
                            </tr>
                        </x-slot:head>
                        @php $grouped = []; foreach ($tasks as $t) { $grouped[$t['group']][] = $t; } @endphp
                        @foreach ($overview['groups'] as $group)
                            @continue(empty($grouped[$group]))
                            <tr class="bg-base-200/40"><td colspan="3" class="py-1.5 text-xs uppercase tracking-wider text-muted">{{ __('resale.overview.group.' . $group) }}</td></tr>
                        @foreach ($grouped[$group] as $task)
                            <tr class="hover">
                                <td class="text-right align-top"><x-status-badge :tone="$task['tone']" size="sm">{{ $task['count'] }}</x-status-badge></td>
                                <td>
                                    <a href="{{ $task['url'] }}" class="link link-hover font-medium" @if ($task['dialog']) data-entry-modal-trigger @endif>
                                        <x-icon :name="$task['icon']" size="1.1rem" /> {{ $task['title'] }}
                                    </a>
                                    @if ($task['detail'] !== null)
                                        <div class="text-xs text-muted">{{ $task['detail'] }}</div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($task['dialog'])
                                        <x-icon-btn icon="arrow_forward" size="xs" tone="ghost" :href="$task['url']" :title="$task['title']" data-entry-modal-trigger />
                                    @else
                                        <x-icon-btn icon="arrow_forward" size="xs" tone="ghost" :href="$task['url']" :title="$task['title']" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @endforeach
                    </x-table>
                @endif
            </x-card>

            <x-card :title="__('resale.overview.state.title')" icon="history" padding="p-0" class="self-start">
                <div class="px-4 py-3 border-b border-base-300 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-muted">{{ __('resale.overview.state.last_run') }}</div>
                        <div class="tabular-nums">{{ $overview['last_run'] !== null ? \App\Support\Tz::toLocal($overview['last_run'])?->fdatetime() : __('resale.overview.state.never') }}</div>
                    </div>
                    @if ($canManage)
                        <form method="POST" action="{{ route('finance.resale.periods.propose') }}">
                            @csrf
                            <x-icon-btn icon="auto_awesome" tone="ghost" size="sm" type="submit" show-label>{{ __('resale.link.action.propose') }}</x-icon-btn>
                        </form>
                    @endif
                </div>
                {{-- Was keine Aufgabe ist, aber den Stand erklärt (Phase 163): Vorschläge als Stichprobe, geplante und noch nicht fällige Perioden. --}}
                <div class="px-4 py-2 border-b border-base-300 text-xs space-y-0.5">
                    <div class="flex justify-between gap-2"><a href="{{ route('finance.resale.periods.index', ['status' => 'proposed']) }}" class="link link-hover">{{ __('resale.overview.state.proposed') }}</a><span class="tabular-nums">{{ $overview['summary']['proposed'] }}</span></div>
                    <div class="flex justify-between gap-2"><span>{{ __('resale.overview.state.planned') }}</span><span class="tabular-nums">{{ $overview['summary']['planned'] }}</span></div>
                    <div class="flex justify-between gap-2"><span>{{ __('resale.overview.state.grace', ['days' => \App\Services\Reselling\Register\ResaleOverview::GRACE_DAYS]) }}</span><span class="tabular-nums">{{ $overview['summary']['grace'] }}</span></div>
                </div>
                <div class="px-4 py-2 text-xs uppercase tracking-wider text-muted">{{ __('resale.overview.state.imports') }}</div>
                @forelse ($overview['imports'] as $state)
                    <div class="px-4 py-2 border-t border-base-300 flex flex-wrap items-start justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $state['provider']->label() }}</div>
                            @if ($state['last'] !== null)
                                <div class="text-xs text-muted truncate">{{ $state['last']->created_at->fdate() }} · {{ $state['last']->file_name }}@if ($state['last']->creator !== null) · {{ $state['last']->creator->name }}@endif</div>
                            @else
                                {{-- raw-markup-ok: Zeile im Stand je Anbieter („kein Import“), kein Leerzustand der Karte --}}
                                <div class="text-xs text-muted">{{ __('resale.overview.state.none') }}</div>
                            @endif
                        </div>
                        @if ($state['age'] !== null)
                            <x-status-badge size="xs" :tone="$state['stale'] ? 'warning' : 'success'" :label="__('resale.overview.state.age', ['days' => $state['age']])" />
                        @endif
                    </div>
                @empty
                    <x-empty-state compact icon="upload" :title="__('resale.overview.state.empty')" />
                @endforelse
            </x-card>
        </div>
    </x-index-page>
@endsection
