@extends('layouts.app')
@section('title', __('Mitarbeiter'))
@section('nav-title', __('Mitarbeiter'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('Mitarbeiter des Mandanten verwalten.')">
    <x-slot:actions>
        @if ($canManageMembers ?? true)
            {{-- Personalstamm-CSV-Import (Feature 103, MVP-537) --}}
            <x-icon-btn icon="upload_file" tone="outline" size="sm"
                        data-entry-modal-trigger
                        :href="route('org.members.import.form')"
                        show-label>{{ __('Importieren') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('org.members.create')"
                        show-label>{{ __('Mitarbeiter anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($members->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">group</span>'
            :title="__('Noch keine Mitarbeiter')"
            :message="__('Lege das erste Teammitglied an.')"
        />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('org.members.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'asc'">
            <x-slot:head>
                <tr>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort="personnel_number">{{ __('Personalnr.') }}</x-table.th>
                    <x-table.th sort="email">{{ __('E-Mail') }}</x-table.th>
                    <th>{{ __('Rolle') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
                @foreach ($members as $member)
                    <tr>
                        <td class="font-medium">{{ $member->name }}</td>
                        <td class="text-sm text-base-content/70">
                            @if ($member->personnel_number)
                                {{ $member->personnel_number }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td class="text-sm text-base-content/70">{{ $member->email }}</td>
                        <td>
                            @foreach ($member->roles as $role)
                                <x-status-badge size="sm" outline>{{ $role->name }}</x-status-badge>
                            @endforeach
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @can('viewAny', [\App\Models\FlexEligibility::class, $member])
                                    <x-icon-btn icon="schedule"
                                                :href="route('users.flex-eligibility.index', $member)"
                                                :label="__('flex.eligibility.nav_title')" />
                                @endcan
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('org.members.edit', $member)"
                                            :label="__('Bearbeiten')" />
                                {{-- Support-Impersonation (Rang 64): nur mit user.impersonate;
                                     der Server verlangt zusätzlich eine aktive Supportfreigabe. --}}
                                @if (Gate::allows(\App\Enums\User\Permission::UserImpersonate->value) && ! session()->has(\App\Http\Controllers\Admin\SupportImpersonationController::SESSION_KEY) && $member->id !== auth()->id())
                                    <x-action-form :action="route('admin.support.impersonate.start', $member)" method="POST"
                                          :confirm="__('Support-Sitzung als :name starten? Alle Aktionen werden auditiert.', ['name' => $member->name])"
                                          :confirm-label="__('Starten')">
                                        <x-icon-btn icon="switch_account" tone="warning" type="submit" :label="__('Als Nutzer anmelden (Support)')" />
                                    </x-action-form>
                                @endif
                                @if ($canManageMembers ?? true)
                                    <x-action-form :action="route('org.members.destroy', $member)" method="DELETE"
                                          :confirm="__('Mitarbeiter wirklich entfernen?')"
                                          :confirm-label="__('Entfernen')">
                                        <x-icon-btn icon="person_remove" tone="error" type="submit" :label="__('Entfernen')" />
                                    </x-action-form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
        </x-table>
        <x-pagination :paginator="$members" standing />
    @endif
</x-index-page>
@endsection
