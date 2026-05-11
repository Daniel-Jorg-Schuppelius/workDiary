@extends('layouts.app')
@section('title', __('Legacy Mitarbeiter') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Mitarbeiter'))

@section('content')
    @php($legacyUsers = collect($users ?? []))
    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Mitarbeiter') }}</h1>
            <a href="{{ route('legacy.users.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">+ {{ __('Neuer Mitarbeiter') }}</a>
        </div>
        <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
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
                            <a href="{{ route('legacy.users.edit', $legacyUser) }}" data-entry-modal-trigger class="link link-primary text-xs">{{ __('Bearbeiten') }}</a>
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
        </div>
    </div>
@endsection
