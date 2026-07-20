<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryCaseFileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{CommunicationNote, DiaryEntry, Document, MaterialUsage, Protocol, TimeEntry, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Timeline\DiaryEntryTimelineService;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Fallakte (MVP-013, ../WorkDiary-Architecture/fallakte.md): zusammenhängende Read-Only-Gesamtsicht
 * eines Auftrags inkl. vollständiger Timeline — druckbar über Print-CSS
 * (Muster: diary/export-pdf) und als serverseitiges PDF (MVP-349, analog
 * Kundenportal-Fallakte, jedoch mit vollem INTERNEN Datenumfang). Zugriff wie
 * die Auftragsdetailseite (auth + Organisations-Scope, Cross-Org → 404 via
 * HasSqid/Tenant-Scope) — HTML und PDF teilen dieselbe Datenquelle und
 * dasselbe Recht (kein neues Recht).
 */
class DiaryCaseFileController extends Controller {
    public function __invoke(DiaryEntry $diary, DiaryEntryTimelineService $timeline, FeatureFlagResolver $featureFlags): View {
        /** @var User $viewer */
        $viewer = Auth::user();

        return view('diary.case-file', $this->caseFileData($diary, $viewer, $timeline, $featureFlags));
    }

    /**
     * Interne Fallakte als serverseitiges PDF (fallakte.md §11 Folge-MVP):
     * identischer Datenschnitt wie die HTML-Fallakte — inkl. interner Einträge
     * (Kommentare, interne Anhänge, Kommunikation) im Unterschied zum strikt
     * kundensichtbaren Portal-PDF.
     */
    public function pdf(DiaryEntry $diary, DiaryEntryTimelineService $timeline, FeatureFlagResolver $featureFlags): \Symfony\Component\HttpFoundation\Response {
        /** @var User $viewer */
        $viewer = Auth::user();

        // View→PDF über den zentralen Renderer (C15; Vollaudit 2026-07, N27);
        // organization: null hält die Ausgabe bewusst design-frei (unverändert).
        $bytes = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->renderPdf(
            \App\Enums\DocumentDesign\RenderDocumentKind::Report,
            'diary.case-file-pdf',
            $this->caseFileData($diary, $viewer, $timeline, $featureFlags),
            null,
        );

        $filename = sprintf('fallakte-%s-%s.pdf', $diary->getRouteKey(), now()->format('Y-m-d'));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Gemeinsame Datenquelle für HTML- UND PDF-Fallakte (identischer
     * Sichtbarkeitsschnitt — Muster: CustomerPortal\DiaryDetailController).
     *
     * @return array<string, mixed>
     */
    private function caseFileData(DiaryEntry $diary, User $viewer, DiaryEntryTimelineService $timeline, FeatureFlagResolver $featureFlags): array {
        $diary->load([
            'user:id,name',
            'assignedUser:id,name',
            'customer:id,name,company',
            'project:id,name',
            'entryType:id,label',
            'tags:id,name,color,slug',
            'comments.user:id,name',
            'attachments.uploader:id,name',
        ]);

        $timeEntries = TimeEntry::query()
            ->where('diary_entry_id', $diary->id)
            ->with('user:id,name')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $materials = MaterialUsage::query()
            ->whereIn('timesheet_id', TimeEntry::query()
                ->where('diary_entry_id', $diary->id)
                ->whereNotNull('timesheet_id')
                ->select('timesheet_id'))
            ->orderBy('id')
            ->get();

        $protocols = Protocol::query()
            ->where('subject_type', $diary->getMorphClass())
            ->where('subject_id', $diary->getKey())
            ->withCount('signatures')
            ->with('tags:id,name,color,slug')
            ->orderByDesc('occurred_at')
            ->get();

        $openIssues = $diary->openIssues()->with(['assignee:id,name', 'creator:id,name'])->get();

        // Dienstmittel/Assets (Feature 009 Akzeptanz 1; Vollaudit 2026-07, M5):
        // Gegenstand des Auftrags (diary.asset_id) + beim Checkout auf den
        // Auftrag gebuchte Ausgaben (asset_assignments.diary_entry_id).
        $assetAssignments = \App\Models\AssetAssignment::query()
            ->where('diary_entry_id', $diary->id)
            ->with(['asset:id,name', 'assignedToUser:id,name'])
            ->orderByDesc('checked_out_at')
            ->get();
        $diary->loadMissing('asset:id,name');

        // Kommunikationsnotizen ohne confidential (außer berechtigt) —
        // gleiche Logik wie die Auftrags-Timeline (CommunicationNotePolicy/Scope).
        $communicationNotes = Gate::allows('viewAny', CommunicationNote::class)
            ? $diary->communicationNotes()->visibleTo($viewer)->with('creator:id,name')->get()
            : collect();

        $documents = Gate::allows('viewAny', Document::class) && $featureFlags->isEnabled('module.documents')
            ? Document::query()
            ->where('documentable_type', $diary->getMorphClass())
            ->where('documentable_id', $diary->getKey())
            ->with(['creator:id,name', 'currentVersion'])
            ->latest('created_at')
            ->get()
            : collect();

        $fullTimeline = $timeline->forDiaryEntry($diary, $viewer, null, 500);

        return [
            'diary' => $diary,
            'timeEntries' => $timeEntries,
            'totalMinutes' => (int) $timeEntries->sum('minutes'),
            'billableMinutes' => (int) $timeEntries->where('billable', true)->sum('minutes'),
            'materials' => $materials,
            'protocols' => $protocols,
            'openIssues' => $openIssues,
            'assetAssignments' => $assetAssignments,
            'communicationNotes' => $communicationNotes,
            'documents' => $documents,
            'timelineItems' => $fullTimeline['items'],
            'generatedAt' => now(),
        ];
    }
}
