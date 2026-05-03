@extends('layouts.app')
@section('title', __('Legacy Mitarbeiter') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Mitarbeiter'))

@section('content')
    @php($legacyUsers = collect($users ?? []))
    <div class="mb-3">
        <a href="{{ route('legacy.users.create') }}" class="btn btn-primary btn-sm">{{ __('Neuen Mitarbeiter anlegen') }}</a>
    </div>

    <x-table size="xs">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('E-Mail') }}</th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($legacyUsers as $legacyUser)
                    <tr>
                        <td class="text-center">{{ $legacyUser->id }}</td>
                        <td>{{ $legacyUser->uname }}</td>
                        <td>{{ $legacyUser->email ?: '-' }}</td>
                        <td class="text-right">
                            <a href="{{ route('legacy.users.edit', $legacyUser) }}" class="link link-primary text-xs">{{ __('Bearbeiten') }}</a>
                            <span class="mx-1 text-base-content/40">|</span>
                            <form method="POST" action="{{ route('legacy.users.destroy', $legacyUser) }}" class="inline" onsubmit="return confirm('{{ __('Mitarbeiter wirklich löschen?') }}')">
                                @csrf
                                @method('DELETE')
                                <button class="link">{{ __('Löschen') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-base-content/70">{{ __('Keine Mitarbeiter gefunden.') }}</td>
                    </tr>
                @endforelse
            </tbody>
    </x-table>
@endsection
