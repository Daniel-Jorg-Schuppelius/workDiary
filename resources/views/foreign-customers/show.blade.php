{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $foreignCustomer->name . ' — ' . __('Fremdkunde'))
@section('nav-title', __('Fremdkunde'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        {{-- Kopf: Standard-Toolbar; Farbpunkt und Kunden-Link bleiben als
             Slot-Inhalt (die Farbe spiegelt die Zuordnung im Tagebuch). --}}
        <x-page-toolbar :title="$foreignCustomer->name"
                        :badge="$foreignCustomer->isArchived() ? __('archiviert') : null"
                        badge-tone="ghost">
            <span class="inline-flex items-center gap-2">
                <span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background:{{ $foreignCustomer->color ?: '#94a3b8' }}"></span>
                {{ __('Endkunde von') }}
                <a class="link" href="{{ route('customers.show', $foreignCustomer->customer) }}">{{ $foreignCustomer->customer->displayLabel() }}</a>
            </span>
            <x-slot:actions>
                    @can('update', $foreignCustomer)
                        <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                                    :href="route('foreign-customers.edit', $foreignCustomer)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @endcan
                    @can('promote', $foreignCustomer)
                        <x-action-form :action="route('foreign-customers.promote', $foreignCustomer)"
                              :confirm="__('Diesen Fremdkunden zu einem eigenständigen Kunden befördern? Alle zugeordneten Projekte werden auf den neuen Kunden umgehängt.')"
                              :confirm-label="__('Befördern')">
                            <x-icon-btn icon="upgrade" type="submit" size="sm" tone="primary" show-label>{{ __('Zu Kunde befördern') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                    @can('archive', $foreignCustomer)
                        @if ($foreignCustomer->isArchived())
                            <form method="POST" action="{{ route('foreign-customers.restore', $foreignCustomer) }}">
                                @csrf
                                <x-icon-btn icon="unarchive" type="submit" size="sm" show-label>{{ __('Wiederherstellen') }}</x-icon-btn>
                            </form>
                        @else
                            <form method="POST" action="{{ route('foreign-customers.archive', $foreignCustomer) }}">
                                @csrf
                                <x-icon-btn icon="archive" type="submit" size="sm" show-label>{{ __('Archivieren') }}</x-icon-btn>
                            </form>
                        @endif
                    @endcan
            </x-slot:actions>
        </x-page-toolbar>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @if ($foreignCustomer->company)<div><span class="text-base-content/50">{{ __('Firma') }}:</span> {{ $foreignCustomer->company }}</div>@endif
                @if ($foreignCustomer->contact_name)<div><span class="text-base-content/50">{{ __('Ansprechpartner') }}:</span> {{ $foreignCustomer->contact_name }}</div>@endif
                @if ($foreignCustomer->email)<div><span class="text-base-content/50">{{ __('E-Mail') }}:</span> {{ $foreignCustomer->email }}</div>@endif
                @if ($foreignCustomer->phone)<div><span class="text-base-content/50">{{ __('Telefon') }}:</span> {{ $foreignCustomer->phone }}</div>@endif
                @if ($foreignCustomer->address)<div class="sm:col-span-2"><span class="text-base-content/50">{{ __('Adresse') }}:</span> {{ $foreignCustomer->address }}</div>@endif
                @if ($foreignCustomer->comment)<div class="sm:col-span-2"><span class="text-base-content/50">{{ __('Notiz') }}:</span> {{ $foreignCustomer->comment }}</div>@endif
            </div>
        </div>

        {{-- Projekte — gleiche Darstellung wie auf der Kunden-Detailseite --}}
        <x-card :title="__('Projekte')" icon="folder" :count="$projects->count()">
            @if ($projects->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">folder_off</span>'
                               :title="__('Keine Projekte')"
                               :message="__('Diesem Fremdkunden sind noch keine Projekte zugeordnet.')" />
            @else
                @include('customers._project_list_items', ['items' => $projects])
            @endif
        </x-card>

        {{-- Änderungsverlauf (Audit) — Standard-Darstellung wie Kunden/Lieferanten --}}
        @if ($auditLogs->isNotEmpty())
            <x-card :title="__('Änderungsverlauf')" icon="history">
                <x-audit-log-list :logs="$auditLogs" />
            </x-card>
        @endif
    </div>
</x-page-shell>
@endsection
