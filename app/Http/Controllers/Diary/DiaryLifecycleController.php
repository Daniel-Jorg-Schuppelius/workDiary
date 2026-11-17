<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryLifecycleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Diary;

use App\Exceptions\{ClassificationRequirementException, InvalidOrderTransitionException};
use App\Http\Controllers\Controller;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Services\Diary\OrderService;
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use InvalidArgumentException;

class DiaryLifecycleController extends Controller {
    public function __invoke(Request $request, DiaryEntry $diary, string $action, OrderService $orders): RedirectResponse {
        Gate::authorize($this->ability($action), $diary);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            // Pflichtklassifikationen vor dem Abschluss (etwa „Ergebnis“) kommen aus dem Abschlussdialog.
            if (in_array($action, ['complete', 'approveAndComplete'], true)) {
                $this->applyClassifications($request, $diary);
            }
            match ($action) {
                'accept' => $orders->accept($diary, $actor),
                'reject' => $orders->reject($diary, $actor, $this->requiredText($request, 'reason', 2000)),
                'start' => $orders->start($diary, $actor),
                'pause' => $orders->pause($diary, $actor, ...$this->pauseData($request)),
                'resume' => $orders->resume($diary, $actor),
                'complete' => $orders->complete($diary, $actor, $this->requiredText($request, 'summary', 5000)),
                'handover' => $orders->handover($diary, $actor, $this->protocol($request, $diary), $this->signerName($request)),
                'markInvoiced' => $orders->markInvoiced($diary, $actor, $this->requiredText($request, 'reference', 120)),
                'cancel' => $orders->cancel($diary, $actor, $this->requiredText($request, 'reason', 2000)),
                // Interne Prüfung (Feature 198, MVP-1635).
                'submitReview' => $orders->submitForReview($diary, $actor, $this->optionalText($request, 'note', 2000)),
                'withdrawReview' => $orders->withdrawReview($diary, $actor),
                'approveReview' => $orders->approveReview($diary, $actor, $this->optionalText($request, 'note', 2000)),
                'queryReview' => $orders->queryReview($diary, $actor, $this->requiredText($request, 'question', 2000)),
                'resubmitReview' => $orders->resubmitForReview($diary, $actor, $this->optionalText($request, 'note', 2000)),
                'approveAndComplete' => $orders->approveAndComplete($diary, $actor, $this->requiredText($request, 'summary', 5000)),
                default => abort(404),
            };
        } catch (ClassificationRequirementException|InvalidOrderTransitionException|InvalidArgumentException $e) {
            return back()->withErrors(['lifecycle' => ErrorText::for($e)]);
        }

        return back()->with('success', __('Auftragsstatus aktualisiert.'));
    }

    private function ability(string $action): string {
        return match ($action) {
            'accept' => 'accept',
            'reject' => 'reject',
            'start' => 'start',
            'pause' => 'pause',
            'resume' => 'resume',
            'complete' => 'complete',
            'handover' => 'handover',
            'markInvoiced' => 'markInvoiced',
            'cancel' => 'cancel',
            'submitReview' => 'submitReview',
            'withdrawReview' => 'withdrawReview',
            'approveReview' => 'approveReview',
            'queryReview' => 'queryReview',
            'resubmitReview' => 'resubmitReview',
            'approveAndComplete' => 'approveAndComplete',
            default => abort(404),
        };
    }

    /**
     * Im Abschlussdialog gewählte Klassifikationen (Domäne → Code) am Auftrag
     * setzen; nur bekannte Domänen außer Eintragsart und Priorität, nur Codes
     * der Organisation.
     */
    private function applyClassifications(Request $request, DiaryEntry $diary): void {
        $data = $request->validate([
            'classifications' => ['nullable', 'array', 'max:10'],
            'classifications.*' => ['nullable', 'string', 'max:64'],
        ]);
        $resolver = app(\App\Services\Classification\ClassificationResolver::class);
        foreach ((array) ($data['classifications'] ?? []) as $domainKey => $code) {
            $domain = \App\Enums\Classification\ClassificationDomain::tryFrom((string) $domainKey);
            if ($domain === null || in_array($domain, [\App\Enums\Classification\ClassificationDomain::EntryType, \App\Enums\Classification\ClassificationDomain::Priority], true) || ! is_string($code) || $code === '') {
                continue;
            }
            $classification = $resolver->resolveCode((int) $diary->organization_id, $domain, $code);
            if ($classification !== null) {
                $diary->syncClassificationDomain($domain, [(int) $classification->id]);
            }
        }
        $diary->unsetRelation('classifications');
    }

    private function optionalText(Request $request, string $field, int $max): ?string {
        $data = $request->validate([
            $field => ['nullable', 'string', 'max:' . $max],
        ]);

        return $data[$field] ?? null;
    }

    /** @return array{0: string, 1: string|null} */
    private function pauseData(Request $request): array {
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:customer,material'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return [$data['reason'], $data['note'] ?? null];
    }

    private function requiredText(Request $request, string $field, int $max): string {
        $data = $request->validate([
            $field => ['required', 'string', 'max:' . $max],
        ]);

        return $data[$field];
    }

    /** Nur gefragt, wenn das Protokoll keine Kundenunterschrift trägt. */
    private function signerName(Request $request): ?string {
        $data = $request->validate(['accepted_by' => ['nullable', 'string', 'max:120']]);

        return $data['accepted_by'] ?? null;
    }

    private function protocol(Request $request, DiaryEntry $diary): Protocol {
        $data = $request->validate(['protocol_id' => ['required', 'string']]);

        // Sqid aus dem Formular (W3.3); die org-gescopte Query bleibt die Schutzlinie.
        return Protocol::query()
            ->where('organization_id', $diary->organization_id)
            ->findOrFail(\App\Support\Sqid::decodeOrAbort(Protocol::class, (string) $data['protocol_id']));
    }
}
