@extends('layouts.app')

@section('title', __('Organisationen'))
@section('nav-title', __('Organisationen'))

@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">
    <div class="flex justify-end">
        <a href="{{ route('admin.organizations.create') }}" class="btn btn-primary btn-sm">
            + {{ __('Organisation anlegen') }}
        </a>
    </div>

    <x-table>
        <thead>
            <tr>
                <th><x-sort-th column="name" :route="route('admin.organizations.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'" default="name">{{ __('Name') }}</x-sort-th></th>
                <th>{{ __('Slug') }}</th>
                <th><x-sort-th column="plan" :route="route('admin.organizations.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Plan') }}</x-sort-th></th>
                <th class="text-center"><x-sort-th column="users" :route="route('admin.organizations.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Benutzer') }}</x-sort-th></th>
                <th class="text-center"><x-sort-th column="is_active" :route="route('admin.organizations.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Aktiv') }}</x-sort-th></th>
                <th>{{ __('Erstellt') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
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
                            <a href="{{ route('admin.organizations.edit', $org) }}" class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
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
                <tr>
                    <td colspan="7" class="text-center text-base-content/50 py-8">{{ __('Keine Organisationen vorhanden.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div>{{ $organizations->links() }}</div>
</div>
@endsection
