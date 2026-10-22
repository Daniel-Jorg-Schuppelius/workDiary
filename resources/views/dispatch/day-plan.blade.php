{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : day-plan.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Tagesvorschlag'))
@section('nav-title', __('Tagesvorschlag'))

@section('content')
    <x-index-page :subtitle="__('Alle offenen Aufträge eines Tages in einem Lauf auf Fahrer verteilen. Der Vorschlag wird erst mit der Übernahme zu Touren.')">
        <x-slot:actions>
            <x-icon-btn icon="dashboard" tone="ghost" size="sm" :href="route('dispatch.board')" show-label>{{ __('Leitstelle') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('dispatch.day-plan')" :reset="route('dispatch.day-plan')">
            <x-filter-field :label="__('Datum')" for="day-plan-date">
                <input id="day-plan-date" type="date" name="date" class="input input-bordered input-sm" required
                       value="{{ old('date', $date->toDateString()) }}" data-autosubmit>
            </x-filter-field>
        </x-filter-bar>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card as="form" class="space-y-4" method="POST" action="{{ route('dispatch.day-plan.store') }}">
                @csrf
                <input type="hidden" name="date" value="{{ old('date', $date->toDateString()) }}">
                <x-form-group :legend="__('Fahrer')" icon="route" tone="primary" cols="1">
                    <x-user-checklist name="drivers" :users="$users" :selected="$preselected"
                                      :placeholder="__('Mitarbeiter suchen…')"
                                      :empty-text="__('Keine Mitarbeiter in dieser Organisation.')" />
                    @error('drivers')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </x-form-group>
                <p class="text-sm text-muted">
                    {{ trans_choice(':count Auftrag kommt für diesen Tag in Frage.|:count Aufträge kommen für diesen Tag in Frage.', $candidates->count(), ['count' => $candidates->count()]) }}
                    {{ __('Berücksichtigt werden Arbeitsfenster, Zeitfenster, Pausen und Qualifikationen; Fahrer starten und enden zu Hause.') }}
                </p>
                <div class="flex justify-end">
                    <x-icon-btn icon="auto_awesome" tone="primary" size="sm" type="submit" show-label>{{ __('Tagesvorschlag berechnen') }}</x-icon-btn>
                </div>
            </x-card>

            <div class="space-y-4">
                <x-card padding="p-0">
                    <div class="border-b border-base-300 px-4 py-2 text-sm font-medium">{{ __('Aufträge des Tages') }}</div>
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('Auftrag') }}</th>
                                <th>{{ __('Kunde') }}</th>
                                <th>{{ __('Modus') }}</th>
                                <th>{{ __('Zeitfenster') }}</th>
                                <th class="text-right">{{ __('Dauer') }}</th>
                            </tr>
                        </x-slot:head>
                        @forelse ($candidates as $entry)
                            <tr>
                                <td><x-order-link :entry="$entry" link-class="link">{{ $entry->title }}</x-order-link></td>
                                <td>{{ $entry->customer?->name }}</td>
                                <td><x-status-badge size="xs" outline>{{ $entry->modeLabel() }}</x-status-badge></td>
                                <td class="text-xs text-muted">{{ $entry->time_window_start ? substr((string) $entry->time_window_start, 0, 5) : '—' }}–{{ $entry->time_window_end ? substr((string) $entry->time_window_end, 0, 5) : '—' }}</td>
                                <td class="text-right">{{ $entry->service_minutes ? \App\Support\Formats::duration((int) $entry->service_minutes, 'clock') : '—' }}</td>
                            </tr>
                        @empty
                            <x-table.empty icon="assignment" :colspan="5" :title="__('Keine offenen Aufträge mit Koordinaten für diesen Tag.')" compact />
                        @endforelse
                    </x-table>
                </x-card>

                <x-card>
                    <h2 class="mb-2 text-sm font-medium">{{ __('Vorschläge dieses Tages') }}</h2>
                    @if ($recent->isEmpty())
                        <x-empty-state icon="auto_awesome" :title="__('Noch kein Vorschlag berechnet.')" compact />
                    @else
                        <ul class="space-y-1 text-sm">
                            @foreach ($recent as $proposal)
                                <li class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('tour-proposals.show', $proposal) }}" class="link">{{ $proposal->created_at?->fdatetime() }}</a>
                                    <x-status-badge :tone="$proposal->status->tone()" size="xs">{{ $proposal->status->label() }}</x-status-badge>
                                    @if (isset($proposal->metrics['after']['tours']))
                                        <span class="text-xs text-muted">{{ $proposal->metrics['after']['tours'] }} {{ __('Touren') }} · {{ $proposal->metrics['after']['stops'] ?? 0 }} {{ __('Stopps') }} · {{ $proposal->metrics['after']['unassigned'] ?? 0 }} {{ __('offen') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>
        </div>
    </x-index-page>
@endsection
