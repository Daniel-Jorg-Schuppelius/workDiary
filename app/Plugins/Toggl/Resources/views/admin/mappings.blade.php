{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : mappings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Toggl-Zuordnungen'))
@section('nav-title', __('Toggl-Zuordnungen'))

@php
    // id → Label für die Projekt-Anzeige (Projekt (Kunde)).
    $customerLabel = collect($customers)->keyBy('id')->map(fn($c) => $c['label']);
    // Benutzer-Zuordnungen ($userMappings, vereint aus Referenzen + Aliassen)
    // kommen aus dem Controller; Kunden und Projekte je als eigene Tabelle.
    $clientMappings = $mappings->where('external_type', \App\Plugins\Toggl\TogglImportService::EXT_TYPE_CLIENT)->values();
    $projectMappings = $mappings->where('external_type', \App\Plugins\Toggl\TogglImportService::EXT_TYPE_PROJECT)->values();
@endphp

@section('content')
<x-page-shell>
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Gemerkte Zuordnungen') }}</h1>
            <a href="{{ route('admin.toggl.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück zum Import') }}</a>
        </div>
        <p class="mb-4 text-sm text-base-content/60">
            {{ __('Diese Zuordnungen entscheiden, welchem Kunden bzw. Projekt ein Toggl-Client/-Projekt bei künftigen Importen automatisch zugewiesen wird. Hier kannst du sie umbiegen oder entfernen.') }}
        </p>

        @if (session('status'))
            <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Benutzer-Zuordnung anlegen: Toggl-E-Mail → Benutzer. Für Mitarbeiter,
             deren Toggl-Adresse von der workDiary-Adresse abweicht — greift in
             CSV-/API-Import, Inbox-Buchung und Reparatur-Befehl. --}}
        <form method="POST" action="{{ route('admin.toggl.mappings.store-user') }}"
              class="mb-4 rounded-box bg-base-200/50 p-3">
            @csrf
            <div class="text-sm font-semibold">{{ __('Benutzer-Zuordnung anlegen') }}</div>
            <p class="mb-2 text-xs text-base-content/60">
                {{ __('Toggl-E-Mail einem Benutzer zuordnen — für Mitarbeiter, deren Toggl-Adresse von der workDiary-Adresse abweicht.') }}
            </p>
            <div class="flex flex-wrap items-end gap-2">
                <label class="form-control w-full max-w-xs">
                    <span class="label-text text-xs">{{ __('Toggl-E-Mail') }}</span>
                    @if ($togglEmails !== [])
                        {{-- Bekannte Toggl-Benutzer (API/Inbox) — verhindert Tippfehler. --}}
                        <select name="toggl_email" required class="select select-sm select-bordered w-full">
                            <option value="">{{ __('— wählen —') }}</option>
                            @foreach ($togglEmails as $tu)
                                <option value="{{ $tu['email'] }}">{{ $tu['name'] === $tu['email'] ? $tu['email'] : $tu['name'] . ' (' . $tu['email'] . ')' }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="email" name="toggl_email" required maxlength="191" placeholder="name@firma.de"
                               class="input input-sm input-bordered w-full">
                        @if ($allTogglEmailsMapped)
                            <span class="label-text-alt text-success">{{ __('Alle bekannten Toggl-Adressen sind bereits zugeordnet.') }}</span>
                        @endif
                    @endif
                </label>
                <label class="form-control w-full max-w-xs">
                    <span class="label-text text-xs">{{ __('Benutzer') }}</span>
                    <select name="user" required class="select select-sm select-bordered w-full">
                        <option value="">{{ __('— wählen —') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u['sqid'] }}">{{ $u['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Zuordnen') }}</button>
            </div>
        </form>

        {{-- Bestehende Benutzer-Zuordnungen — eigene Tabelle, nicht in der
             großen Kunden-/Projekt-Tabelle unten versteckt. --}}
        @if ($userMappings->isNotEmpty())
            <div class="mb-4 rounded-box bg-base-200/50 p-3">
                <div class="text-sm font-semibold">{{ __('Benutzer-Zuordnungen') }} ({{ $userMappings->count() }})</div>
                <div class="mt-3">
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('Toggl-E-Mail') }}</th>
                                <th>{{ __('Benutzer') }}</th>
                                <th class="text-right">{{ __('Aktion') }}</th>
                            </tr>
                        </x-slot:head>
                            @foreach ($userMappings as $um)
                                @php
                                    $target = $um->user;
                                    $updateRoute = $um->source === 'alias'
                                        ? route('admin.toggl.mappings.user-alias.update', $um->sqid)
                                        : route('admin.toggl.mappings.update', $um->sqid);
                                    $deleteRoute = $um->source === 'alias'
                                        ? route('admin.toggl.mappings.user-alias.delete', $um->sqid)
                                        : route('admin.toggl.mappings.delete', $um->sqid);
                                @endphp
                                <tr>
                                    <td class="font-mono text-xs">{{ $um->email }}</td>
                                    <td>
                                        @if ($target === null)
                                            <span class="text-error text-xs">{{ __('verwaist (Ziel gelöscht)') }}</span>
                                        @else
                                            {{ $target->name }}
                                            <span class="text-base-content/50">({{ $target->email }})</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ $updateRoute }}" class="flex items-center gap-2">
                                                @csrf
                                                <select name="target_id" required class="select select-sm select-bordered">
                                                    <option value="">{{ __('— wählen —') }}</option>
                                                    @foreach ($users as $u)
                                                        <option value="{{ $u['sqid'] }}" @selected($u['sqid'] === $target?->sqid)>{{ $u['label'] }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-sm">{{ __('Umbiegen') }}</button>
                                            </form>
                                            <form method="POST" action="{{ $deleteRoute }}"
                                                  data-confirm-dialog
                                                  data-confirm-message="{{ __('Diese Zuordnung entfernen? Künftige Importe matchen dann nicht mehr automatisch.') }}">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Entfernen') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                    </x-table>
                </div>
            </div>
        @endif

        {{-- Kunden-Zuordnungen: Toggl-Client → Kunde oder Endkunde --}}
        @if ($clientMappings->isNotEmpty())
            <div class="mb-4 rounded-box bg-base-200/50 p-3">
                <div class="text-sm font-semibold">{{ __('Kunden-Zuordnungen') }} ({{ $clientMappings->count() }})</div>
                <div class="mt-3">
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('Toggl-Client') }}</th>
                                <th>{{ __('Zugeordnet zu') }}</th>
                                <th class="text-right">{{ __('Aktion') }}</th>
                            </tr>
                        </x-slot:head>
                            @foreach ($clientMappings as $mapping)
                                @php
                                    $target = $mapping->referenceable;
                                    $currentSqid = $target?->sqid;
                                    $isForeign = $target instanceof \App\Models\ForeignCustomer;
                                @endphp
                                <tr>
                                    <td class="font-mono text-xs">{{ $mapping->external_id }}</td>
                                    <td>
                                        @if ($target === null)
                                            <span class="text-error text-xs">{{ __('verwaist (Ziel gelöscht)') }}</span>
                                        @elseif ($isForeign)
                                            {{ $target->name }}
                                            <span class="text-base-content/50">({{ $customerLabel[$target->customer_id] ?? '—' }})</span>
                                            <x-status-badge tone="accent" size="sm">{{ __('Endkunde') }}</x-status-badge>
                                        @else
                                            {{ $target->displayLabel() }}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.toggl.mappings.update', $mapping->sqid) }}"
                                                  class="flex items-center gap-2">
                                                @csrf
                                                <select name="target_id" required class="select select-sm select-bordered">
                                                    <option value="">{{ __('— wählen —') }}</option>
                                                    <optgroup label="{{ __('Kunden') }}">
                                                        @foreach ($customers as $c)
                                                            <option value="{{ $c['sqid'] }}" @selected(! $isForeign && $c['sqid'] === $currentSqid)>{{ $c['label'] }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                    @if ($foreignCustomers !== [])
                                                        <optgroup label="{{ __('Endkunden (Fremdkunden)') }}">
                                                            @foreach ($foreignCustomers as $fc)
                                                                <option value="{{ $fc['sqid'] }}" @selected($isForeign && $fc['sqid'] === $currentSqid)>
                                                                    {{ $fc['name'] }} ({{ $customerLabel[$fc['customer_id']] ?? '—' }})
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                </select>
                                                <button type="submit" class="btn btn-sm">{{ __('Umbiegen') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.toggl.mappings.delete', $mapping->sqid) }}"
                                                  data-confirm-dialog
                                                  data-confirm-message="{{ __('Diese Zuordnung entfernen? Künftige Importe matchen dann nicht mehr automatisch.') }}">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Entfernen') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                    </x-table>
                </div>
            </div>
        @endif

        {{-- Projekt-Zuordnungen: Toggl-Schlüssel „client|projekt" → Projekt --}}
        @if ($projectMappings->isNotEmpty())
            <div class="mb-4 rounded-box bg-base-200/50 p-3">
                <div class="text-sm font-semibold">{{ __('Projekt-Zuordnungen') }} ({{ $projectMappings->count() }})</div>
                <div class="mt-3">
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('Toggl-Projekt') }}</th>
                                <th>{{ __('Zugeordnet zu') }}</th>
                                <th class="text-right">{{ __('Aktion') }}</th>
                            </tr>
                        </x-slot:head>
                            @foreach ($projectMappings as $mapping)
                                @php
                                    $target = $mapping->referenceable;
                                    $currentSqid = $target?->sqid;
                                @endphp
                                <tr>
                                    <td class="font-mono text-xs">{{ $mapping->external_id }}</td>
                                    <td>
                                        @if ($target === null)
                                            <span class="text-error text-xs">{{ __('verwaist (Ziel gelöscht)') }}</span>
                                        @else
                                            {{ $target->name }}
                                            <span class="text-base-content/50">({{ $target->customer_id === null ? __('Intern') : ($customerLabel[$target->customer_id] ?? '—') }})</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.toggl.mappings.update', $mapping->sqid) }}"
                                                  class="flex items-center gap-2">
                                                @csrf
                                                <select name="target_id" required class="select select-sm select-bordered">
                                                    <option value="">{{ __('— wählen —') }}</option>
                                                    @foreach ($projects as $p)
                                                        <option value="{{ $p['sqid'] }}" @selected($p['sqid'] === $currentSqid)>
                                                            {{ $p['name'] }} ({{ $p['customer_id'] === null ? __('Intern') : ($customerLabel[$p['customer_id']] ?? '—') }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-sm">{{ __('Umbiegen') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.toggl.mappings.delete', $mapping->sqid) }}"
                                                  data-confirm-dialog
                                                  data-confirm-message="{{ __('Diese Zuordnung entfernen? Künftige Importe matchen dann nicht mehr automatisch.') }}">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Entfernen') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                    </x-table>
                </div>
            </div>
        @endif

        @if ($userMappings->isEmpty() && $clientMappings->isEmpty() && $projectMappings->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">link_off</span>'
                           :title="__('Noch keine Zuordnungen gemerkt.')"
                           :message="__('Zuordnungen entstehen beim Buchen in der Zuordnungs-Inbox, beim Workspace-Import oder oben über die Benutzer-Zuordnung.')" />
        @endif
    </div>
</x-page-shell>
@endsection
