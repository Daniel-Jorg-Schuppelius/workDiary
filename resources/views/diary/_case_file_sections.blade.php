{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _case_file_sections.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Datenteil der Fallakte (Kopf bis Timeline) für beide Fassungen:
  diary/case-file (Bildschirm/Druck) und diary/case-file-pdf (PDF).
  Erwartet: die Daten aus DiaryCaseFileController::caseFileData() und
            $static (bool) — true = PDF-Fassung: keine Links, Formulare und
            Tooltips, dafür Intern-Hinweis und die Klassen des PDF-Stylesheets.
--}}
@php
    /** @var \App\Models\Diary\DiaryEntry $diary */
    /** @var bool $static */
    $fmtMinutes = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
@endphp
{{-- Kopf --}}
<h1>{{ __('timeline.title.case_file') }}: {{ $diary->title ?: \CommonToolkit\Helper\Data\StringHelper::truncate($diary->content, 80) }}</h1>
<div class="meta">
    @if ($static)
        {{ __('timeline.case.internal_notice') }}<br>
    @endif
    {{ __('timeline.case.generated_at') }} {{ $generatedAt->fdatetime() }}
    — {{ __('timeline.case.order') }} #{{ $diary->id }}
    — {{ __('timeline.case.status') }}: {{ $diary->statusLabel() }}
</div>

{{-- Stammdaten --}}
<h2>{{ __('timeline.case.master_data') }}</h2>
<table class="kv">
    <tr><th>{{ __('timeline.case.customer') }}</th><td>{{ $diary->customer?->name ?? '—' }}@if ($diary->customer?->company) — {{ $diary->customer->company }}@endif</td></tr>
    <tr><th>{{ __('timeline.case.project') }}</th><td>{{ $diary->project?->name ?? '—' }}</td></tr>
    <tr><th>{{ __('timeline.case.status') }}</th><td>{{ $diary->statusLabel() }}</td></tr>
    <tr><th>{{ __('timeline.case.period') }}</th><td>{{ $diary->start_at?->fdatetime() ?? '—' }} – {{ $diary->end_at?->fdatetime() ?? '—' }}</td></tr>
    <tr><th>{{ __('timeline.case.responsible') }}</th><td>{{ $diary->user?->name ?? '—' }}@if ($diary->assignedUser) · {{ __('timeline.case.assigned_to') }}: {{ $diary->assignedUser->name }}@endif</td></tr>
    @if ($diary->entryType)
        <tr><th>{{ __('timeline.case.type') }}</th><td>{{ $diary->entryType->label }}</td></tr>
    @endif
    @if ($diary->tags->isNotEmpty())
        <tr><th>{{ __('timeline.case.tags') }}</th><td>{{ $diary->tags->pluck('name')->map(fn($n) => '#' . $n)->implode(' ') }}</td></tr>
    @endif
    <tr><th>{{ __('timeline.case.created_by') }}</th><td>{{ $diary->user?->name ?? '—' }} · {{ $diary->created_at->fdatetime() }}</td></tr>
    <tr><th>{{ __('timeline.case.description') }}</th><td class="pre">{{ $diary->content }}</td></tr>
    @if ($diary->response)
        <tr><th>{{ __('timeline.case.response') }}</th><td class="pre">{{ $diary->response }}</td></tr>
    @endif
</table>

