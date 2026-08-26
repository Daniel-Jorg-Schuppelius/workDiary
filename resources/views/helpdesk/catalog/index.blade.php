{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Servicekatalog (Feature 065, MVP-154): drei Ebenen gruppiert
     (Fachdienst → Angebot → Katalogeintrag), je Ebene Modal-CRUD. --}}

@extends('layouts.app')
@section('title', __('Servicekatalog'))
@section('nav-title', __('Servicekatalog'))

@section('content')
    <x-index-page :subtitle="__('Bestellbare Leistungen mit Formular, Genehmigungskette und Fulfillment — Änderungen wirken nur auf neue Bestellungen (Snapshots).')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('servicedesk.catalog.services.create')"
                            show-label>{{ __('Neuer Fachdienst') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        @forelse ($services as $service)
            <x-card padding="p-4">
                <div class="flex items-center gap-2">
                    <x-icon name="category" class="text-primary" />
                    <span class="text-lg font-semibold">{{ $service->name }}</span>
                    @unless ($service->active)
                        <x-status-badge tone="ghost" size="xs">{{ __('Inaktiv') }}</x-status-badge>
                    @endunless
                    <span class="flex-1"></span>
                    @if ($canManage)
                        <x-icon-btn icon="add" tone="ghost" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('servicedesk.catalog.offerings.create', ['service' => $service->sqid])"
                                    show-label>{{ __('Neues Angebot') }}</x-icon-btn>
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('servicedesk.catalog.services.edit', $service)"
                                    :label="__('Bearbeiten')" />
                        @if ($service->offerings->isEmpty())
                            <x-action-form :action="route('servicedesk.catalog.services.destroy', $service)"
                                  method="DELETE"
                                  data-confirm-title="{{ __('Fachdienst löschen') }}"
                                  :confirm="__('Der Fachdienst wird entfernt.')"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        @endif
                    @endif
                </div>
                @if ($service->description)
                    <p class="mt-1 text-sm text-muted">{{ $service->description }}</p>
                @endif

                @forelse ($service->offerings as $offering)
                    <div class="mt-3 rounded-box border border-base-300 bg-base-200/40 p-3">
                        <div class="flex items-center gap-2">
                            <x-icon name="widgets" class="text-muted" />
                            <span class="font-medium">{{ $offering->name }}</span>
                            @unless ($offering->active)
                                <x-status-badge tone="ghost" size="xs">{{ __('Inaktiv') }}</x-status-badge>
                            @endunless
                            <span class="flex-1"></span>
                            @if ($canManage)
                                <x-icon-btn icon="add" tone="ghost" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('servicedesk.catalog.items.create', ['offering' => $offering->sqid])"
                                            show-label>{{ __('Neuer Katalogeintrag') }}</x-icon-btn>
                                <x-icon-btn icon="edit" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('servicedesk.catalog.offerings.edit', $offering)"
                                            :label="__('Bearbeiten')" />
                                @if ($offering->requestItems->isEmpty())
                                    <x-action-form :action="route('servicedesk.catalog.offerings.destroy', $offering)"
                                          method="DELETE"
                                          data-confirm-title="{{ __('Serviceangebot löschen') }}"
                                          :confirm="__('Das Angebot wird entfernt.')"
                                          :confirm-label="__('Löschen')">
                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('Löschen')" />
                                    </x-action-form>
                                @endif
                            @endif
                        </div>

                        @php($fulfillmentLabels = [
                            'task' => __('Aufgabe'),
                            'project' => __('Projekt'),
                            'diary' => __('Auftragsbuch-Eintrag'),
                            'procedure' => __('Verfahrenslauf'),
                        ])
                        @if ($offering->requestItems->isNotEmpty())
                            <x-table :zebra="true" class="mt-2">
                                <x-slot:head>
                                    <tr>
                                        <th>{{ __('Katalogeintrag') }}</th>
                                        <th>{{ __('Formular') }}</th>
                                        <th>{{ __('Genehmigung') }}</th>
                                        <th>{{ __('Fulfillment') }}</th>
                                        <th>{{ __('SLA') }}</th>
                                        <th>{{ __('Sichtbarkeit') }}</th>
                                        <th class="text-right">{{ __('Version') }}</th>
                                        <th class="w-24 text-right">{{ __('Aktion') }}</th>
                                    </tr>
                                </x-slot:head>
                                <tbody>
                                    @foreach ($offering->requestItems as $item)
                                        <tr class="hover">
                                            <td class="font-medium">
                                                {{ $item->name }}
                                                @unless ($item->active)
                                                    <x-status-badge tone="ghost" size="xs">{{ __('Inaktiv') }}</x-status-badge>
                                                @endunless
                                            </td>
                                            <td class="text-sm text-muted">{{ $item->formTemplate?->name ?? '—' }}</td>
                                            @php($stepCount = count((array) ($item->approval_chain ?? [])))
                                            <td class="text-sm tabular-nums">
                                                {{ $stepCount === 0 ? __('Keine') : __(':n Schritt(e)', ['n' => $stepCount]) }}
                                            </td>
                                            <td class="text-sm">{{ $fulfillmentLabels[$item->fulfillment] ?? $item->fulfillment }}</td>
                                            <td class="text-sm text-muted">{{ $item->slaContract?->label ?? '—' }}</td>
                                            <td class="text-sm">
                                                @php($visibility = (array) ($item->visibility ?? []))
                                                @if ((bool) ($visibility['portal'] ?? false))
                                                    <x-status-badge tone="info" size="xs">{{ __('Kundenportal') }}</x-status-badge>
                                                @endif
                                                @if ((array) ($visibility['roles'] ?? []) !== [])
                                                    <x-status-badge tone="ghost" size="xs">{{ __('Rollenbeschränkt') }}</x-status-badge>
                                                @endif
                                                @if ((array) ($visibility['customer_ids'] ?? []) !== [])
                                                    <x-status-badge tone="ghost" size="xs">{{ __('Kundenbeschränkt') }}</x-status-badge>
                                                @endif
                                                @if ($visibility === [])
                                                    <span class="text-muted">{{ __('Intern') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right tabular-nums">v{{ $item->version }}</td>
                                            <td class="text-right whitespace-nowrap">
                                                @if ($canManage)
                                                    <x-icon-btn icon="edit" size="xs"
                                                                data-entry-modal-trigger
                                                                :href="route('servicedesk.catalog.items.edit', $item)"
                                                                :label="__('Bearbeiten')" />
                                                    <x-action-form :action="route('servicedesk.catalog.items.destroy', $item)"
                                                          method="DELETE"
                                                          data-confirm-title="{{ __('Katalogeintrag löschen') }}"
                                                          :confirm="__('Der Katalogeintrag wird entfernt.')"
                                                          :confirm-label="__('Löschen')">
                                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('Löschen')" />
                                                    </x-action-form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-table>
                        @else
                            <p class="mt-2 text-sm text-muted">{{ __('Noch keine Katalogeinträge in diesem Angebot.') }}</p>
                        @endif
                    </div>
                @empty
                    <p class="mt-3 text-sm text-muted">{{ __('Noch keine Angebote in diesem Fachdienst.') }}</p>
                @endforelse
            </x-card>
        @empty
            <x-card>
                <div class="py-8 text-center text-muted">
                    <x-icon name="storefront" class="text-3xl" />
                    <p class="mt-2 font-medium">{{ __('Noch kein Servicekatalog angelegt') }}</p>
                    <p class="text-sm">{{ __('Lege zuerst einen Fachdienst an, dann Angebote und bestellbare Katalogeinträge.') }}</p>
                </div>
            </x-card>
        @endforelse
    </x-index-page>
@endsection
