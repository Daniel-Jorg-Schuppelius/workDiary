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
                            data-open-dialog="chat-new-channel">{{ __('Neuer Kanal') }}</x-icon-btn>
                <x-icon-btn icon="person_add" tone="outline" size="sm" show-label
                            data-open-dialog="chat-new-dm">{{ __('Direktnachricht') }}</x-icon-btn>
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
     data-channel-id="{{ $activeChannel?->sqid }}"
     data-txt-del-title="{{ __('Nachricht löschen?') }}"
     data-txt-del-msg="{{ __('Diese Nachricht wird dauerhaft entfernt.') }}"
     data-txt-del-ok="{{ __('Löschen') }}"
     data-me-name="{{ $me?->name }}"
     data-me-id="{{ $me?->id }}"
     data-txt-typing="{{ __(':name schreibt …') }}"
     data-txt-forwarded="{{ __('Nachricht weitergeleitet.') }}"
     data-txt-new="{{ __('Neue Nachrichten') }}"
     data-txt-reminded="{{ __('Erinnerung gesetzt.') }}"
     data-txt-scheduled="{{ __('Nachricht geplant.') }}"
     data-txt-today="{{ __('Heute') }}"
     data-txt-yesterday="{{ __('Gestern') }}"
     class="flex min-h-0 flex-1 gap-2 lg:gap-3">

    {{-- Sidebar: Kanäle (mobil: Vollbreite; bei offenem Kanal ausgeblendet) --}}
    <aside class="{{ $activeChannel ? 'hidden lg:flex' : 'flex' }} w-full min-h-0 shrink-0 flex-col overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs lg:w-64">
        <div class="flex h-14 shrink-0 items-center gap-2 border-b border-base-300 px-3">
            <h2 class="font-['Space_Grotesk'] font-semibold">{{ __('Kanäle') }}</h2>
        </div>
        <nav id="chat-channel-list" class="min-h-0 flex-1 overflow-y-auto p-2" data-list-url="{{ route('chat.channel-list') }}">
            @include('chat._channel_list')
        </nav>
    </aside>

    {{-- Hauptbereich (mobil: nur sichtbar, wenn ein Kanal offen ist) --}}
    <section class="{{ $activeChannel ? 'flex' : 'hidden lg:flex' }} min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
        @if ($activeChannel)
            <header class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-base-300 px-3">
                <a href="{{ route('chat.index') }}" class="btn btn-ghost btn-sm btn-square -ml-1 shrink-0 lg:hidden" title="{{ __('Zurück') }}"><x-icon name="arrow_back" /></a>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate font-['Space_Grotesk'] text-lg font-semibold leading-tight">
                        <x-icon :name="$icon[$activeChannel->type] ?? 'tag'" size="1.1rem" class="opacity-60" /> {{ $channelTitle($activeChannel) }}
                    </h1>
                    @if ($activeChannel->description)<p class="truncate text-xs text-base-content/60">{{ $activeChannel->description }}</p>@endif
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span id="chat-presence" data-tpl="{{ __(':count online') }}" class="hidden items-center gap-1 text-xs font-medium text-success">
                        <span class="inline-block size-2 rounded-full bg-success"></span><span data-count></span>
                    </span>
                    <span class="text-xs text-base-content/50">{{ trans_choice(':count Mitglied|:count Mitglieder', $activeChannel->members->count(), ['count' => $activeChannel->members->count()]) }}</span>
                    @can('manageMembers', $activeChannel)
                        <button class="btn btn-xs btn-ghost btn-square" title="{{ __('Mitglieder einladen') }}" aria-label="{{ __('Mitglieder einladen') }}" data-open-dialog="chat-invite"><x-icon name="person_add" size="1.1rem" /></button>
                    @endcan
                    @if (! $activeChannel->isDirect())
                        <x-action-form :action="route('chat.channels.leave', $activeChannel)"
                              data-confirm-title="{{ __('Kanal verlassen?') }}"
                              :confirm="__('Du erhältst keine neuen Nachrichten dieses Kanals mehr.')"
                              :confirm-label="__('Verlassen')"
                              confirm-icon="logout">
                            <button class="btn btn-xs btn-ghost btn-square text-error" title="{{ __('Verlassen') }}" aria-label="{{ __('Verlassen') }}"><x-icon name="logout" size="1.1rem" /></button>
                        </x-action-form>
                    @endif
                </div>
            </header>

            {{-- Nachrichtenliste --}}
            <div class="relative flex min-h-0 flex-1 flex-col">
                <div id="chat-messages" class="min-h-0 flex-1 overflow-x-clip overflow-y-auto pt-3"></div>
                <button id="chat-scroll-bottom" type="button"
                        class="btn btn-circle btn-sm absolute bottom-3 right-4 z-10 hidden shadow-md"
                        title="{{ __('Nach unten') }}"><x-icon name="arrow_downward" /></button>
            </div>

            {{-- Tipp-Anzeige ("… schreibt …") --}}
            <div id="chat-typing" class="hidden h-5 px-4 text-xs italic text-base-content/60"></div>

            {{-- Composer --}}
            <form id="chat-composer" class="border-t border-base-300 bg-base-200 p-3" enctype="multipart/form-data">
                @csrf
                {{-- Zitat-Antwort-Vorschau --}}
                <div id="chat-reply-bar" class="mb-1 hidden items-center gap-2 rounded-lg border-l-4 border-primary bg-base-100 px-2 py-1 text-xs">
                    <input type="hidden" name="quoted_id" id="chat-quoted-id" value="">
                    <div class="min-w-0 flex-1">
                        <span id="chat-reply-name" class="font-semibold text-primary"></span>
                        <span id="chat-reply-snippet" class="block truncate opacity-70"></span>
                    </div>
                    <button type="button" id="chat-reply-cancel" class="btn btn-ghost btn-xs btn-square" title="{{ __('Abbrechen') }}"><x-icon name="close" size="1rem" /></button>
                </div>
                {{-- Format-Toolbar --}}
                <div class="mb-1 flex items-center gap-0.5">
                    <button type="button" data-fmt="bold" class="btn btn-ghost btn-xs btn-square" title="{{ __('Fett') }}"><x-icon name="format_bold" size="1.15rem" /></button>
                    <button type="button" data-fmt="italic" class="btn btn-ghost btn-xs btn-square" title="{{ __('Kursiv') }}"><x-icon name="format_italic" size="1.15rem" /></button>
                    <button type="button" data-fmt="code" class="btn btn-ghost btn-xs btn-square" title="{{ __('Inline-Code') }}"><x-icon name="code" size="1.15rem" /></button>
                    <button type="button" data-fmt="codeblock" class="btn btn-ghost btn-xs btn-square" title="{{ __('Codeblock') }}"><x-icon name="data_object" size="1.15rem" /></button>
                    <button type="button" id="chat-schedule-btn" class="btn btn-ghost btn-xs btn-square" title="{{ __('Senden planen') }}"><x-icon name="schedule" size="1.15rem" /></button>
                    <div class="relative">
                        <button type="button" id="chat-emoji-insert" class="btn btn-ghost btn-xs btn-square" title="{{ __('Emoji') }}"><x-icon name="mood" size="1.15rem" /></button>
                        <div id="chat-emoji-panel" class="absolute bottom-full left-0 z-30 mb-1 hidden max-h-56 w-72 grid-cols-8 gap-0.5 overflow-y-auto rounded-box border border-base-300 bg-base-100 p-1 text-2xl shadow-lg">
                            @foreach (['👍', '👎', '❤️', '🔥', '🎉', '😂', '😅', '😍', '😎', '🤔', '😮', '😢', '😡', '🤯', '🥳', '😴', '🙏', '👏', '🙌', '💪', '🤝', '👀', '✅', '❌', '❓', '❗', '💡', '⭐', '🚀', '🎯', '💯', '☕'] as $emoji)
                                <button type="button" data-insert="{{ $emoji }}" class="rounded p-1 leading-none hover:bg-base-200">{{ $emoji }}</button>
                            @endforeach
                        </div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs btn-square ml-auto" title="{{ __('Formatierungshilfe') }}"
                            data-open-dialog="chat-format-help"><x-icon name="help" size="1.15rem" /></button>
                </div>
                {{-- Anhang-Vorschau (Einfügen/Drag&Drop) --}}
                <div id="chat-file-preview" class="mb-1 hidden flex-wrap gap-2"></div>
                <div class="flex items-end gap-2">
                    <textarea name="body" rows="1" class="textarea textarea-bordered max-h-32 min-h-10 flex-1"
                              placeholder="{{ __('Nachricht schreiben …') }}"
                              data-submit-on-enter></textarea>
                    <label class="btn btn-ghost btn-square" title="{{ __('Datei anhängen') }}">
                        <x-icon name="attach_file" />
                        <input id="chat-file-input" type="file" name="files[]" multiple class="hidden">
                    </label>
                    <button type="button" class="btn btn-ghost btn-square" title="{{ __('Umfrage') }}" data-open-dialog="chat-new-poll"><x-icon name="bar_chart" /></button>
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
    <aside id="chat-thread" class="hidden min-h-0 flex-col border border-base-300 bg-base-100 shadow-xs fixed inset-0 z-40 lg:static lg:inset-auto lg:z-auto lg:w-80 lg:shrink-0 lg:rounded-box">
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
    {{-- Dialog: Nachricht bearbeiten (JS-gesteuert über chat.js) --}}
    <x-modal id="chat-edit-dialog" :embedded="false" icon="edit" :eyebrow="__('Chat')" :title="__('Nachricht bearbeiten')">
        <div class="fieldset">
            <label class="fieldset-label" for="chat-edit-input">{{ __('Nachricht') }}</label>
            <textarea id="chat-edit-input" rows="3" class="textarea textarea-bordered w-full"></textarea>
        </div>
        <x-slot:actions>
            <x-button type="button" tone="ghost" size="md" data-entry-modal-close>{{ __('Abbrechen') }}</x-button>
            <x-button type="button" id="chat-edit-save" tone="primary" size="md">{{ __('Speichern') }}</x-button>
        </x-slot:actions>
    </x-modal>

    {{-- Dialog: Emoji-Auswahl (JS-gesteuert über chat.js) --}}
    <x-modal id="chat-emoji-dialog" :embedded="false" icon="mood" :eyebrow="__('Chat')" :title="__('Reagieren')" :hide-footer="true">
        <div class="grid max-h-[60vh] grid-cols-8 gap-0.5 overflow-y-auto text-4xl leading-none">
            @foreach (['👍', '👎', '❤️', '🧡', '💛', '💚', '💙', '💜', '🔥', '🎉', '🎊', '✨', '⭐', '🌟', '💫', '💯', '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '🙂', '😉', '😊', '😍', '🥰', '😘', '😎', '🤩', '🤔', '🤨', '😐', '😑', '🙄', '😏', '😮', '😯', '😲', '😳', '🥺', '😢', '😭', '😤', '😡', '🤯', '😱', '😴', '🤤', '🤗', '🤭', '🤫', '🫡', '🤝', '🙏', '👏', '🙌', '👐', '🤲', '💪', '👀', '🫶', '✅', '❌', '❓', '❗', '💡', '🚀', '🎯', '🏆', '👑', '💰', '☕', '🍕', '🎂', '🍻', '🥳', '🤡', '💩', '👻', '🙈', '🙉', '🙊', '🐶', '🐱'] as $emoji)
                <button type="button" data-emoji="{{ $emoji }}" class="rounded-lg p-1.5 transition hover:bg-base-200">{{ $emoji }}</button>
            @endforeach
        </div>
    </x-modal>

    {{-- Dialog: Weiterleiten (JS-gesteuert) --}}
    <x-modal id="chat-forward-dialog" :embedded="false" icon="forward" :eyebrow="__('Chat')" :title="__('Weiterleiten')">
        <div class="fieldset">
            <label class="fieldset-label" for="chat-forward-channel">{{ __('Zielkanal') }}</label>
            <select id="chat-forward-channel" class="select select-bordered w-full">
                @foreach ($channels as $c)
                    <option value="{{ $c->sqid }}">{{ $channelTitle($c) }}</option>
                @endforeach
            </select>
        </div>
        <x-slot:actions>
            <x-button type="button" tone="ghost" size="md" data-entry-modal-close>{{ __('Abbrechen') }}</x-button>
            <x-button type="button" id="chat-forward-send" tone="primary" size="md">{{ __('Weiterleiten') }}</x-button>
        </x-slot:actions>
    </x-modal>

    {{-- Dialog: Senden planen (JS-gesteuert) --}}
    <x-modal id="chat-schedule-dialog" :embedded="false" icon="schedule" :eyebrow="__('Chat')" :title="__('Senden planen')">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Zeitpunkt') }}</label>
            <div class="flex gap-2">
                <input type="date" id="chat-schedule-date" class="input input-bordered w-full">
                <input type="time" id="chat-schedule-time" class="input input-bordered w-36">
            </div>
        </div>
        <x-slot:actions>
            <x-button type="button" tone="ghost" size="md" data-entry-modal-close>{{ __('Abbrechen') }}</x-button>
            <x-button type="button" id="chat-schedule-send" tone="primary" size="md">{{ __('Planen') }}</x-button>
        </x-slot:actions>
    </x-modal>

    {{-- Dialog: Formatierungshilfe --}}
    <x-modal id="chat-format-help" :embedded="false" icon="help" :eyebrow="__('Chat')" :title="__('Formatierung')" :hide-footer="true">
        <ul class="space-y-1.5 text-sm">
            <li class="flex items-center gap-2"><code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">**{{ __('Text') }}**</code> → <strong>{{ __('Text') }}</strong></li>
            <li class="flex items-center gap-2"><code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">_{{ __('Text') }}_</code> → <em>{{ __('Text') }}</em></li>
            <li class="flex items-center gap-2"><code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">~~{{ __('Text') }}~~</code> → <del>{{ __('Text') }}</del></li>
            <li class="flex items-center gap-2"><code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">`code`</code> → <code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">code</code></li>
            <li class="flex items-center gap-2"><code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">```</code> … <code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">```</code> → {{ __('Codeblock') }}</li>
            <li><code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">@Name</code> → {{ __('Erwähnung') }} · <code class="rounded bg-base-300/70 px-1 py-0.5 font-mono">https://…</code> → Link</li>
        </ul>
    </x-modal>

    {{-- Dialog: Erinnerung (JS-gesteuert) --}}
    <x-modal id="chat-remind-dialog" :embedded="false" icon="alarm" :eyebrow="__('Chat')" :title="__('Erinnerung')">
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm" data-remind="20">{{ __('In 20 Min.') }}</button>
            <button type="button" class="btn btn-sm" data-remind="60">{{ __('In 1 Stunde') }}</button>
            <button type="button" class="btn btn-sm" data-remind="180">{{ __('In 3 Stunden') }}</button>
            <button type="button" class="btn btn-sm" data-remind="tomorrow">{{ __('Morgen früh') }}</button>
        </div>
        <div class="fieldset mt-3">
            <label class="fieldset-label">{{ __('Eigener Zeitpunkt') }}</label>
            <div class="flex gap-2">
                <input type="date" id="chat-remind-date" class="input input-bordered w-full">
                <input type="time" id="chat-remind-time" class="input input-bordered w-32">
            </div>
        </div>
        <x-slot:actions>
            <x-button type="button" tone="ghost" size="md" data-entry-modal-close>{{ __('Abbrechen') }}</x-button>
            <x-button type="button" id="chat-remind-save" tone="primary" size="md">{{ __('Erinnern') }}</x-button>
        </x-slot:actions>
    </x-modal>

    {{-- Dialog: Umfrage --}}
    <x-modal id="chat-new-poll" :embedded="false" icon="bar_chart" :eyebrow="__('Chat')" :title="__('Umfrage erstellen')"
             :action="route('chat.polls.store', $activeChannel)" method="POST" :submit-label="__('Erstellen')">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Frage') }}</label>
            <input type="text" name="question" required maxlength="300" class="input input-bordered w-full">
        </div>
        <div class="fieldset mt-2">
            <label class="fieldset-label">{{ __('Optionen') }}</label>
            <div id="chat-poll-options" class="space-y-1" data-opt-placeholder="{{ __('Option') }}">
                @for ($i = 0; $i < 2; $i++)
                    <div class="flex items-center gap-1">
                        <input type="text" name="options[]" maxlength="200" class="input input-bordered input-sm w-full" placeholder="{{ __('Option') }} {{ $i + 1 }}">
                        <button type="button" class="chat-poll-remove btn btn-ghost btn-sm btn-square" tabindex="-1" title="{{ __('Entfernen') }}"><x-icon name="close" size="1rem" /></button>
                    </div>
                @endfor
            </div>
            <x-button type="button" id="chat-poll-add" tone="ghost" class="mt-1 self-start">
                <x-icon name="add" size="1rem" /> {{ __('Option hinzufügen') }}
            </x-button>
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
