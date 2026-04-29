@extends('layouts.app')
@section('title', 'Legacy Mitarbeiter — ' . config('app.name', 'WorkDiary'))
@section('nav-title', 'Mitarbeiter')

@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    <div class="flex-none">
        <a href="{{ route('legacy.users.create') }}" class="btn btn-primary btn-sm">{{ __('Neuen Mitarbeiter anlegen') }}</a>
    </div>

    <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300">
        <div class="h-full overflow-auto">
        <table class="table table-xs table-zebra table-pin-rows">
            <thead class="bg-base-200">
                <tr>
                    <th class="w-16 text-center">ID</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('E-Mail') }}</th>
                    <th class="w-28 text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="text-center">{{ $user->id }}</td>
                        <td>{{ $user->uname }}</td>
                        <td>{{ $user->email ?: '-' }}</td>
                        <td class="text-right">
                            <a href="{{ route('legacy.users.edit', $user) }}" class="btn btn-sm btn-ghost" title="{{ __('Bearbeiten') }}" aria-label="{{ __('Bearbeiten') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('legacy.users.destroy', $user) }}" class="inline" onsubmit="return confirm('{{ __('Mitarbeiter wirklich löschen?') }}')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-ghost text-error" title="{{ __('Löschen') }}" aria-label="{{ __('Löschen') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-base-content/70">{{ __('Keine Mitarbeiter gefunden.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
