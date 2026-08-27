{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : open-issues.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Meine offenen Punkte" — Daten: OpenIssuesWidget.
--}}
<x-card :title="__('Meine offenen Punkte')" icon="flag" :count="$kpi['open_issues_assigned'] ?? 0">
    <x-slot:actions>
        <span class="text-xs text-muted">
            {{ __('Von mir erstellt, offen') }}: {{ $kpi['open_issues_created'] ?? 0 }}
        </span>
    </x-slot:actions>

    @if ($issues->isEmpty())
        <x-empty-state compact icon="flag"
                       :title="__('Alles erledigt')" :message="__('Keine offenen Punkte zugewiesen.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($issues as $issue)
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
