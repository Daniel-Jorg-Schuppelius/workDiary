@extends('layouts.app')
@section('title', __('Dashboard') . ' — WorkDiary')
@section('nav-title', __('Dashboard'))

@section('content')
    @php
        /** @var \Carbon\CarbonImmutable $now */
        /** @var array $user */
        /** @var array|null $team */
        /** @var array $finance */
        /** @var array|null $onboarding */
    @endphp

    <x-page-shell gap="6">

        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-['Space_Grotesk'] text-2xl font-bold">{{ __('Hallo') }}, {{ Auth::user()->name }}</h1>
                <p class="text-sm text-base-content/60">{{ $now->translatedFormat('l, d.m.Y H:i') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-icon-btn icon="calendar_view_week" size="sm" :href="route('week.index')" show-label>{{ __('Wochenansicht') }}</x-icon-btn>
                <x-icon-btn icon="menu_book" size="sm" :href="route('diary.index')" show-label>{{ __('Tagebuch') }}</x-icon-btn>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('diary.create')"
                            show-label>{{ __('Neuer Eintrag') }}</x-icon-btn>
            </div>
        </div>

        @isset($onboarding)
            <x-onboarding-widget
                :checklist="$onboarding['checklist']"
                :widget-dismissed-at="$onboarding['widget_dismissed_at']" />
        @endisset

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

        {{-- Finanz & Reise (Monat-to-Date) --}}
        <section>
            <h2 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold uppercase tracking-[0.2em] text-base-content/60">
                {{ __('Finanzen & Reisen') }} · {{ $finance['month']['label'] ?? '' }}
            </h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Spesen eingereicht (Brutto)') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums">
                        {{ number_format((float) ($finance['month']['expenses_submitted_gross'] ?? 0), 2, ',', '.') }} €
                    </p>
                </div>
                <div class="rounded-box border border-success/40 bg-success/5 p-4">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Davon erstattet') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums text-success">
                        {{ number_format((float) ($finance['month']['expenses_reimbursed_gross'] ?? 0), 2, ',', '.') }} €
                    </p>
                </div>
                <div class="rounded-box border border-warning/40 bg-warning/5 p-4">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Spesen ausstehend / Entwurf') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">
                        <span class="text-warning">{{ $finance['month']['expenses_pending_count'] ?? 0 }}</span>
                        <span class="text-base-content/40 text-base font-normal">/</span>
                        <span class="opacity-70">{{ $finance['month']['expenses_draft_count'] ?? 0 }}</span>
                    </p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Reisen (Monat) / Entwürfe') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">
                        {{ $finance['month']['trips_count'] ?? 0 }}
                        <span class="text-base-content/40 text-base font-normal">/</span>
                        <span class="opacity-70">{{ $finance['month']['trip_drafts'] ?? 0 }}</span>
                    </p>
                </div>
                <div class="rounded-box border border-info/40 bg-info/5 p-4">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Urlaub offen / genehmigt :year', ['year' => $now->year]) }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">
                        <span class="text-info">{{ $finance['vacation']['pending'] ?? 0 }}</span>
                        <span class="text-base-content/40 text-base font-normal">/</span>
                        <span class="opacity-70">{{ rtrim(rtrim(number_format((float) ($finance['vacation']['approved_days_this_year'] ?? 0), 1, ',', '.'), '0'), ',') }} {{ __('Tage') }}</span>
                    </p>
                </div>
                @if (! empty($finance['approver_pending']))
                    <div class="rounded-box border border-error/40 bg-error/5 p-4 md:col-span-3">
                        <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Genehmigungs-Stack (gesamt)') }}</p>
                        <p class="mt-1 font-['Space_Grotesk'] text-xl font-bold flex items-center gap-4">
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="receipt_long" class="text-base" />
                                <span>{{ $finance['approver_pending']['expenses'] }}</span>
                                <a href="{{ route('expense-approvals.inbox') }}" class="text-xs link link-hover opacity-70">{{ __('Spesen') }}</a>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="beach_access" class="text-base" />
                                <span>{{ $finance['approver_pending']['vacations'] }}</span>
                                <a href="{{ route('vacations.index') }}" class="text-xs link link-hover opacity-70">{{ __('Urlaub') }}</a>
                            </span>
                        </p>
                    </div>
                @endif
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
                                <span class="inline-flex items-center gap-1"><x-icon name="event" /> {{ $shift->start_at->format('H:i') }} – {{ $shift->end_at->format('H:i') }}</span>
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
                                <span class="inline-flex items-center gap-1"><x-icon name="priority_high" /> {{ $em->start_at->format('d.m. H:i') }} – {{ $em->end_at->format('d.m. H:i') }}</span>
                                @if ($em->reason)<span class="text-base-content/60">{{ truncate($em->reason, 50) }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Letzte eigene Einträge --}}
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">

            {{-- ── Schichtplan-Widget ── --}}
            @if (isset($user['upcoming_scheduled']))
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Nächste geplante Schichten') }}</h3>
                    <a href="{{ route('schedule.index') }}" class="btn btn-xs btn-ghost">{{ __('Alle →') }}</a>
                </div>
                @if ($user['upcoming_scheduled']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Keine geplanten Schichten in den nächsten 7 Tagen.') }}</p>
                @else
                    <ul class="space-y-1.5 text-sm">
                        @foreach ($user['upcoming_scheduled'] as $sshift)
                            <li class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-[0.65rem] font-bold text-white"
                                      style="background:{{ $sshift->shiftType?->color ?? '#6b7280' }};">
                                    {{ $sshift->shiftType?->abbreviation ?? '?' }}
                                </span>
                                <span class="font-medium">{{ \Carbon\Carbon::parse($sshift->date)->translatedFormat('D d.m.') }}</span>
                                @if ($sshift->resolvedStartTime())
                                    <span class="text-base-content/60">{{ $sshift->resolvedStartTime() }}{{ $sshift->resolvedEndTime() ? '–'.$sshift->resolvedEndTime() : '' }}</span>
                                @endif
                                @if ($sshift->shiftType)
                                    <span class="ml-auto text-xs text-base-content/50">{{ $sshift->shiftType->name }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @endif

            {{-- Meine offenen Punkte --}}
            @if (isset($user['open_issues_assigned']))
            <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <header class="mb-3 flex items-center justify-between">
                    <h3 class="font-['Space_Grotesk'] text-lg font-semibold">
                        {{ __('Meine offenen Punkte') }}
                        <span class="ml-1 text-sm font-normal text-base-content/60">
                            ({{ $user['kpi']['open_issues_assigned'] ?? 0 }})
                        </span>
                    </h3>
                    <span class="text-xs text-base-content/60">
                        {{ __('Von mir erstellt, offen') }}: {{ $user['kpi']['open_issues_created'] ?? 0 }}
                    </span>
                </header>
                @if ($user['open_issues_assigned']->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Keine offenen Punkte zugewiesen.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($user['open_issues_assigned'] as $issue)
                            @php
                                $subjectRoute = null;
                                if ($issue->subject_type === \App\Models\DiaryEntry::class) {
                                    $subjectRoute = route('diary.show', $issue->subject_id) . '#open-issues';
                                }
                            @endphp
                            <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span @class([
                                        'badge badge-xs',
                                        'badge-warning' => $issue->status->value === 'open',
                                        'badge-info' => $issue->status->value === 'inProgress',
                                        'badge-error' => $issue->status->value === 'blocked',
                                        'badge-ghost' => $issue->status->value === 'reopened',
                                    ])>{{ $issue->status->label() }}</span>
                                    <span @class([
                                        'badge badge-xs badge-outline',
                                        'badge-error' => $issue->severity->value === 'critical',
                                        'badge-warning' => $issue->severity->value === 'high',
                                    ])>{{ $issue->severity->label() }}</span>
                                    @if ($issue->due_at)
                                        <span @class([
                                            'badge badge-xs',
                                            'badge-error' => $issue->due_at->isPast(),
                                            'badge-ghost' => ! $issue->due_at->isPast(),
                                        ])>{{ $issue->due_at->format('d.m.Y') }}</span>
                                    @endif
                                </div>
                                @if ($subjectRoute)
                                    <a href="{{ $subjectRoute }}" class="link link-primary block">{{ $issue->title }}</a>
                                @else
                                    <span class="block font-medium">{{ $issue->title }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @endif

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
                                <a href="{{ route('diary.show', $comment->commentable_id) }}#comments" class="link block">{{ truncate($comment->body, 100) }}</a>
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
                                <a href="{{ route('diary.show', $comment->commentable_id) }}#comments" class="link block">{{ truncate($comment->body, 120) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </x-page-shell>
@endsection
