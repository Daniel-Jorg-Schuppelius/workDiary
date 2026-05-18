@extends('layouts.app')

@section('title', __('Organisationen'))
@section('nav-title', __('Organisationen'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <a href="{{ route('admin.organizations.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                    + {{ __('Organisation anlegen') }}
                </a>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-table table-sort="server"
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
                <tr>
                    <td class="font-medium">{{ $org->name }}</td>
                    <td class="font-mono text-sm text-base-content/60">{{ $org->slug }}</td>
                    <td>
                        <span class="badge badge-sm {{ $org->plan === 'enterprise' ? 'badge-primary' : ($org->plan === 'pro' ? 'badge-secondary' : 'badge-ghost') }}">
                            {{ $org->plan }}
                        </span>
                    </td>
                    <td class="text-center">{{ $org->users_count }}</td>
                    <td class="text-center">
                        @if ($org->is_active)
                            <span class="badge badge-success badge-sm">{{ __('Ja') }}</span>
                        @else
                            <span class="badge badge-error badge-sm">{{ __('Nein') }}</span>
                        @endif
                    </td>
                    <td class="text-sm text-base-content/60">{{ $org->created_at?->toDateString() }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.organizations.edit', $org) }}" data-entry-modal-trigger class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                            <form method="POST" action="{{ route('admin.organizations.destroy', $org) }}"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Organisation wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Löschen') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">apartment</span>' :colspan="7" :title="__('Keine Organisationen vorhanden')" compact />
            @endforelse
    </x-table>

    @if ($organizations->hasPages())
        <div>{{ $organizations->links() }}</div>
    @endif
</x-page-shell>
@endsection
