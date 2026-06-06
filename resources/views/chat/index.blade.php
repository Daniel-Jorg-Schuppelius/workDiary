@extends('layouts.app')
@section('title', __('Chat') . ' — WorkDiary')
@section('nav-title', __('Chat'))
{{-- App-Standard für volle, scrollende Inhaltshöhe (wie Woche/Kanban): der Wrapper
     bekommt feste Höhe (wd-page-fill), main füllt sie (flex-1), die inneren Panes
     regeln ihr Scrollen selbst. --}}
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Support\Collection<int,\App\Models\Chat\Channel> $channels */
    /** @var \App\Models\Chat\Channel|null $activeChannel */
    $me = auth()->user();
    $icon = ['channel' => 'tag', 'group' => 'groups', 'direct' => 'person'];
    $channelTitle = function ($c) use ($me) {
        if ($c->type === 'direct') {
            return $c->members->firstWhere('id', '!=', $me?->id)?->name ?? __('Direktnachricht');
        }
        return $c->name;
    };
@endphp

@section('content')
<x-page-shell overflow="clip">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Kanäle, Direktnachrichten, Threads, Reaktionen und Umfragen.')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm" show-label
                            onclick="document.getElementById('chat-new-channel').showModal()">{{ __('Neuer Kanal') }}</x-icon-btn>
                <x-icon-btn icon="person_add" tone="outline" size="sm" show-label
                            onclick="document.getElementById('chat-new-dm').showModal()">{{ __('Direktnachricht') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Such-/Filterleiste (Standard-Optik, eigener div unter dem Header) --}}
    <div class="flex min-h-16 flex-none items-center gap-2 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="relative w-full max-w-md">
            <input id="chat-search" type="search" autocomplete="off"
                   class="input input-sm input-bordered w-full"
                   placeholder="{{ __('Nachrichten durchsuchen …') }}">
            <div id="chat-search-results"
                 class="absolute inset-x-0 top-full z-30 mt-1 hidden max-h-80 overflow-y-auto rounded-box border border-base-300 bg-base-100 shadow-lg"></div>
        </div>
    </div>

