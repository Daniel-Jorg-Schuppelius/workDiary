{{-- Einzelne Chat-Nachricht (WhatsApp-Stil: eigene rechts, fremde links).
     Serverseitig gerendert (MessageController@render) und per fetch ins DOM
     eingefügt — daher KEINE Alpine-Direktiven, sondern data-Attribute +
     Event-Delegation in der Chat-JS. Erwartet $message, $me. --}}
@php
    /** @var \App\Models\Chat\Message $message */
    $isMine = $me && $message->user_id === $me->id;
    $reactions = $message->reactions->groupBy('emoji');
    $poll = $message->type === 'poll' ? $message->poll : null;
    $initials = \Illuminate\Support\Str::of($message->user?->name ?? '–')->substr(0, 2)->upper();
    $metaTone = $isMine ? 'text-primary-content/70' : 'text-base-content/50';
@endphp
<div class="chat-msg group relative flex items-start gap-2 px-3 py-0.5 {{ $isMine ? 'flex-row-reverse' : '' }}"
     data-message-id="{{ $message->id }}" id="chat-msg-{{ $message->id }}"
     data-user-id="{{ $message->user_id }}" data-ts="{{ $message->created_at?->timestamp }}" data-mine="{{ $isMine ? '1' : '0' }}">
    {{-- Avatar nur bei fremden Nachrichten --}}
    @unless ($isMine)
        <div class="chat-avatar flex size-8 shrink-0 items-center justify-center rounded-full bg-neutral text-xs font-medium text-neutral-content"
             title="{{ $message->user?->name }}">{{ $initials }}</div>
    @endunless

    <div class="relative flex min-w-0 max-w-[78%] flex-col {{ $isMine ? 'items-end' : 'items-start' }}">
        {{-- Name nur bei fremden Nachrichten --}}
        @unless ($isMine)
            <span class="chat-name mb-0.5 px-1 text-xs font-semibold text-base-content/70">{{ $message->user?->name ?? __('System') }}</span>
        @endunless

        @if ($poll)
            {{-- Umfrage: eigene Karte (keine Bubble-Farbe) --}}
            @php
                $total = $poll->options->reduce(fn ($c, $o) => $c + $o->votes->count(), 0);
                $myVotes = $poll->options->flatMap->votes->where('user_id', $me?->id)->pluck('poll_option_id')->all();
            @endphp
            <div class="rounded-2xl border border-base-300 bg-base-100 p-3" data-poll-id="{{ $poll->id }}">
                <p class="font-medium">{{ $poll->question }}</p>
                <div class="mt-2 space-y-1.5">
                    @foreach ($poll->options as $opt)
                        @php $c = $opt->votes->count(); $pct = $total > 0 ? round($c / $total * 100) : 0; $mine = in_array($opt->id, $myVotes, true); @endphp
                        <button type="button" class="block w-full text-left" data-action="vote" data-poll-id="{{ $poll->id }}" data-option-id="{{ $opt->id }}" @disabled($poll->isClosed())>
                            <div class="flex items-center justify-between text-sm">
                                <span class="{{ $mine ? 'font-semibold text-primary' : '' }}">{{ $mine ? '✓ ' : '' }}{{ $opt->label }}</span>
                                <span class="tabular-nums text-base-content/60">{{ $c }} · {{ $pct }}%</span>
                            </div>
                            <progress class="progress progress-primary h-1.5 w-full" value="{{ $pct }}" max="100"></progress>
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-base-content/50">
                    {{ trans_choice(':count Stimme|:count Stimmen', $total, ['count' => $total]) }}
                    @if ($poll->multiple) · {{ __('Mehrfachauswahl') }} @endif
                    @if ($poll->isClosed()) · {{ __('beendet') }} @endif
                    · {{ $message->created_at?->ftime() }}
                </p>
            </div>
        @else
            {{-- Sprechblase --}}
            <div class="rounded-2xl px-3 py-1.5 shadow-xs {{ $isMine ? 'rounded-br-sm bg-primary text-primary-content' : 'rounded-bl-sm bg-base-200 text-base-content' }}">
                @if (filled($message->body))
                    @php
                        // Escapen, dann @Mentions hervorheben (auf dem escapten String → XSS-sicher).
                        $bodyHtml = preg_replace('/@([\p{L}\p{N}_.\-]+)/u', '<span class="font-semibold underline decoration-current/40">@$1</span>', e($message->body));
                    @endphp
                    <div class="whitespace-pre-line break-words text-sm leading-relaxed">{!! nl2br($bodyHtml) !!}</div>
                @endif

                {{-- Anhänge --}}
                @if ($message->attachments->isNotEmpty())
                    <div class="mt-1.5 flex flex-wrap gap-2">
                        @foreach ($message->attachments as $att)
                            @php $url = \App\Http\Controllers\AttachmentController::downloadUrl($att); @endphp
                            @if ($att->isImage())
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="block">
                                    <img src="{{ $url }}" alt="{{ $att->original_name }}" class="max-h-48 rounded-xl object-cover">
                                </a>
                            @else
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl bg-base-100/70 px-3 py-2 text-sm text-base-content hover:bg-base-100">
                                    <x-icon name="description" /> <span class="max-w-48 truncate">{{ $att->original_name }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Meta: Zeit / bearbeitet / angepinnt --}}
                <div class="mt-0.5 flex items-center justify-end gap-1 text-[10px] {{ $metaTone }}">
                    @if ($message->isPinned())<x-icon name="push_pin" size="0.75rem" />@endif
                    @if ($message->edited_at)<span>{{ __('bearbeitet') }}</span>@endif
                    <time>{{ $message->created_at?->ftime() }}</time>
                </div>
            </div>
        @endif

        {{-- Thread-Indikator: dauerhaft sichtbar, sobald Antworten existieren --}}
        @php $replyCount = $message->parent_id ? 0 : $message->replies->count(); @endphp
        @if ($replyCount > 0)
            <button type="button" data-action="thread" data-message-id="{{ $message->id }}"
                    class="mt-1 inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary transition hover:bg-primary/20">
                <x-icon name="forum" size="0.9rem" />
                {{ trans_choice(':count Antwort|:count Antworten', $replyCount, ['count' => $replyCount]) }}
                <x-icon name="chevron_right" size="0.9rem" class="opacity-60" />
            </button>
        @endif

        {{-- Reaktionen: nur rendern, wenn vorhanden (sonst kein Leerraum) --}}
        @if ($reactions->isNotEmpty())
            <div class="mt-0.5 flex flex-wrap items-center gap-1 {{ $isMine ? 'justify-end' : '' }}">
                @foreach ($reactions as $emoji => $group)
                    @php $mine = $group->contains('user_id', $me?->id); @endphp
                    <button type="button" class="btn btn-xs {{ $mine ? 'btn-primary' : 'btn-ghost border border-base-300' }} gap-1" data-action="react" data-message-id="{{ $message->id }}" data-emoji="{{ $emoji }}">
                        <span>{{ $emoji }}</span><span class="tabular-nums">{{ $group->count() }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Aktionen: kleiner Trigger neben der Bubble (bei Nachrichten-Hover).
             Das volle Aktionsmenü erscheint erst beim Hovern des Triggers. --}}
        <div class="group/act absolute -top-2 z-10 hidden group-hover:block {{ $isMine ? 'right-1' : 'left-1' }}">
            <button type="button" class="btn btn-circle btn-xs border border-base-300 bg-base-100 shadow-sm" title="{{ __('Aktionen') }}">
                <x-icon name="more_horiz" size="1rem" />
            </button>
            {{-- Menü: deckt den Trigger bei Hover ab, wächst zur Mitte --}}
            <div class="absolute top-0 hidden items-center gap-0.5 rounded-full border border-base-300 bg-base-100 px-1 py-0.5 shadow-md group-hover/act:flex {{ $isMine ? 'right-0' : 'left-0' }}">
                <button type="button" class="btn btn-xs btn-ghost" data-action="react" data-message-id="{{ $message->id }}" data-emoji="👍" title="{{ __('Gefällt mir') }}">👍</button>
                <button type="button" class="btn btn-xs btn-ghost" data-action="react-pick" data-message-id="{{ $message->id }}" title="{{ __('Reagieren') }}">😀</button>
                @if (! $message->parent_id)
                    <button type="button" class="btn btn-xs btn-ghost gap-1" data-action="thread" data-message-id="{{ $message->id }}"><x-icon name="forum" size="0.9rem" /> {{ __('Antworten') }}</button>
                    <button type="button" class="btn btn-xs btn-ghost" data-action="pin" data-message-id="{{ $message->id }}" title="{{ __('Anpinnen') }}"><x-icon name="push_pin" size="0.9rem" /></button>
                @endif
                @if ($isMine)
                    <button type="button" class="btn btn-xs btn-ghost" data-action="edit" data-message-id="{{ $message->id }}" data-body="{{ $message->body }}" title="{{ __('Bearbeiten') }}"><x-icon name="edit" size="0.9rem" /></button>
                    <button type="button" class="btn btn-xs btn-ghost text-error" data-action="delete" data-message-id="{{ $message->id }}" title="{{ __('Löschen') }}"><x-icon name="delete" size="0.9rem" /></button>
                @endif
            </div>
        </div>
    </div>
</div>
