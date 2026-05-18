@extends('layouts.app')
@section('title', __('Mitarbeiter'))
@section('nav-title', __('Mitarbeiter'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <a href="{{ route('org.members.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                    + {{ __('Mitarbeiter anlegen') }}
                </a>
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
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('org.members.edit', $member) }}" data-entry-modal-trigger class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                                <form method="POST" action="{{ route('org.members.destroy', $member) }}"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Mitarbeiter wirklich entfernen?') }}"
                                      data-confirm-label="{{ __('Entfernen') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Entfernen') }}</button>
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
