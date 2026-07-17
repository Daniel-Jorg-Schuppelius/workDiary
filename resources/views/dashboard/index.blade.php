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

    <x-page-shell>

        {{-- Hero-Header --}}
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-box bg-base-200 text-base-content/70 sm:flex">
                        <x-icon name="waving_hand" size="1.75rem" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="font-['Space_Grotesk'] text-2xl font-bold tracking-tight truncate">{{ __('Hallo') }}, {{ Auth::user()->name }}</h1>
                        <p class="text-sm text-base-content/60">{{ $now->translatedFormat('l, d.m.Y H:i') }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-icon-btn icon="calendar_view_week" size="sm" :href="route('week.index')" show-label>{{ __('Wochenansicht') }}</x-icon-btn>
                    <x-icon-btn icon="menu_book" size="sm" :href="route('diary.index')" show-label>{{ __('Auftragsbuch') }}</x-icon-btn>
                    <x-icon-btn icon="tune" size="sm" :href="route('dashboard.customize')" show-label>{{ __('Anpassen') }}</x-icon-btn>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('diary.create')"
                                show-label>{{ __('Neuer Eintrag') }}</x-icon-btn>
                </div>
            </div>
        </x-card>

        {{-- Eigene Widgets (Phase G) --}}
        @php
            /** @var \App\Dashboard\WidgetRegistry $widgetRegistry */
            $widgetRegistry = app(\App\Dashboard\WidgetRegistry::class);
            $widgetUser = Auth::user();
            $widgetAvailable = $widgetRegistry->availableFor($widgetUser);
            $widgetConfig = $widgetUser->dashboardWidgets()->get()->keyBy('widget_key');
            $widgetsToRender = $widgetAvailable
                ->map(function ($w) use ($widgetConfig) {
                    $stored = $widgetConfig->get($w->key());
                    return [
                        'widget' => $w,
                        'sort_order' => $stored?->sort_order ?? 999,
                        'hidden' => (bool) ($stored?->hidden ?? false),
                    ];
                })
                ->reject(fn (array $i) => $i['hidden'])
                ->sortBy(fn (array $i) => [$i['sort_order'], $i['widget']->label()])
                ->values();
        @endphp

        @if ($widgetsToRender->isNotEmpty())
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($widgetsToRender as $entry)
                    {{ $entry['widget']->render($widgetUser) }}
                @endforeach
            </div>
        @endif

        @isset($onboarding)
            <x-onboarding-widget
                :checklist="$onboarding['checklist']"
                :widget-dismissed-at="$onboarding['widget_dismissed_at']" />
        @endisset

        {{-- KPI-Kacheln (immer sichtbar) --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-kpi-tile :label="__('Meine offenen Einträge')" :value="$user['kpi']['open_entries']" />
            <x-kpi-tile :label="__('In Bearbeitung')" :value="$user['kpi']['progress_entries']" />
            <x-kpi-tile :label="__('Anstehende Schichten')" :value="$user['kpi']['upcoming_shifts']" />
            <x-kpi-tile :label="__('Anstehende Notdienste')" :value="$user['kpi']['upcoming_emergencies']" />
        </div>

        {{-- Tabs --}}
        <div x-data="tabs('overview')" data-tab-persist="wd-dash-tab"
             class="space-y-4">
            <div role="tablist" class="tabs tabs-box flex-nowrap w-full overflow-x-auto">
                <button type="button" role="tab" class="tab gap-1.5 whitespace-nowrap" :class="tabClass('overview')" @click="setTab('overview')">
                    <x-icon name="dashboard" /> <span>{{ __('Überblick') }}</span>
                </button>
                <button type="button" role="tab" class="tab gap-1.5 whitespace-nowrap" :class="tabClass('tasks')" @click="setTab('tasks')">
                    <x-icon name="checklist" /> <span>{{ __('Aufgaben') }}</span>
                </button>
                <button type="button" role="tab" class="tab gap-1.5 whitespace-nowrap" :class="tabClass('activity')" @click="setTab('activity')">
                    <x-icon name="forum" /> <span>{{ __('Aktivität') }}</span>
                </button>
                <button type="button" role="tab" class="tab gap-1.5 whitespace-nowrap" :class="tabClass('finance')" @click="setTab('finance')">
                    <x-icon name="payments" /> <span>{{ __('Finanzen & Reisen') }}</span>
                </button>
            </div>

            {{-- ── Tab: Überblick ───────────────────────────────────────────── --}}
            <div x-show="isTab('overview')" x-cloak class="space-y-4">
                @if ($team)
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <x-kpi-tile :label="__('Offen (Team)')" :value="$team['kpi']['open_entries']" tone="info" />
                        <x-kpi-tile :label="__('In Bearbeitung (Team)')" :value="$team['kpi']['progress_entries']" tone="info" />
                        <x-kpi-tile :label="__('Heute archiviert')" :value="$team['kpi']['archived_today']" tone="info" />
                        <x-kpi-tile :label="__('Mitarbeitende')" :value="$team['kpi']['user_count']" tone="info" />
                    </div>
                @endif

                <div class="grid gap-4 lg:grid-cols-2">
                    <x-card>
                        <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                            <x-icon name="today" class="text-primary" /> {{ __('Heute') }}
                        </h3>
                        @if ($user['today_shifts']->isEmpty())
                            <x-empty-state compact icon='<span class="material-symbols-outlined">event_available</span>'
                                           :title="__('Keine Schicht heute')" :message="__('Keine Schicht heute.')" />
                        @else
                            <ul class="space-y-2">
                                @foreach ($user['today_shifts'] as $shift)
                                    <li class="flex items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2 text-sm">
                                        <span class="inline-flex items-center gap-1"><x-icon name="event" /> {{ $shift->start_at->ftime() }} – {{ $shift->end_at->ftime() }}</span>
                                        <span class="text-base-content/60">{{ $shift->note ? \CommonToolkit\Helper\Data\StringHelper::truncate($shift->note, 40) : '' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                            <x-icon name="event_upcoming" class="text-primary" /> {{ __('Nächste Schichten') }}
                        </h3>
                        @if ($user['upcoming_shifts']->isEmpty())
                            <x-empty-state compact icon='<span class="material-symbols-outlined">event_busy</span>'
                                           :title="__('Keine geplanten Schichten')" :message="__('Keine geplanten Schichten.')" />
                        @else
                            <ul class="space-y-2 text-sm">
                                @foreach ($user['upcoming_shifts'] as $shift)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                        <span>{{ $shift->start_at->format('d.m. H:i') }} – {{ $shift->end_at->format('d.m. H:i') }}</span>
                                        @if ($shift->note)<span class="text-base-content/60">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($shift->note, 50) }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                            <x-icon name="emergency" class="text-primary" /> {{ __('Nächste Notdienste') }}
                        </h3>
                        @if ($user['upcoming_emergencies']->isEmpty())
                            <x-empty-state compact icon='<span class="material-symbols-outlined">crisis_alert</span>'
                                           :title="__('Keine geplanten Notdienste')" :message="__('Keine geplanten Notdienste.')" />
                        @else
                            <ul class="space-y-2 text-sm">
                                @foreach ($user['upcoming_emergencies'] as $em)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                        <span class="inline-flex items-center gap-1"><x-icon name="priority_high" /> {{ $em->start_at->format('d.m. H:i') }} – {{ $em->end_at->format('d.m. H:i') }}</span>
                                        @if ($em->reason)<span class="text-base-content/60">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($em->reason, 50) }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>

                    @if (isset($user['upcoming_scheduled']))
                        <x-card>
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <h3 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                                    <x-icon name="calendar_month" class="text-primary" /> {{ __('Nächste geplante Schichten') }}
                                </h3>
                                <x-button href="{{ route('schedule.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
                            </div>
                            @if ($user['upcoming_scheduled']->isEmpty())
                                <x-empty-state compact icon='<span class="material-symbols-outlined">calendar_month</span>'
                                               :title="__('Nichts geplant')" :message="__('Keine geplanten Schichten in den nächsten 7 Tagen.')" />
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
                        </x-card>
                    @endif
                </div>
            </div>

            {{-- ── Tab: Aufgaben ────────────────────────────────────────────── --}}
            <div x-show="isTab('tasks')" x-cloak class="grid gap-4 lg:grid-cols-2">
                @if (isset($user['open_issues_assigned']))
                    <x-card>
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h3 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                                <x-icon name="flag" class="text-warning" /> {{ __('Meine offenen Punkte') }}
                                <span class="font-normal text-base-content/50">({{ $user['kpi']['open_issues_assigned'] ?? 0 }})</span>
                            </h3>
                            <span class="text-xs text-base-content/60">
                                {{ __('Von mir erstellt, offen') }}: {{ $user['kpi']['open_issues_created'] ?? 0 }}
                            </span>
                        </div>
                        @if ($user['open_issues_assigned']->isEmpty())
                            <x-empty-state compact icon='<span class="material-symbols-outlined">flag</span>'
                                           :title="__('Alles erledigt')" :message="__('Keine offenen Punkte zugewiesen.')" />
                        @else
                            <ul class="space-y-2 text-sm">
                                @foreach ($user['open_issues_assigned'] as $issue)
                                    @php
                                        $subjectRoute = null;
                                        if ($issue->subject_type === \App\Models\DiaryEntry::class) {
                                            $subjectRoute = route('diary.show', $issue->subject_id) . '#open-issues';
                                        } elseif ($issue->subject_type === \App\Models\SafetyEvent::class && $issue->subject) {
                                            $subjectRoute = route('safety-events.show', $issue->subject) . '#open-issues';
                                        }
                                        $issTone = ['open' => 'warning', 'inProgress' => 'info', 'blocked' => 'error', 'reopened' => 'ghost'][$issue->status->value] ?? 'ghost';
                                        $issSevTone = ['critical' => 'error', 'high' => 'warning'][$issue->severity->value] ?? 'ghost';
                                    @endphp
                                    <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-status-badge size="xs" :tone="$issTone">{{ $issue->status->label() }}</x-status-badge>
                                            <x-status-badge size="xs" :tone="$issSevTone">{{ $issue->severity->label() }}</x-status-badge>
                                            @if ($issue->due_at)
                                                <x-status-badge size="xs" :tone="$issue->due_at->isPast() ? 'error' : 'ghost'">{{ $issue->due_at->fdate() }}</x-status-badge>
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
                    </x-card>
                @endif

                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                        <x-icon name="history_edu" class="text-warning" /> {{ __('Meine letzten Einträge') }}
                    </h3>
                    @if ($user['recent_entries']->isEmpty())
                        <x-empty-state compact icon='<span class="material-symbols-outlined">edit_note</span>'
                                       :title="__('Noch keine Einträge')" :message="__('Noch keine Einträge.')" />
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($user['recent_entries'] as $entry)
                                <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                    <a href="{{ route('diary.show', $entry) }}" class="link link-primary block">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->content, 80) }}</a>
                                    <span class="text-xs text-base-content/60">{{ $entry->statusLabel() }} · {{ $entry->updated_at->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>

            {{-- ── Tab: Aktivität ───────────────────────────────────────────── --}}
            <div x-show="isTab('activity')" x-cloak class="grid gap-4 lg:grid-cols-2">
                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                        <x-icon name="comment" class="text-info" /> {{ __('Neue Kommentare auf meinen Einträgen') }}
                    </h3>
                    @if ($user['recent_comments']->isEmpty())
                        <x-empty-state compact icon='<span class="material-symbols-outlined">comment</span>'
                                       :title="__('Keine Kommentare')" :message="__('Noch keine Kommentare.')" />
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($user['recent_comments'] as $comment)
                                <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                    <div class="text-xs text-base-content/60">{{ optional($comment->user)->name ?? '—' }} · {{ $comment->created_at->diffForHumans() }}</div>
                                    <a href="{{ route('diary.show', $comment->commentable_id) }}#comments" class="link block">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($comment->body, 100) }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                        <x-icon name="attach_file" class="text-info" /> {{ __('Neue Anhänge auf meinen Einträgen') }}
                    </h3>
                    @if ($user['recent_attachments']->isEmpty())
                        <x-empty-state compact icon='<span class="material-symbols-outlined">attach_file</span>'
                                       :title="__('Keine Anhänge')" :message="__('Noch keine Anhänge.')" />
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($user['recent_attachments'] as $att)
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                    <a href="{{ route('diary.show', $att->attachable_id) }}#attachments" class="link link-primary break-all"><x-icon name="attachment" class="align-middle" /> {{ $att->original_name }}</a>
                                    <span class="text-xs text-base-content/60">{{ optional($att->uploader)->name ?? '—' }} · {{ $att->created_at->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                @if ($team)
                    <x-card class="lg:col-span-2">
                        <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                            <x-icon name="groups" class="text-info" /> {{ __('Letzte Team-Aktivität') }}
                        </h3>
                        @if ($team['recent_activity']->isEmpty())
                            <x-empty-state compact icon='<span class="material-symbols-outlined">groups</span>'
                                           :title="__('Keine Aktivität')" :message="__('Noch keine Aktivität.')" />
                        @else
                            <ul class="space-y-2 text-sm">
                                @foreach ($team['recent_activity'] as $comment)
                                    <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                        <div class="text-xs text-base-content/60">{{ optional($comment->user)->name ?? '—' }} · {{ $comment->created_at->diffForHumans() }}</div>
                                        <a href="{{ route('diary.show', $comment->commentable_id) }}#comments" class="link block">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($comment->body, 120) }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                @endif
            </div>

            {{-- ── Tab: Finanzen & Reisen ───────────────────────────────────── --}}
            <div x-show="isTab('finance')" x-cloak class="space-y-4">
                <p class="font-['Space_Grotesk'] text-sm font-semibold uppercase tracking-[0.2em] text-base-content/60">
                    {{ __('Monat') }} · {{ $finance['month']['label'] ?? '' }}
                </p>
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
            </div>
        </div>
    </x-page-shell>
@endsection