{{-- Zeiten --}}
@if ($timeEntries->isNotEmpty())
    <h2>{{ __('timeline.case.times') }}@if (! $seesAllTimes) ({{ __('nur eigene Zeiten') }})@endif</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.date') }}</th>
                <th>{{ __('timeline.case.person') }}</th>
                <th>{{ __('timeline.case.duration') }}</th>
                <th>{{ __('timeline.case.billable') }}</th>
                <th>{{ __('timeline.case.description') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($timeEntries as $timeEntry)
                <tr>
                    <td>{{ $timeEntry->date?->fdate() ?? '—' }}</td>
                    <td>{{ $timeEntry->user?->name ?? '—' }}</td>
                    <td>{{ $fmtMinutes((int) $timeEntry->minutes) }}</td>
                    <td>{{ $timeEntry->billable ? __('timeline.case.yes') : __('timeline.case.no') }}</td>
                    <td>{{ $timeEntry->description ?? '—' }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="sum" colspan="2">{{ __('timeline.case.total') }}</td>
                <td class="sum">{{ $fmtMinutes($totalMinutes) }}</td>
                <td class="sum" colspan="2">{{ __('timeline.case.billable') }}: {{ $fmtMinutes($billableMinutes) }}</td>
            </tr>
        </tbody>
    </table>
@endif

{{-- Material --}}
@if ($materials->isNotEmpty())
    <h2>{{ __('timeline.case.material') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.quantity') }}</th>
                <th>{{ __('timeline.case.description') }}</th>
                <th>{{ __('timeline.case.billed') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($materials as $usage)
                <tr>
                    <td>{{ rtrim(rtrim((string) $usage->quantity?->getNumericValue(), '0'), '.') }} {{ $usage->unit }}</td>
                    <td>{{ $usage->description }}</td>
                    <td>{{ $usage->billed ? __('timeline.case.yes') : __('timeline.case.no') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Protokolle & Abnahmen --}}
@if ($protocols->isNotEmpty())
    <h2>@if ($static){{ __('timeline.case.protocols') }}@else<x-term glossary="abnahme">{{ __('timeline.case.protocols') }}</x-term>@endif</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.date') }}</th>
                <th>{{ __('timeline.case.title_col') }}</th>
                <th>{{ __('timeline.case.status') }}</th>
                <th>{{ __('timeline.case.signature_state') }}</th>
                <th>{{ __('weather.block.title') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($protocols as $protocol)
                <tr>
                    <td>{{ $protocol->occurred_at?->fdatetime() ?? '—' }}</td>
                    <td>
                        @if ($static)
                            {{ $protocol->title }}
                        @else
                            <a class="link link-hover" href="{{ route('protocols.show', $protocol) }}">{{ $protocol->title }}</a>
                        @endif
                        @if ($protocol->tags->isNotEmpty())
                            <div class="small">{{ $protocol->tags->pluck('name')->map(fn ($n) => '#' . $n)->implode(' ') }}</div>
                        @endif
                    </td>
                    <td><x-status-badge tone="plain" size="md">{{ $protocol->status->label() }}</x-status-badge></td>
                    <td>
                        @if ($protocol->signed_at)
                            {{ __('timeline.case.signed_at') }} {{ $protocol->signed_at->fdatetime() }} ({{ $protocol->signatures_count }})
                        @else
                            {{ __('timeline.case.unsigned') }}
                        @endif
                    </td>
                    {{-- Wetter des Protokolltags (Feature 062 → Rang 10): Snapshot als
                         Beweiswert; sonst am Bildschirm der Abruf-Button (idempotent)
                         für Berechtigte. --}}
                    <td>
                        @php $ws = $protocol->weatherSnapshot; @endphp
                        @if ($ws)
                            {{ $ws->temp_min }}–{{ $ws->temp_max }} °C · {{ $ws->precipitation_mm }} mm · {{ $ws->wind_gust_kmh }} km/h
                            <div class="small">{{ __('weather.source') }}: {{ \App\Support\Trans::or('weather.providers.' . $ws->provider, $ws->provider) }} · {{ $ws->fetched_at?->fdatetime() }}</div>
                        @elseif (! $static && auth()->user()?->can('update', $protocol))
                            <form method="POST" action="{{ route('protocols.weather', $protocol) }}">
                                @csrf
                                <x-button type="submit" tone="ghost" size="xs">{{ __('weather.attach.button') }}</x-button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Offene Punkte --}}
@if ($openIssues->isNotEmpty())
    <h2>{{ __('timeline.case.open_issues') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.title_col') }}</th>
                <th>{{ __('timeline.case.severity') }}</th>
                <th>{{ __('timeline.case.status') }}</th>
                <th>{{ __('timeline.case.assigned_to') }}</th>
                <th>{{ __('timeline.case.due') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($openIssues as $issue)
                <tr>
                    <td>{{ $issue->title }}</td>
                    <td>{{ $issue->severity->label() }}</td>
                    <td><x-status-badge tone="plain" size="md">{{ $issue->status->label() }}</x-status-badge></td>
                    <td>{{ $issue->assignee?->name ?? '—' }}</td>
                    <td>{{ $issue->due_at?->fdate() ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Dienstmittel/Assets (Feature 009 Akzeptanz 1; Vollaudit 2026-07, M5). --}}
@if ($diary->asset !== null || $assetAssignments->isNotEmpty())
    <h2>{{ __('timeline.case.assets') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.asset') }}</th>
                <th>{{ __('timeline.case.asset_role') }}</th>
                <th>{{ __('timeline.case.person') }}</th>
                <th>{{ __('timeline.case.date') }}</th>
            </tr>
        </thead>
        <tbody>
            @if ($diary->asset !== null)
                <tr>
                    <td>@if ($static){{ $diary->asset->name }}@else<a href="{{ route('assets.show', $diary->asset) }}">{{ $diary->asset->name }}</a>@endif</td>
                    <td>{{ __('timeline.case.asset_subject') }}</td>
                    <td>—</td>
                    <td>—</td>
                </tr>
            @endif
            @foreach ($assetAssignments as $assignment)
                <tr>
                    @if ($static)
                        <td>{{ $assignment->asset->name ?? '—' }}</td>
                    @else
                        <td>
                            @if ($assignment->asset !== null)
                                <a href="{{ route('assets.show', $assignment->asset) }}">{{ $assignment->asset->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    <td>{{ __('timeline.case.asset_issued') }}</td>
                    <td>{{ $assignment->assignedToUser?->name ?? '—' }}</td>
                    <td>{{ $assignment->checked_out_at?->fdate() ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Kommunikation (ohne confidential, außer berechtigt) --}}
@if ($communicationNotes->isNotEmpty())
    <h2>{{ __('timeline.case.communication') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.date') }}</th>
                <th>{{ __('timeline.case.type') }}</th>
                <th>{{ __('timeline.case.subject') }}</th>
                <th>{{ __('timeline.case.person') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($communicationNotes as $note)
                <tr>
                    <td>{{ $note->occurred_at?->fdatetime() ?? '—' }}</td>
                    <td>{{ $note->type->label() }}</td>
                    <td>{{ $note->subject }}</td>
                    <td>{{ $note->creator?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Dokumente --}}
@if ($documents->isNotEmpty())
    <h2>{{ __('timeline.case.documents') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.title_col') }}</th>
                <th>{{ __('timeline.case.type') }}</th>
                <th>{{ __('timeline.case.status') }}</th>
                <th>{{ __('timeline.case.created_by') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($documents as $document)
                <tr>
                    <td>{{ $document->title }}</td>
                    <td>{{ $document->document_type->label() }}</td>
                    <td><x-status-badge tone="plain" size="md">{{ $document->effectiveStatus()->label() }}</x-status-badge></td>
                    <td>{{ $document->creator?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Anhänge (auch interne — Unterschied zum Portal-PDF) --}}
@if ($diary->attachments->isNotEmpty())
    <h2>{{ __('timeline.case.attachments') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.date') }}</th>
                <th>{{ __('timeline.case.title_col') }}</th>
                <th>{{ __('timeline.case.person') }}</th>
                <th>{{ __('Kundenportal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($diary->attachments as $attachment)
                <tr>
                    <td>{{ $attachment->created_at?->fdatetime() ?? '—' }}</td>
                    <td>{{ $attachment->original_name }}</td>
                    <td>{{ $attachment->uploader?->name ?? '—' }}</td>
                    <td>
                        {{-- Kundenfreigabe (Rang 54) + Bestätigungsstatus (Rang 55). --}}
                        @if (! $static && auth()->user()?->can('update', $diary))
                            <form method="POST" action="{{ route('attachments.customer-visibility', $attachment) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <x-button type="submit" tone="ghost" size="xs">
                                    {{ $attachment->customer_visible ? __('Freigegeben') : __('Intern') }}
                                </x-button>
                            </form>
                        @else
                            {{ $attachment->customer_visible ? __('Freigegeben') : __('Intern') }}
                        @endif
                        @php $confirmation = $attachment->confirmations->first(); @endphp
                        @if ($confirmation !== null)
                            · {{ __('Vom Kunden bestätigt am :date', ['date' => $confirmation->confirmed_at->fdate()]) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Kommentare (intern — im Portal-PDF nie enthalten) --}}
@if ($diary->comments->isNotEmpty())
    <h2>{{ __('timeline.case.comments') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.date') }}</th>
                <th>{{ __('timeline.case.person') }}</th>
                <th>{{ __('timeline.case.description') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($diary->comments as $comment)
                <tr>
                    <td>{{ $comment->created_at?->fdatetime() ?? '—' }}</td>
                    <td>{{ $comment->user?->name ?? '—' }}</td>
                    <td class="pre">{{ $comment->body }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Vollständige Timeline --}}
<h2>{{ __('timeline.title.section') }}</h2>
@if (empty($timelineItems))
    {{-- raw-markup-ok: Standalone-Seite mit eigenem Stylesheet, ohne App-CSS --}}
    <p class="muted">{{ __('timeline.empty') }}</p>
@else
    <table>
        <thead>
            <tr>
                <th>{{ __('timeline.case.date') }}</th>
                <th>{{ __('timeline.case.event') }}</th>
                <th>{{ __('timeline.case.person') }}</th>
                <th>{{ __('timeline.case.details') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($timelineItems as $item)
                <tr>
                    <td>{{ $item->occurredAt?->fdatetime() ?? '—' }}</td>
                    <td>{{ $item->title }}</td>
                    <td>{{ $item->actor ?? __('timeline.actor_system') }}</td>
                    <td>{{ $item->summary ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
