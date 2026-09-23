<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubAttendanceSheetStatus, ClubAttendanceStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{AddSpontaneousAttendeeRequest, CorrectAttendanceRecordRequest, SaveAttendanceSheetRequest};
use App\Models\Calendar\Event;
use App\Models\Club\{ClubAttendanceRecord, ClubAttendanceSheet, ClubGroup, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\ClubAttendanceService;
use App\Services\UI\DateRangeContext;
use App\Support\{CarbonFmt, CsvExport, Sqid};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Anwesenheit je Vereinstermin (Feature 159, MVP-844): mobile Liste mit
 * Sammelerfassung, Bestätigung, begründeten Korrekturen und spontanen
 * Ergänzungen; Nachweisliste mit CSV-Export für den Header-Zeitraum.
 * Fachlogik im ClubAttendanceService.
 */
class ClubAttendanceController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubAttendanceService $attendance,
    ) {}

    public function show(Event $event): View {
        $details = $event->clubDetails()->first();
        abort_if($details === null, 404);
        Gate::authorize('view', $details);

        $sheet = ClubAttendanceSheet::query()->where('event_id', $event->id)->first();
        $canRecord = $sheet !== null ? Gate::allows('record', $sheet) : Gate::allows('manageParticipants', $details);
        if ($sheet === null && $canRecord) {
            $sheet = $this->attendance->sheetFor($event);
        }
        if ($sheet !== null) {
            Gate::authorize('view', $sheet);
        }

        $event->load(['clubGroups:id,name', 'responsibleUser:id,name']);
        $roster = $sheet !== null ? $this->attendance->rosterFor($sheet, $event) : collect();
        $records = $sheet !== null
            ? $sheet->records()->with(['overlapEvent:id,title,started_at', 'revisions' => fn($q) => $q->with('actor:id,name')])->get()->keyBy('club_member_id')
            : collect();

        return view('club.attendance.sheet', [
            'event' => $event,
            'sheet' => $sheet,
            'roster' => $roster,
            'records' => $records,
            'confirmations' => $sheet?->confirmations()->with('confirmedBy:id,name')->get() ?? collect(),
            'maxMinutes' => $sheet !== null ? $sheet->maxMinutes($event) : null,
            'eventMinutes' => $sheet !== null ? $sheet->eventMinutes($event) : null,
            'canRecord' => $sheet !== null && Gate::allows('record', $sheet),
            'statuses' => ClubAttendanceStatus::cases(),
        ]);
    }

    public function save(SaveAttendanceSheetRequest $request, Event $event): RedirectResponse {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $rows = [];
        foreach ((array) ($data['records'] ?? []) as $sqid => $row) {
            $memberId = Sqid::decodeOrNumeric(ClubMember::class, (string) $sqid);
            if ($memberId !== null) {
                $rows[$memberId] = (array) $row;
            }
        }
        $conducted = array_key_exists('conducted_minutes', $data) && $data['conducted_minutes'] !== null ? (int) $data['conducted_minutes'] : null;
        $this->attendance->saveRows($sheet, $rows, $actor, (int) $data['version'], $conducted);

        return redirect()
            ->route('club.events.attendance.show', $event)
            ->with('success', __('club.attendance.flash.saved'));
    }

    public function confirm(Request $request, Event $event): RedirectResponse {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);

        $data = $request->validate(['version' => ['required', 'integer', 'min:0']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->attendance->confirm($sheet, $actor, (int) $data['version']);

        return redirect()
            ->route('club.events.attendance.show', $event)
            ->with('success', __('club.attendance.flash.confirmed'));
    }

    public function reopenDialog(Event $event): View {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);

        return view('club.attendance._reopen_dialog', ['event' => $event, 'sheet' => $sheet]);
    }

    public function reopen(Request $request, Event $event): RedirectResponse {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);

        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->attendance->reopen($sheet, $actor, (string) $data['reason'], (int) $data['version']);

        return redirect()
            ->route('club.events.attendance.show', $event)
            ->with('success', __('club.attendance.flash.reopened'));
    }

    public function editRecord(Event $event, ClubAttendanceRecord $record): View {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);
        abort_unless($record->club_attendance_sheet_id === $sheet->id, 404);

        return view('club.attendance._record_dialog', [
            'event' => $event,
            'sheet' => $sheet,
            'record' => $record->load(['member', 'revisions.actor:id,name']),
            'maxMinutes' => $sheet->maxMinutes($event),
            'statuses' => ClubAttendanceStatus::cases(),
        ]);
    }

    public function updateRecord(CorrectAttendanceRecordRequest $request, Event $event, ClubAttendanceRecord $record): RedirectResponse {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);
        abort_unless($record->club_attendance_sheet_id === $sheet->id, 404);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $row = [
            'status' => (string) $data['status'],
            'minutes' => isset($data['minutes']) ? (int) $data['minutes'] : null,
            'arrived_at' => isset($data['arrived_at']) ? (string) $data['arrived_at'] : null,
            'left_at' => isset($data['left_at']) ? (string) $data['left_at'] : null,
        ];
        $this->attendance->correct($record, $row, $actor, isset($data['reason']) ? (string) $data['reason'] : null, (int) $data['version']);

        return redirect()
            ->route('club.events.attendance.show', $event)
            ->with('success', __('club.attendance.flash.corrected'));
    }

    public function overlapDialog(Event $event, ClubAttendanceRecord $record): View {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);
        abort_unless($record->club_attendance_sheet_id === $sheet->id, 404);

        return view('club.attendance._overlap_dialog', ['event' => $event, 'record' => $record->load(['member', 'overlapEvent:id,title,started_at,ended_at'])]);
    }

    public function clearOverlap(Request $request, Event $event, ClubAttendanceRecord $record): RedirectResponse {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);
        abort_unless($record->club_attendance_sheet_id === $sheet->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->attendance->clearOverlap($record, $actor, (string) $data['reason']);

        return redirect()
            ->route('club.events.attendance.show', $event)
            ->with('success', __('club.attendance.flash.overlap_cleared'));
    }

    public function spontaneousDialog(Event $event): View {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);

        $inRoster = $this->attendance->rosterFor($sheet, $event)->pluck('id');
        $candidates = ClubMember::query()->current()->whereNotIn('id', $inRoster)->orderBy('last_name')->orderBy('first_name')->get();

        return view('club.attendance._spontaneous_dialog', ['event' => $event, 'sheet' => $sheet, 'candidates' => $candidates]);
    }

    public function spontaneous(AddSpontaneousAttendeeRequest $request, Event $event): RedirectResponse {
        $sheet = $this->sheetOrFail($event);
        Gate::authorize('record', $sheet);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        /** @var ClubMember $member */
        $member = ClubMember::query()->findOrFail((int) $data['club_member_id']);
        $this->attendance->addSpontaneous($sheet, $member, $actor, (int) $data['version'], $data['reason'] ?? null);

        return redirect()
            ->route('club.events.attendance.show', $event)
            ->with('success', __('club.attendance.flash.spontaneous', ['name' => $member->fullName()]));
    }

    /** Nachweisliste im Header-Zeitraum: bestätigte Nachweise je Mitglied und Termin. */
    public function index(Request $request, DateRangeContext $range): View {
        Gate::authorize('viewAny', ClubAttendanceSheet::class);

        $filters = $this->filters($request);
        $query = $this->recordsQuery($filters, $range);

        return view('club.attendance.index', [
            'records' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
            'groups' => ClubGroup::query()->orderBy('name')->get(['id', 'name']),
            'range' => $range->current(),
        ]);
    }

    /** Derselbe Bestand als CSV (eine Zeile je Nachweis). */
    public function export(Request $request, DateRangeContext $range): StreamedResponse {
        Gate::authorize('export', ClubAttendanceSheet::class);

        $filters = $this->filters($request);
        $rows = [];
        $this->recordsQuery($filters, $range)->chunk(500, function ($chunk) use (&$rows): void {
            foreach ($chunk as $record) {
                $event = $record->event;
                $rows[] = [
                    $event !== null ? CarbonFmt::orgTz($event->started_at)->format('d.m.Y H:i') : '',
                    $event !== null ? $event->title : '',
                    $record->member?->displayNo() ?? '',
                    $record->member?->fullName() ?? '',
                    $record->status->label(),
                    $record->minutes ?? 0,
                    $record->sheet !== null ? $record->creditableMinutes($record->sheet) : 0,
                    $record->hasUnresolvedOverlap() ? (string) __('club.attendance.label.overlap') : '',
                ];
            }
        });
        $current = $range->current();
        $filename = 'vereinsnachweise-' . $current['from']->format('Y-m-d') . '-' . $current['to']->format('Y-m-d') . '.csv';

        return CsvExport::streamFromRows($filename, [
            (string) __('club.events.field.starts'),
            (string) __('club.events.field.title'),
            (string) __('club.field.member_no'),
            (string) __('club.field.member'),
            (string) __('club.field.status'),
            (string) __('club.attendance.field.minutes'),
            (string) __('club.attendance.field.credited'),
            (string) __('club.attendance.label.overlap'),
        ], $rows);
    }

    /** @return array{q: string, group: string, only_credited: bool} */
    private function filters(Request $request): array {
        return [
            'q' => trim((string) $request->query('q', '')),
            'group' => trim((string) $request->query('group', '')),
            'only_credited' => $request->boolean('only_credited'),
        ];
    }

    /**
     * @param  array{q: string, group: string, only_credited: bool}  $filters
     * @return Builder<ClubAttendanceRecord>
     */
    private function recordsQuery(array $filters, DateRangeContext $range): Builder {
        /** @var User $user */
        $user = Auth::user();
        $current = $range->current();

        $query = ClubAttendanceRecord::query()
            ->with(['member', 'event:id,title,started_at,ended_at', 'sheet:id,status'])
            ->whereHas('sheet', fn(Builder $sheet) => $sheet->where('status', ClubAttendanceSheetStatus::Confirmed->value))
            ->whereHas('event', fn(Builder $event) => $event
                ->where('started_at', '>=', $current['from']->startOfDay()->utc())
                ->where('started_at', '<', $current['to']->addDay()->startOfDay()->utc()))
            ->orderByDesc('event_id');

        if ($filters['q'] !== '') {
            $query->whereHas('member', fn(Builder $member) => $member->search($filters['q']));
        }
        $groupId = $filters['group'] !== '' ? Sqid::decodeOrNumeric(ClubGroup::class, $filters['group']) : null;
        if ($groupId !== null) {
            $query->whereHas('event.clubGroups', fn(Builder $groups) => $groups->where('club_groups.id', $groupId));
        }
        if ($filters['only_credited']) {
            $query->whereIn('status', [ClubAttendanceStatus::Present->value, ClubAttendanceStatus::Partial->value])
                ->where(fn(Builder $q) => $q->whereNull('overlap_event_id')->orWhereNotNull('overlap_cleared_at'));
        }
        if (! Gate::allows('viewRegister', ClubMember::class)) {
            $query->whereHas('event.clubGroups', fn(Builder $groups) => $groups->where('leader_user_id', $user->id));
        }

        return $query;
    }

    private function sheetOrFail(Event $event): ClubAttendanceSheet {
        $sheet = ClubAttendanceSheet::query()->where('event_id', $event->id)->first();
        abort_if($sheet === null, 404);

        return $sheet;
    }
}
