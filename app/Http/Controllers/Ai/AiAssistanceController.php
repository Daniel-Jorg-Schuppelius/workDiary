<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiAssistanceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Ai\AiTextSuggestion;
use App\Models\{CommunicationNote, CustomerQuery, DiaryEntry, Document, ImportRun, Organization, Project, Quote, QuoteItem, User};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\{CaseNarrativeSuggestionService, CommunicationNoteSuggestionService, DocumentMetadataSuggestionService, DocumentTranslationSuggestionService, ImportMappingSuggestionService, PlanActualExplainService, PortalQuerySuggestionService, SupportDiagnosisSuggestionService};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * KI-Assistenz der Wellen 2 und 3 (Feature 148, MVP-732): Vorschläge
 * anfordern, Chips einzeln übernehmen, Vorschläge verwerfen.
 *
 * Getrennt vom {@see AiSuggestionController} (Feature 084/143, Belege und
 * Protokollpunkte), weil hier andere Domänen, andere Rechte und andere
 * Übernahme-Pfade gelten. Gemeinsam bleibt die Linie: Rechte = Fach-Policy
 * der Domäne PLUS `ai.use`, Fehler enden IMMER als Flash (der Fachablauf
 * hängt nie an der KI), und die KI schreibt nie still.
 *
 * Reine Einsichten (Portal-Rückfrage verstehen, Plan-Ist erklären,
 * Support-Diagnose erklären, Spaltenzuordnung) haben bewusst KEIN
 * „übernehmen": es gibt kein Fachfeld, in das sie gehören — sie werden
 * gelesen und verworfen, die Entscheidung wird auditiert.
 */
class AiAssistanceController extends Controller {
    public function __construct(
        private readonly DocumentTranslationSuggestionService $documentTexts,
        private readonly PortalQuerySuggestionService $portalQueries,
        private readonly CommunicationNoteSuggestionService $notes,
        private readonly CaseNarrativeSuggestionService $narratives,
        private readonly PlanActualExplainService $planActual,
        private readonly SupportDiagnosisSuggestionService $support,
        private readonly DocumentMetadataSuggestionService $documents,
        private readonly ImportMappingSuggestionService $imports,
    ) {}

    /** Angebotsposition in die Belegsprache des Kunden übersetzen. */
    public function quoteItemTranslate(Quote $quote, QuoteItem $item): RedirectResponse {
        $this->authorizeQuote($quote, $item);

        return $this->guarded(function () use ($quote, $item): string {
            $this->documentTexts->translateQuoteItem($quote, $item, Auth::user());

            return __('ai.flash.suggestion_created');
        });
    }

    /** Begleittext des Angebots in die Belegsprache des Kunden übersetzen. */
    public function quoteTermsTranslate(Quote $quote): RedirectResponse {
        $this->authorizeQuote($quote);

        return $this->guarded(function () use ($quote): string {
            $this->documentTexts->translateQuoteTerms($quote, Auth::user());

            return __('ai.flash.suggestion_created');
        });
    }

    /** Fremdsprachige Portal-Rückfrage übersetzen und zusammenfassen. */
    public function portalQuery(CustomerQuery $customerQuery): RedirectResponse {
        $this->authorizePortalQueries();

        return $this->guarded(function () use ($customerQuery): string {
            $this->portalQueries->understand($customerQuery, Auth::user());

            return __('ai.flash.insight_created');
        });
    }

    /** Gesprächsverlauf einer Kommunikationsnotiz strukturieren. */
    public function communicationNote(CommunicationNote $note): RedirectResponse {
        Gate::authorize('update', $note);
        $this->authorizeAiUse();

        return $this->guarded(function () use ($note): string {
            $suggestion = $this->notes->structure($note, Auth::user());

            return $suggestion === null
                ? __('ai.flash.structure_none')
                : __('ai.flash.structure_created');
        });
    }

    /** Auftragsverlauf zu einem Kurznarrativ verdichten. */
    public function caseNarrative(DiaryEntry $diary): RedirectResponse {
        Gate::authorize('view', $diary);
        $this->authorizeAiUse();

        return $this->guarded(function () use ($diary): string {
            $this->narratives->narrate($diary, $this->actor());

            return __('ai.flash.narrative_created');
        });
    }

    /** Plan-Ist-Abweichung eines Projekts erklären. */
    public function planActual(Request $request, Project $project): RedirectResponse {
        $this->authorizeReports();

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return $this->guarded(function () use ($project, $data): string {
            $this->planActual->explainProject(
                $project,
                CarbonImmutable::parse($data['from'])->startOfDay(),
                CarbonImmutable::parse($data['to'])->endOfDay(),
                Auth::user(),
            );

            return __('ai.flash.insight_created');
        });
    }

    /** Health-Block des Supportberichts erklären (PII-frei per Whitelist). */
    public function supportDiagnose(): RedirectResponse {
        Gate::authorize(Permission::PlatformSupportExport->value);
        $this->authorizeAiUse();

        return $this->guarded(function (): string {
            $this->support->explain($this->organization(), Auth::user());

            return __('ai.flash.insight_created');
        });
    }

    /** Dokumenttyp und Fristen aus dem Dokumenttext vorschlagen. */
    public function document(Document $document): RedirectResponse {
        Gate::authorize('update', $document);
        $this->authorizeAiUse();

        return $this->guarded(function () use ($document): string {
            $suggestion = $this->documents->analyze($document, Auth::user());

            return $suggestion === null
                ? __('ai.flash.structure_none')
                : __('ai.flash.structure_created');
        });
    }