<div id="chat-root"
     data-channel-id="{{ $activeChannel?->id }}"
     data-txt-delete="{{ __('Nachricht löschen?') }}"
     data-txt-edit="{{ __('Nachricht bearbeiten:') }}"
     data-txt-emoji="{{ __('Emoji:') }}"
     class="flex min-h-0 flex-1 gap-3">

    {{-- Sidebar: Kanäle --}}
    <aside class="flex w-64 min-h-0 shrink-0 flex-col rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="flex h-14 shrink-0 items-center gap-2 border-b border-base-300 px-3">
            <h2 class="font-['Space_Grotesk'] font-semibold">{{ __('Kanäle') }}</h2>
        </div>
        <nav id="chat-channel-list" class="min-h-0 flex-1 overflow-y-auto p-2">
            @forelse ($channels as $c)
                @php $unread = $me ? $c->unreadCountFor($me) : 0; $active = $activeChannel && $activeChannel->id === $c->id; @endphp
                <a href="{{ route('chat.show', $c) }}"
                   class="flex items-center gap-2 rounded-box px-2 py-1.5 text-sm {{ $active ? 'bg-primary/10 text-primary' : 'hover:bg-base-200' }}">
                    <x-icon :name="$icon[$c->type] ?? 'tag'" size="1rem" class="opacity-60" />
                    <span class="flex-1 truncate {{ $unread ? 'font-semibold' : '' }}">{{ $channelTitle($c) }}</span>
                    @if ($unread)<span class="badge badge-primary badge-sm tabular-nums">{{ $unread }}</span>@endif
                </a>
            @empty
                <p class="px-2 py-4 text-sm text-base-content/50">{{ __('Noch keine Kanäle.') }}</p>
            @endforelse
        </nav>
    </aside>

    {{-- Hauptbereich --}}
    <section class="flex min-h-0 min-w-0 flex-1 flex-col rounded-box border border-base-300 bg-base-100 shadow-xs">
        @if ($activeChannel)
            <header class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-base-300 px-3">
                <div class="min-w-0">
                    <h1 class="truncate font-['Space_Grotesk'] text-lg font-semibold leading-tight">
                        <x-icon :name="$icon[$activeChannel->type] ?? 'tag'" size="1.1rem" class="opacity-60" /> {{ $channelTitle($activeChannel) }}
                    </h1>
                    @if ($activeChannel->description)<p class="truncate text-xs text-base-content/60">{{ $activeChannel->description }}</p>@endif
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <span class="text-xs text-base-content/50">{{ trans_choice(':count Mitglied|:count Mitglieder', $activeChannel->members->count(), ['count' => $activeChannel->members->count()]) }}</span>
                    @can('manageMembers', $activeChannel)
                        <button class="btn btn-xs btn-ghost" onclick="document.getElementById('chat-invite').showModal()"><x-icon name="person_add" size="1rem" /> {{ __('Einladen') }}</button>
                    @endcan
                    @if (! $activeChannel->isDirect())
                        <form method="POST" action="{{ route('chat.channels.leave', $activeChannel) }}" onsubmit="return confirm('{{ __('Kanal verlassen?') }}')">@csrf
                            <button class="btn btn-xs btn-ghost text-error">{{ __('Verlassen') }}</button>
                        </form>
                    @endif
                </div>
            </header>

            {{-- Nachrichtenliste --}}
            <div id="chat-messages" class="min-h-0 flex-1 overflow-x-clip overflow-y-auto pt-3"></div>

            {{-- Composer --}}
            <form id="chat-composer" class="border-t border-base-300 p-3" enctype="multipart/form-data">
                @csrf
                <div class="flex items-end gap-2">
                    <textarea name="body" rows="1" class="textarea textarea-bordered max-h-32 min-h-10 flex-1"
                              placeholder="{{ __('Nachricht schreiben …') }}"
                              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.requestSubmit();}"></textarea>
                    <label class="btn btn-ghost btn-square" title="{{ __('Datei anhängen') }}">
                        <x-icon name="attach_file" />
                        <input type="file" name="files[]" multiple class="hidden">
                    </label>
                    <button type="button" class="btn btn-ghost btn-square" title="{{ __('Umfrage') }}" onclick="document.getElementById('chat-new-poll').showModal()"><x-icon name="bar_chart" /></button>
                    <button type="submit" class="btn btn-primary btn-square" title="{{ __('Senden') }}"><x-icon name="send" /></button>
                </div>
            </form>
        @else
            {{-- Standard-Empty-State (graues Feld) füllt die Karte und wächst mit. --}}
            <x-empty-state class="m-3 min-h-0 flex-1"
                           icon='<span class="material-symbols-outlined" aria-hidden="true">forum</span>'
                           :title="__('Kein Kanal ausgewählt')"
                           :message="__('Wähle links einen Kanal oder erstelle einen neuen.')" />
        @endif
    </section>

    {{-- Thread-Drawer --}}
    <aside id="chat-thread" class="hidden w-80 min-h-0 shrink-0 flex-col rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-base-300 px-3">
            <h2 class="font-['Space_Grotesk'] font-semibold">{{ __('Thread') }}</h2>
            <button id="chat-thread-close" class="btn btn-xs btn-ghost btn-square"><x-icon name="close" /></button>
        </div>
        <div id="chat-thread-body" class="min-h-0 flex-1 overflow-y-auto p-2"></div>
        <form id="chat-thread-form" class="border-t border-base-300 p-3" enctype="multipart/form-data">
            @csrf
            <div class="flex items-end gap-2">
                <textarea name="body" rows="1" class="textarea textarea-bordered min-h-10 flex-1" placeholder="{{ __('Antworten …') }}"></textarea>
                <button type="submit" class="btn btn-primary btn-square"><x-icon name="send" /></button>
            </div>
        </form>
    </aside>
