@extends('layouts.app')
@section('title', __('Mitglieder'))
@section('nav-title', __('Mitglieder'))
@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">
    <div class="flex justify-end">
        <a href="{{ route('org.members.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
            + {{ __('Mitglied anlegen') }}
        </a>
    </div>

    <x-table>
        <thead>
            <tr>
                <th><x-sort-th column="name" :route="route('org.members.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'" default="name">{{ __('Name') }}</x-sort-th></th>
                <th><x-sort-th column="email" :route="route('org.members.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('E-Mail') }}</x-sort-th></th>
                <th>{{ __('Rolle') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $member)
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
                                  data-confirm-message="{{ __('Mitglied wirklich entfernen?') }}"
                                  data-confirm-label="{{ __('Entfernen') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Entfernen') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-8 text-center text-base-content/50">{{ __('Keine Mitglieder vorhanden.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>
    <div>{{ $members->links() }}</div>
</div>
@endsection
