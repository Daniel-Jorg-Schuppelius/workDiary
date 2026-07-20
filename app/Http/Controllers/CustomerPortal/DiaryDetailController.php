<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryDetailController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\{Attachment, DiaryEntry, MaterialUsage, TimeEntry, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, URL};
use Illuminate\View\View;

/**
 * Portal-Auftragsdetail (Feature 012, Rang 54): read-only Sicht auf den
 * eigenen Auftrag — kundensichtbare Fotos (attachments.customer_visible),
 * Materialliste (nur Menge/Einheit/Bezeichnung, keine Preise) und
 * kundensichtbare Protokolle. Das Fallakte-PDF läuft über einen signierten,
 * kurzlebigen Link (24 h, teilbar ohne Portal-Session).
 */
class DiaryDetailController extends Controller {
    public function show(DiaryEntry $diary): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_unless((int) $diary->customer_id === (int) $user->customer_id, 403);

        $data = $this->customerViewData($diary);

        return view('customer.diary.show', $data + [
            'diary' => $diary,
            'pdfUrl' => URL::temporarySignedRoute('customer.diary.pdf', now()->addHours(24), ['diary' => $diary->getRouteKey()]),
            'confirmedByMe' => $data['photos']
                ->flatMap(fn (Attachment $a) => $a->confirmations)
                ->where('user_id', $user->id)
                ->keyBy('attachment_id'),
        ]);
    }

    /**
     * Fallakte-PDF über signierten Link (ohne Portal-Session teilbar; Schutz
     * ausschließlich über die 24-h-Signatur — Inhalt ist strikt auf
     * kundensichtbare Daten beschränkt).
     */
    public function pdf(Request $request, DiaryEntry $diary): \Symfony\Component\HttpFoundation\Response {
        abort_unless($request->hasValidSignature(), 403);

        // View→PDF über den zentralen Renderer (C15; Vollaudit 2026-07, N27) — design-frei.
        $bytes = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->renderPdf(
            \App\Enums\DocumentDesign\RenderDocumentKind::Report,
            'customer.diary.pdf',
            $this->customerViewData($diary) + ['diary' => $diary],
            null,
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="auftrag-' . $diary->getRouteKey() . '.pdf"',
        ]);
    }

    /**
     * Kundensichtbare Inhalte des Auftrags — einzige Datenquelle für
     * Portal-Detail UND PDF (identischer Sichtbarkeitsschnitt).
     *
     * @return array{photos: \Illuminate\Support\Collection<int, Attachment>, materials: \Illuminate\Support\Collection<int, MaterialUsage>, protocols: \Illuminate\Support\Collection<int, \App\Models\Protocol>}
     */
    private function customerViewData(DiaryEntry $diary): array {
        $photos = $diary->attachments()
            ->where('customer_visible', true)
            ->with('confirmations.user:id,name')
            ->get();

        $materials = MaterialUsage::query()
            ->whereIn('timesheet_id', TimeEntry::query()
                ->where('diary_entry_id', $diary->id)
                ->whereNotNull('timesheet_id')
                ->select('timesheet_id'))
            ->orderBy('created_at')
            ->get(['id', 'timesheet_id', 'description', 'quantity', 'unit', 'created_at']);

        $protocols = $diary->protocols()
            ->where('visibility', \App\Enums\Protocol\ProtocolVisibility::Customer->value)
            ->orderByDesc('occurred_at')
            ->get();

        // Vollaudit 2026-07 (H9): freigegebene Kommunikationsnotizen
        // (visibility=customer) erreichen jetzt das Portal — der Freigabe-
        // Workflow (communication.published) lief zuvor ins Leere.
        $notes = $diary->communicationNotes()
            ->where('visibility', \App\Enums\Communication\CommunicationVisibility::Customer->value)
            ->orderByDesc('occurred_at')
            ->get(['id', 'type', 'direction', 'subject', 'body', 'occurred_at']);

        return [
            'photos' => $photos,
            'materials' => $materials,
            'protocols' => $protocols,
            'notes' => $notes,
        ];
    }
}
