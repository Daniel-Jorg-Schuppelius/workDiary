@extends('layouts.app')

@section('title', __('Organisationen'))
@section('nav-title', __('Organisationen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
@php
    /** @var \App\Services\OrganizationLifecycleService $orgLifecycle */
    $orgLifecycle = app(\App\Services\OrganizationLifecycleService::class);
    $cooldownHours = $orgLifecycle->cooldownHours();
@endphp
<x-index-page overflow="clip" :subtitle="__('Mandanten der Plattform verwalten und konfigurieren.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.organizations.create')"
                    show-label>{{ __('Organisation anlegen') }}</x-icon-btn>
    </x-slot:actions>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('admin.organizations.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'asc'">
        <x-slot:head>
            <tr>
                <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                <th>{{ __('Slug') }}</th>
                <x-table.th sort="plan">{{ __('Plan') }}</x-table.th>
                <x-table.th sort="users" align="center">{{ __('Benutzer') }}</x-table.th>
                <x-table.th sort="is_active" align="center">{{ __('Aktiv') }}</x-table.th>
                <th>{{ __('Erstellt') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
            @forelse ($organizations as $org)
                @php
                    $canPurge = $orgLifecycle->isPurgeAllowed($org);
                    $deactivatedAt = $org->deactivated_at;
                @endphp
                <tr>
                    <td class="font-medium">
                        {{ $org->name }}
                        @if (! $org->is_active && $deactivatedAt)
                            <div class="text-xs text-base-content/50">
                                {{ __('Deaktiviert am :date', ['date' => $deactivatedAt->fdatetime()]) }}
                            </div>
                        @endif
                    </td>
                    <td class="font-mono text-sm text-base-content/60">{{ $org->slug }}</td>
                    <td>
                        <x-status-badge size="sm" :tone="$org->plan === 'enterprise' ? 'primary' : ($org->plan === 'pro' ? 'secondary' : 'ghost')">
                            {{ \App\Models\Organization::planLabel($org->plan) }}
                        </x-status-badge>
                    </td>
                    <td class="text-center">{{ $org->users_count }}</td>
                    <td class="text-center">
                        @if ($org->is_active)
                            <x-status-badge tone="success" size="sm">{{ __('Ja') }}</x-status-badge>
                        @else
                            <x-status-badge tone="error" size="sm">{{ __('Nein') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-sm text-base-content/60">{{ $org->created_at?->toDateString() }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('admin.organizations.edit', $org)"
                                        :label="__('Bearbeiten')" />

                            {{-- Daten-Export (DSGVO Art. 20): liefert ZIP --}}
                            <x-action-form :action="route('admin.organizations.export', $org)">
                                <x-icon-btn icon="download" type="submit"
                                            :label="__('Daten exportieren (ZIP)')" />
                            </x-action-form>

                            @if ($org->is_active)
                                {{-- Deaktivieren (reversibel) --}}
                                @php $deactivateConfirm = __('Organisation ":name" deaktivieren? Sie verschwindet aus dem Org-Switcher und kann nicht mehr als aktiver Kontext gewählt werden, bis sie reaktiviert wird.', ['name' => $org->name]); @endphp
                                <x-action-form :action="route('admin.organizations.deactivate', $org)"
                                      :confirm="$deactivateConfirm"
                                      :confirm-label="__('Deaktivieren')">
                                    <x-icon-btn icon="block" tone="warning" type="submit" :label="__('Deaktivieren')" />
                                </x-action-form>
                            @else
                                {{-- Reaktivieren --}}
                                <x-action-form :action="route('admin.organizations.reactivate', $org)">
                                    <x-icon-btn icon="check_circle" tone="success" type="submit" :label="__('Reaktivieren')" />
                                </x-action-form>

                                {{-- Endgültig löschen (Purge) — nur nach Cooldown --}}
                                @if ($canPurge)
                                    <button type="button"
                                            class="btn btn-sm btn-ghost text-error"
                                            title="{{ __('Endgültig löschen') }}"
                                            aria-label="{{ __('Endgültig löschen') }}"
                                            onclick="document.getElementById('purge-modal-{{ $org->id }}').showModal()">
                                        <x-icon name="delete_forever" />
                                    </button>

                                    <dialog id="purge-modal-{{ $org->id }}" class="modal">
                                        <div class="modal-box wd-modal-box wd-modal-box--standard">
                                            <h3 class="font-bold text-lg text-error">
                                                {{ __('Organisation endgültig löschen') }}
                                            </h3>
                                            <div class="py-3 space-y-2 text-sm">
                                                <p>
                                                    {{ __('Sie sind im Begriff, die Organisation ":name" und ALLE zugehörigen Datensätze und Dateien unwiderruflich zu löschen.', ['name' => $org->name]) }}
                                                </p>
                                                <p class="text-warning">
                                                    {{ __('Diese Aktion kann nicht rückgängig gemacht werden. Erzeugen Sie vorher einen Daten-Export, falls der Kunde die Daten mitnehmen möchte.') }}
                                                </p>
                                                <p>
                                                    {{ __('Zur Bestätigung geben Sie bitte den Slug der Organisation ein:') }}
                                                    <code class="bg-base-200 px-1.5 py-0.5 rounded text-xs">{{ $org->slug }}</code>
                                                </p>
                                            </div>
                                            <form method="POST" action="{{ route('admin.organizations.purge', $org) }}">
                                                @csrf @method('DELETE')
                                                <input type="text"
                                                       name="confirm_slug"
                                                       class="input input-bordered w-full font-mono"
                                                       autocomplete="off"
                                                       required
                                                       placeholder="{{ $org->slug }}">
                                                <div class="modal-action">
                                                    <x-button type="button" tone="ghost" size="md"
                                                            onclick="document.getElementById('purge-modal-{{ $org->id }}').close()">
                                                        {{ __('Abbrechen') }}
                                                    </x-button>
                                                    <x-button type="submit" tone="error" size="md" icon="delete_forever">{{ __('Endgültig löschen') }}</x-button>
                                                </div>
                                            </form>
                                        </div>
                                        <form method="dialog" class="modal-backdrop"><button>close</button></form>
                                    </dialog>
                                @else
                                    <span class="text-xs text-base-content/50 self-center"
                                          title="{{ __('Endgültiges Löschen erst :h Stunden nach Deaktivierung möglich.', ['h' => $cooldownHours]) }}">
                                        <x-icon name="hourglass_top" class="text-base-content/40" />
                                    </span>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">apartment</span>' :colspan="7" :title="__('Keine Organisationen vorhanden')" compact />
            @endforelse
    </x-table>

    <x-pagination :paginator="$organizations" />
</x-index-page>
@endsection