    /** Spaltenzuordnung eines Import-Laufs vorschlagen. */
    public function importMapping(ImportRun $import): RedirectResponse {
        $this->authorizeImport($import);

        return $this->guarded(function () use ($import): string {
            $suggestion = $this->imports->suggestMapping($import, Auth::user());

            return $suggestion === null
                ? __('ai.flash.mapping_none')
                : __('ai.flash.mapping_created');
        });
    }

    /** Textvorschlag übernehmen (nur Capabilities mit Schreibziel). */
    public function accept(Request $request, AiTextSuggestion $suggestion): RedirectResponse {
        $this->authorizeSuggestion($suggestion);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:8000'],
        ]);

        return $this->guarded(function () use ($suggestion, $data): string {
            $subject = $suggestion->subject;
            if ($subject instanceof DiaryEntry) {
                $this->narratives->accept($suggestion, $this->actor(), $data['text']);
            } elseif ($subject instanceof Quote || $subject instanceof QuoteItem) {
                $this->documentTexts->accept($suggestion, Auth::user(), $data['text']);
            } else {
                throw new AiException((string) __('ai.error.suggestion_not_acceptable'));
            }

            return __('ai.flash.suggestion_accepted');
        });
    }

    /** Einen Chip übernehmen — nie Auto-Apply. */
    public function apply(Request $request, AiTextSuggestion $suggestion): RedirectResponse {
        $this->authorizeSuggestion($suggestion);

        $data = $request->validate([
            'field' => ['required', 'string', 'max:60'],
        ]);

        return $this->guarded(function () use ($suggestion, $data): string {
            $subject = $suggestion->subject;
            if ($subject instanceof CommunicationNote) {
                $this->notes->applyValue($suggestion, $this->actor(), $data['field']);
            } elseif ($subject instanceof Document) {
                $this->documents->applyValue($suggestion, $this->actor(), $data['field']);
            } else {
                throw new AiException((string) __('ai.error.suggestion_not_acceptable'));
            }

            return __('ai.flash.suggestion_accepted');
        });
    }

    public function reject(AiTextSuggestion $suggestion): RedirectResponse {
        $this->authorizeSuggestion($suggestion);

        // Alle Wellen-2/3-Services teilen sich das DecidesSuggestions-Concern;
        // das Verwerfen ist identisch (idempotent + Audit ohne Klartext).
        $this->portalQueries->reject($suggestion, Auth::user());

        return back()->with('success', __('ai.flash.suggestion_rejected'));
    }

    private function guarded(callable $action): RedirectResponse {
        try {
            $message = $action();
        } catch (AiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    private function authorizeAiUse(): void {
        abort_unless(Gate::allows(Permission::AiUse->value), 403);
    }

    private function authorizeQuote(Quote $quote, ?QuoteItem $item = null): void {
        Gate::authorize('update', $quote);
        $this->authorizeAiUse();
        if ($item !== null) {
            abort_unless((int) $item->quote_id === (int) $quote->id, 404);
        }
    }

    /** Gleiche Schranke wie {@see \App\Http\Controllers\CustomerQueryController}. */
    private function authorizePortalQueries(): void {
        $user = Auth::user();
        abort_unless(
            $user instanceof User && ($user->isAdmin() || $user->can(Permission::ProtocolCustomerQueryManage->value)),
            403
        );
        $this->authorizeAiUse();
    }

    private function authorizeReports(): void {
        $user = Auth::user();
        abort_unless(
            $user instanceof User && ($user->isAdmin() || $user->can(Permission::ReportView->value)),
            403
        );
        $this->authorizeAiUse();
    }

    private function authorizeImport(ImportRun $run): void {
        $user = Auth::user();
        abort_unless($run->organization_id === (int) $this->organization()->id, 403);
        abort_unless(
            $user instanceof User && ($user->isAdmin() || $user->hasEffectivePermission($run->entity->permission())),
            403
        );
        $this->authorizeAiUse();
    }

    /** Mandantengrenze + Fachrecht des Vorschlags-Subjekts. */
    private function authorizeSuggestion(AiTextSuggestion $suggestion): void {
        $subject = $suggestion->subject;

        if ($subject instanceof QuoteItem) {
            $quote = $subject->quote;
            abort_if($quote === null, 404);
            $this->authorizeQuote($quote);
        } elseif ($subject instanceof Quote) {
            $this->authorizeQuote($subject);
        } elseif ($subject instanceof CustomerQuery) {
            $this->authorizePortalQueries();
        } elseif ($subject instanceof CommunicationNote) {
            $this->authorizeNote($subject);
        } elseif ($subject instanceof DiaryEntry) {
            $this->authorizeDiary($subject);
        } elseif ($subject instanceof Project) {
            $this->authorizeReports();
        } elseif ($subject instanceof Document) {
            $this->authorizeDocument($subject);
        } elseif ($subject instanceof ImportRun) {
            $this->authorizeImport($subject);
        } elseif ($subject instanceof Organization) {
            $this->authorizeSupport($subject);
        } else {
            abort(404);
        }
    }

    private function authorizeNote(CommunicationNote $note): void {
        Gate::authorize('update', $note);
        $this->authorizeAiUse();
    }

    private function authorizeDiary(DiaryEntry $diary): void {
        Gate::authorize('view', $diary);
        $this->authorizeAiUse();
    }

    private function authorizeDocument(Document $document): void {
        Gate::authorize('update', $document);
        $this->authorizeAiUse();
    }

    private function authorizeSupport(Organization $organization): void {
        abort_unless((int) $organization->id === (int) $this->organization()->id, 404);
        Gate::authorize(Permission::PlatformSupportExport->value);
        $this->authorizeAiUse();
    }

    private function organization(): Organization {
        /** @var Organization $organization */
        $organization = app('currentOrganization');

        return $organization;
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
