@extends('layouts.app')
@section('title', __('Mitarbeiter'))
@section('nav-title', __('Mitarbeiter'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('org.members.create')"
                            show-label>{{ __('Mitarbeiter anlegen') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($members->isEmpty())
        <x-card>
            <x-empty-state
                icon='<span class="material-symbols-outlined" aria-hidden="true">group</span>'
                :title="__('Noch keine Mitarbeiter')"
                :message="__('Lege das erste Teammitglied an.')"
            />
        </x-card>
    @else
        <x-table table-sort="server"
                 :route="route('org.members.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'asc'">
            <x-slot:head>
                <tr>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort="email">{{ __('E-Mail') }}</x-table.th>
                    <th>{{ __('Rolle') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
                @foreach ($members as $member)
                    <tr>
                        <td class="font-medium">{{ $member->name }}</td>
                        <td class="text-sm text-base-content/70">{{ $member->email }}</td>
                        <td>
                            @foreach ($member->roles as $role)
                                <span class="badge badge-sm badge-outline">{{ $role->name }}</span>
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
                                <form method="POST" action="{{ route('org.members.destroy', $member) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Mitarbeiter wirklich entfernen?') }}"
                                      data-confirm-label="{{ __('Entfernen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="person_remove" tone="error" type="submit" :label="__('Entfernen')" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
        </x-table>
        @if ($members->hasPages())
            <div>{{ $members->links() }}</div>
        @endif
    @endif
</x-page-shell>
@endsection
