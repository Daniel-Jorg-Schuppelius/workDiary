@extends('layouts.app')
@section('title', __('Dashboard') . ' — WorkDiary')
@section('nav-title', __('Dashboard'))

@section('content')
    @php
        /** @var \Carbon\CarbonImmutable $now */
        /** @var array $user */
        /** @var array|null $team */
    @endphp

    <div class="mx-auto w-full max-w-screen-2xl space-y-6 px-4 xl:px-8 2xl:px-12">

        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-['Space_Grotesk'] text-2xl font-bold">{{ __('Hallo') }}, {{ Auth::user()->name }}</h1>
                <p class="text-sm text-base-content/60">{{ $now->translatedFormat('l, d.m.Y H:i') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('diary.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">+ {{ __('Neuer Eintrag') }}</a>
                <a href="{{ route('week.index') }}" class="btn btn-ghost btn-sm">{{ __('Wochenansicht') }}</a>
                <a href="{{ route('diary.index') }}" class="btn btn-ghost btn-sm">{{ __('Tagebuch') }}</a>
            </div>
        </div>

        {{-- Personal KPIs --}}
        <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Meine offenen Einträge') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $user['kpi']['open_entries'] }}</p>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('In Bearbeitung') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $user['kpi']['progress_entries'] }}</p>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Anstehende Schichten') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $user['kpi']['upcoming_shifts'] }}</p>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Anstehende Notdienste') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $user['kpi']['upcoming_emergencies'] }}</p>
            </div>
        </section>

        @if ($team)
            <section>
                <h2 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold uppercase tracking-[0.2em] text-base-content/60">{{ __('Team') }}</h2>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-box border border-info/40 bg-info/5 p-4">
                        <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Offen (Team)') }}</p>
                        <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $team['kpi']['open_entries'] }}</p>
                    </div>
                    <div class="rounded-box border border-info/40 bg-info/5 p-4">
                        <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('In Bearbeitung (Team)') }}</p>
                        <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $team['kpi']['progress_entries'] }}</p>
                    </div>
                    <div class="rounded-box border border-info/40 bg-info/5 p-4">
                        <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Heute archiviert') }}</p>
                        <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $team['kpi']['archived_today'] }}</p>
                    </div>
                    <div class="rounded-box border border-info/40 bg-info/5 p-4">
                        <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeitende') }}</p>
                        <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $team['kpi']['user_count'] }}</p>
                    </div>
                </div>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">

            {{-- Heute --}}
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <h3 class="mb-3 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Heute') }}</h3>
                @if ($user['today_shifts']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Keine Schicht heute.') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($user['today_shifts'] as $shift)
                            <li class="flex items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2 text-sm">
                                <span>📅 {{ $shift->start_at->format('H:i') }} – {{ $shift->end_at->format('H:i') }}</span>
                                <span class="text-base-content/60">{{ $shift->note ? truncate($shift->note, 40) : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Anstehende Schichten --}}
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <h3 class="mb-3 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Nächste Schichten') }}</h3>
                @if ($user['upcoming_shifts']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Keine geplanten Schichten.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($user['upcoming_shifts'] as $shift)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <span>{{ $shift->start_at->format('d.m. H:i') }} – {{ $shift->end_at->format('d.m. H:i') }}</span>
                                @if ($shift->note)<span class="text-base-content/60">{{ truncate($shift->note, 50) }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Anstehende Notdienste --}}
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <h3 class="mb-3 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Nächste Notdienste') }}</h3>
                @if ($user['upcoming_emergencies']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Keine geplanten Notdienste.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($user['upcoming_emergencies'] as $em)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <span>🚨 {{ $em->start_at->format('d.m. H:i') }} – {{ $em->end_at->format('d.m. H:i') }}</span>
                                @if ($em->reason)<span class="text-base-content/60">{{ truncate($em->reason, 50) }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Letzte eigene Einträge --}}
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <h3 class="mb-3 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Meine letzten Einträge') }}</h3>
                @if ($user['recent_entries']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Noch keine Einträge.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($user['recent_entries'] as $entry)
                            <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <a href="{{ route('diary.show', $entry) }}" class="link link-primary block">{{ truncate($entry->content, 80) }}</a>
                                <span class="text-xs text-base-content/60">{{ $entry->statusLabel() }} · {{ $entry->updated_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Letzte Kommentare --}}
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <h3 class="mb-3 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Neue Kommentare auf meinen Einträgen') }}</h3>
                @if ($user['recent_comments']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Noch keine Kommentare.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($user['recent_comments'] as $comment)
                            <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <div class="text-xs text-base-content/60">{{ optional($comment->user)->name ?? '—' }} · {{ $comment->created_at->diffForHumans() }}</div>
                                <a href="{{ route('diary.show', $comment->diary_entry_id) }}#comments" class="link block">{{ truncate($comment->body, 100) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Letzte Anhänge --}}
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <h3 class="mb-3 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Neue Anhänge auf meinen Einträgen') }}</h3>
                @if ($user['recent_attachments']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Noch keine Anhänge.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($user['recent_attachments'] as $att)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <a href="{{ route('diary.show', $att->attachable_id) }}#attachments" class="link link-primary break-all">📎 {{ $att->original_name }}</a>
                                <span class="text-xs text-base-content/60">{{ optional($att->uploader)->name ?? '—' }} · {{ $att->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        @if ($team)
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <h3 class="mb-3 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Letzte Team-Aktivität') }}</h3>
                @if ($team['recent_activity']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Noch keine Aktivität.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($team['recent_activity'] as $comment)
                            <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <div class="text-xs text-base-content/60">{{ optional($comment->user)->name ?? '—' }} · {{ $comment->created_at->diffForHumans() }}</div>
                                <a href="{{ route('diary.show', $comment->diary_entry_id) }}#comments" class="link block">{{ truncate($comment->body, 120) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
@endsection
