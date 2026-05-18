@extends('layouts.app')
@section('title', __('Mitarbeiter') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Mitarbeiter'))

@section('content')
    <?php $legacyUsers = collect($users ?? []); ?>
    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <span class="text-sm text-base-content/60">{{ trans_choice(':n Mitarbeiter|:n Mitarbeiter', $legacyUsers->count(), ['n' => $legacyUsers->count()]) }}</span>
            <a href="{{ route('legacy.users.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm gap-1">
                <x-icon name="add" /><span>{{ __('Neuer Mitarbeiter') }}</span>
            </a>
        </div>
        <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
        <x-table table-sort="client" bare scroll="none" size="xs" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string" default="asc">{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('E-Mail') }}</x-table.th>
                    <th class="w-24 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($legacyUsers as $legacyUser)
                <tr class="hover">
                    <td>{{ $legacyUser->uname }}</td>
                    <td>{{ $legacyUser->email ?: '–' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('legacy.users.edit', $legacyUser) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost" title="{{ __('Bearbeiten') }}" aria-label="{{ __('Bearbeiten') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <form method="POST" action="{{ route('legacy.users.destroy', $legacyUser) }}" class="inline"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Mitarbeiter wirklich löschen?') }}"
                              data-confirm-label="{{ __('Löschen') }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-xs btn-ghost text-error" title="{{ __('Löschen') }}" aria-label="{{ __('Löschen') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">group</span>' :colspan="3" :title="__('Keine Mitarbeiter gefunden')" compact />
            @endforelse
        </x-table>
        </div>
    </div>
@endsection
