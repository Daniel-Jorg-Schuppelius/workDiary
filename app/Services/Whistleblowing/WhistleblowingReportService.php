<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\{CaseCategory, CasePriority, CaseStatus, ReporterMode};
use App\Models\Whistleblowing\{Portal, WhistleblowingCase};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legt eine Meldung transaktional an (Abschnitt 7.1 / 14). Erzeugt den Fall mit
 * per-Fall-DEK, verschluesselt die Inhalte, erzeugt die Zugangsdaten (nur einmal
 * im Klartext zurueckgegeben), persistiert die gesetzlichen Fristen und schreibt
 * ein minimiertes case.submitted-Event. Speichert BEWUSST keine Reporter-Identitaet
 * (keine user_id/IP/User-Agent).
 */
class WhistleblowingReportService {
    public function __construct(
        private readonly ReporterCredentialService $credentials,
        private readonly WhistleblowingAttachmentService $attachments,
        private readonly WhistleblowingEventService $events,
    ) {}

    /**
     * @param array<string, mixed> $input validierte Meldedaten (reporter_mode,
     *   category, subject, description, optional occurred_from/occurred_to/contact)
     * @param array<int, UploadedFile> $files
     *
     * @return array{case: WhistleblowingCase, case_number: string, secret: string}
     */
    public function submit(Portal $portal, array $input, array $files = []): array {
        $secret = $this->credentials->generateSecret();
        $deadlines = (array) config('whistleblowing.deadlines');

        return DB::transaction(function () use ($portal, $input, $files, $secret, $deadlines): array {
            $case = new WhistleblowingCase;
            $case->organization_id = $portal->organization_id;
            $case->initializeDek();

            $mode = $input['reporter_mode'] === ReporterMode::Confidential->value
                ? ReporterMode::Confidential
                : ReporterMode::Anonymous;

            $case->reporter_mode = $mode;
            $case->category = CaseCategory::from((string) $input['category']);
            $case->priority = CasePriority::Normal;
            $case->status = CaseStatus::Submitted;
            $case->occurred_from = empty($input['occurred_from']) ? null : Carbon::parse((string) $input['occurred_from']);
            $case->occurred_to = empty($input['occurred_to']) ? null : Carbon::parse((string) $input['occurred_to']);
            $case->subject_ciphertext = (string) $input['subject'];
            $case->description_ciphertext = (string) $input['description'];
            if ($mode === ReporterMode::Confidential && ! empty($input['contact'])) {
                $case->contact_ciphertext = $input['contact'];
            }

            $now = Carbon::now();
            $case->acknowledgement_due_at = $now->copy()->addDays((int) ($deadlines['acknowledge_days'] ?? 7));
            $case->feedback_due_at = $now->copy()->addDays((int) ($deadlines['feedback_days'] ?? 90));

            $case->forceFill([
                'case_number' => $this->uniqueCaseNumber($portal->organization_id),
                'access_code_hash' => $this->credentials->hashSecret($secret),
                'access_code_lookup' => $this->credentials->lookupHmac($secret),
            ]);
            $case->save();

            foreach ($files as $file) {
                $this->attachments->storeReporterUpload($case, $file);
            }

            $this->events->record($case, WhistleblowingEventService::CASE_SUBMITTED, null, [
                'category' => $case->category->value,
                'mode' => $mode->value,
                'attachments' => count($files),
            ]);

            return [
                'case' => $case,
                'case_number' => (string) $case->getAttribute('case_number'),
                'secret' => $secret,
            ];
        });
    }

    private function uniqueCaseNumber(int $organizationId): string {
        for ($i = 0; $i < 5; $i++) {
            $candidate = $this->credentials->generateCaseNumber();
            $exists = WhistleblowingCase::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('case_number', $candidate)
                ->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        // Praktisch unerreichbar (32^8 pro Gruppe); defensiv mit Zeitstempel.
        return $this->credentials->generateCaseNumber();
    }
}