</div>
</x-page-shell>

{{-- Dialog: Neuer Kanal --}}
<x-modal id="chat-new-channel" :embedded="false" icon="forum" :eyebrow="__('Chat')" :title="__('Neuer Kanal')"
         :action="route('chat.channels.store')" method="POST" :submit-label="__('Erstellen')">
    <x-form-group :legend="__('Kanal')" icon="tag" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Name') }}</label>
            <input type="text" name="name" required maxlength="120" class="input input-bordered w-full">
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Beschreibung (optional)') }}</label>
            <textarea name="description" maxlength="1000" rows="2" class="textarea textarea-bordered w-full"></textarea>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Typ') }}</label>
            <select name="type" class="select select-bordered w-full"><option value="channel">{{ __('Kanal') }}</option><option value="group">{{ __('Gruppe') }}</option></select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Sichtbarkeit') }}</label>
            <select name="visibility" class="select select-bordered w-full"><option value="public">{{ __('Öffentlich') }}</option><option value="private">{{ __('Privat') }}</option></select>
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Mitglieder') }}</label>
            <x-user-checklist name="members" :users="$orgUsers" value-key="id"
                              :placeholder="__('Mitarbeiter suchen…')"
                              :empty-text="__('Keine weiteren Mitarbeiter in dieser Organisation.')" />
        </div>
    </x-form-group>
</x-modal>

{{-- Dialog: Direktnachricht --}}
<x-modal id="chat-new-dm" :embedded="false" icon="person_add" :eyebrow="__('Chat')" :title="__('Direktnachricht')"
         :action="route('chat.direct')" method="POST" :submit-label="__('Öffnen')">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Person') }}</label>
        <select name="user_id" required class="select select-bordered w-full">
            <option value="">{{ __('— Person wählen —') }}</option>
            @foreach ($orgUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </select>
        @if ($orgUsers->isEmpty())
            <p class="mt-1 text-xs text-base-content/60">{{ __('Keine weiteren Mitarbeiter in dieser Organisation.') }}</p>
        @endif
    </div>
</x-modal>

@if ($activeChannel)
    {{-- Dialog: Umfrage --}}
    <x-modal id="chat-new-poll" :embedded="false" icon="bar_chart" :eyebrow="__('Chat')" :title="__('Umfrage erstellen')"
             :action="route('chat.polls.store', $activeChannel)" method="POST" :submit-label="__('Erstellen')">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Frage') }}</label>
            <input type="text" name="question" required maxlength="300" class="input input-bordered w-full">
        </div>
        <div class="fieldset mt-2">
            <label class="fieldset-label">{{ __('Optionen') }}</label>
            @for ($i = 0; $i < 5; $i++)
                <input type="text" name="options[]" maxlength="200" class="input input-bordered input-sm mt-1 w-full" placeholder="{{ __('Option') }} {{ $i + 1 }}">
            @endfor
        </div>
        <label class="label mt-2 cursor-pointer justify-start gap-2">
            <input type="checkbox" name="multiple" value="1" class="checkbox checkbox-sm">
            <span class="label-text">{{ __('Mehrfachauswahl') }}</span>
        </label>
    </x-modal>

    @can('manageMembers', $activeChannel)
        {{-- Dialog: Einladen --}}
        <x-modal id="chat-invite" :embedded="false" icon="person_add" :eyebrow="__('Chat')" :title="__('Mitglieder einladen')"
                 :action="route('chat.channels.invite', $activeChannel)" method="POST" :submit-label="__('Einladen')">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Mitglieder') }}</label>
                <x-user-checklist name="members" :users="$orgUsers" value-key="id"
                                  :placeholder="__('Mitarbeiter suchen…')"
                                  :empty-text="__('Keine weiteren Mitarbeiter in dieser Organisation.')" />
            </div>
        </x-modal>
    @endcan
@endif
@endsection

@push('scripts')
    @vite('resources/js/chat.js')
@endpush
