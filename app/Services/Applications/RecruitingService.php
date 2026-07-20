<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecruitingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\Notification\NotificationEvent;
use App\Models\Applications\{EmployeeDraft, JobApplication, JobPosting};
use App\Models\{Organization, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

/**
 * Bewerbungs-Lifecycle (Feature 068, MVP-190–193): Eingang mit
 * Dublettenprüfung (email_hash), Pipeline-Entscheidungen mit
 * Datenschutzfolgen (Aufbewahrung nach AGG-/Klagefrist, Talentpool nur
 * mit befristeter Einwilligung), Auskunfts-Export, Anonymisierung und
 * kontrollierte Onboarding-Übergabe über den Mitarbeiter-Entwurf (D4).
 */
class RecruitingService {
    /**
     * @param array<string, mixed> $attributes
     * @return array{application: JobApplication, duplicates: int}
     */
    public function intake(array $attributes, User $actor): array {
        $email = trim((string) ($attributes['email'] ?? ''));
        $emailHash = $email !== '' ? JobApplication::hashEmail($email) : null;

        // Dublettenprüfung (MVP-190): Hinweis, NIE Auto-Zusammenführung.
        $duplicates = 0;
        if ($emailHash !== null) {
            $duplicates = JobApplication::query()
                ->where('organization_id', $actor->organization_id)
                ->where('email_hash', $emailHash)
                ->whereNull('anonymized_at')
                ->count();
        }

        $application = JobApplication::query()->create([
            'organization_id' => (int) $actor->organization_id,
            'job_requisition_id' => $attributes['job_requisition_id'] ?? null,
            'job_posting_id' => $attributes['job_posting_id'] ?? null,
            'candidate_name' => trim((string) ($attributes['candidate_name'] ?? '')) ?: null,
            'email' => $email !== '' ? $email : null,
            'phone' => trim((string) ($attributes['phone'] ?? '')) ?: null,
            'email_hash' => $emailHash,
            'source' => (string) ($attributes['source'] ?? 'other'),
            'status' => 'received',
            'received_at' => now(),
            'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
            'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
            'created_by' => $actor->id,
        ]);
        $application->audit('recruiting.application_received', ['duplicates' => $duplicates]);

        return ['application' => $application, 'duplicates' => $duplicates];
    }

    /**
     * Öffentlicher Selbst-Service-Eingang (MVP-437). Anders als {@see intake()}
     * gibt es **keinen fingierten Nutzer**: Organisation, Posting, Quelle und der
     * Datenschutz-Nachweis werden explizit übergeben, `created_by` bleibt null.
     * Die Dubletten-/Hash-/Empty-null-Logik wird bewusst wiederverwendet; das
     * Audit-Ereignis und die interne Benachrichtigung (ohne Bewerber-PII) sind
     * eigenständig.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{application: JobApplication, duplicates: int}
     */
    public function publicIntake(Organization $organization, JobPosting $posting, array $attributes, string $privacyVersion, string $intakeRef): array {
        $email = trim((string) ($attributes['email'] ?? ''));
        $emailHash = $email !== '' ? JobApplication::hashEmail($email) : null;

        $duplicates = 0;
        if ($emailHash !== null) {
            $duplicates = JobApplication::query()
                ->where('organization_id', $organization->id)
                ->where('email_hash', $emailHash)
                ->whereNull('anonymized_at')
                ->count();
        }

        $responsibleId = $posting->requisition?->responsible_user_id;

        $application = JobApplication::query()->create([
            'organization_id' => $organization->id,
            'job_requisition_id' => $posting->job_requisition_id,
            'job_posting_id' => $posting->id,
            'candidate_name' => trim((string) ($attributes['candidate_name'] ?? '')) ?: null,
            'email' => $email !== '' ? $email : null,
            'phone' => trim((string) ($attributes['phone'] ?? '')) ?: null,
            'email_hash' => $emailHash,
            'source' => 'website',
            'status' => 'received',
            'received_at' => now(),
            'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
            'responsible_user_id' => $responsibleId,
            'created_by' => null,
            'privacy_ack_at' => now(),
            'privacy_ack_version' => $privacyVersion,
            'public_intake_ref' => $intakeRef,
        ]);
        $application->audit('recruiting.public_application_received', [
            'duplicates' => $duplicates,
            'posting_id' => $posting->id,
            'source' => 'website',
        ]);

        $this->notifyResponsible($application, $responsibleId);

        return ['application' => $application, 'duplicates' => $duplicates];
    }

    /**
     * Interne Benachrichtigung der verantwortlichen Person — bewusst **ohne**
     * Bewerber-PII/-Unterlagen im Text (nur Verweis auf die Bewerbungsakte).
     */
    private function notifyResponsible(JobApplication $application, ?int $responsibleId): void {
        if ($responsibleId === null) {
            return;
        }
        $responsible = User::query()->find($responsibleId);
        if (! $responsible instanceof User) {
            return;
        }

        app(NotificationDispatcher::class)->notify(
            NotificationEvent::RecruitingApplicationReceived,
            $application,
            $responsible,
            [
                'title' => (string) __('Neue Bewerbung über den Karrierebereich'),
                'message' => (string) __('Es ist eine neue Bewerbung eingegangen. Details in der Bewerbungsakte.'),
                'url' => route('recruiting.applications.show', $application),
            ],
        );
    }

    /**
     * Entscheidung mit Datenschutzfolgen (MVP-191/192): Absage/Rückzug
     * startet die Löschvormerkung; Talentpool verlangt eine ausdrückliche,
     * befristete Einwilligung.
     */
    public function decide(JobApplication $application, string $decision, ?string $note, User $actor, bool $talentPoolConsent = false): JobApplication {
        if (! in_array($decision, ['offer', 'accepted', 'rejected', 'withdrawn', 'talent_pool'], true)) {
            throw new \RuntimeException((string) __('Ungültige Entscheidung.'));
        }
        if ($application->isAnonymized()) {
            throw new \RuntimeException((string) __('Die Akte ist bereits anonymisiert.'));
        }

        $changes = ['status' => $decision];

        if (in_array($decision, ['rejected', 'withdrawn'], true)) {
            // AGG §15 Abs. 4 + ArbGG §61b → Praxis-Default 6 Monate,
            // org-konfigurierbar (P1: Frist ist Konfiguration, kein Code).
            $months = (int) config('applications.rejected_retention_months', 6);
            $changes['retention_until'] = now()->addMonths($months)->toDateString();
        }

        if ($decision === 'talent_pool') {
            if (! $talentPoolConsent) {
                throw new \RuntimeException((string) __('Talentpool nur mit ausdrücklicher, widerruflicher Einwilligung (Art. 6 Abs. 1 lit. a DSGVO).'));
            }
            $months = (int) config('applications.talent_pool_months', 18);
            $changes['consent_talent_pool_at'] = now();
            $changes['consent_expires_on'] = now()->addMonths($months)->toDateString();
            $changes['retention_until'] = now()->addMonths($months)->toDateString();
        }

        $application->update($changes);
        $application->audit('recruiting.application_decided', ['decision' => $decision, 'note' => $note, 'by' => $actor->id]);

        return $application->refresh();
    }

    /**
     * Anonymisierung/Löschung (MVP-192): PII wird entfernt (Crypto-Delete),
     * die Akte bleibt als anonymer Zähler für Auswertungen erhalten.
     */
    public function anonymize(JobApplication $application, User $actor): JobApplication {
        DB::transaction(function () use ($application, $actor): void {
            $application->interviews()->update(['notes' => null]);
            $application->reviews()->update(['comment' => null]);
            $application->update([
                'candidate_name' => null,
                'email' => null,
                'phone' => null,
                'email_hash' => null,
                'notes' => null,
                'status' => 'deleted',
                'anonymized_at' => now(),
            ]);
            $application->audit('recruiting.application_anonymized', ['by' => $actor->id]);
        });

        return $application->refresh();
    }

    /**
     * Auskunft/Export (MVP-192, Art. 15 DSGVO): strukturierte Kopie der
     * gespeicherten Bewerberdaten für den Betroffenen.
     *
     * @return array<string, mixed>
     */
    public function export(JobApplication $application): array {
        return [
            'candidate_name' => $application->candidate_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'source' => $application->source,
            'status' => $application->status,
            'received_at' => optional($application->received_at)->toIso8601String(),
            'requisition' => $application->requisition?->title,
            'consent_talent_pool_at' => optional($application->consent_talent_pool_at)->toIso8601String(),
            'consent_expires_on' => optional($application->consent_expires_on)->toDateString(),
            'retention_until' => optional($application->retention_until)->toDateString(),
            'interviews' => $application->interviews->map(fn($interview): array => [
                'scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'mode' => $interview->mode,
                'status' => $interview->status,
            ])->all(),
            'documents' => $application->documents->map(fn($doc): array => [
                'label' => $doc->label,
                'document_id' => $doc->document_id,
            ])->all(),
        ];
    }

    /**
     * Onboarding-Übergabe (MVP-193, D4): Zusage erzeugt einen
     * Mitarbeiter-ENTWURF — kein Live-User, keine Rollen, kein Login.
     *
     * @param array<int, string> $qualifications
     * @param array<int, string> $checklist
     */
    public function createEmployeeDraft(JobApplication $application, User $actor, array $qualifications = [], array $checklist = []): EmployeeDraft {
        if ($application->status !== 'accepted') {
            throw new \RuntimeException((string) __('Nur zugesagte Bewerbungen werden ins Onboarding übergeben.'));
        }
        if ($application->employeeDraft()->exists()) {
            throw new \RuntimeException((string) __('Für diese Bewerbung existiert bereits ein Mitarbeiter-Entwurf.'));
        }

        $defaultChecklist = $checklist !== [] ? $checklist : [
            (string) __('Arbeitsvertrag unterschrieben abgelegt'),
            (string) __('Arbeitsplatz/Ausstattung vorbereitet'),
            (string) __('Zugänge beantragt (erst am Starttag aktivieren)'),
            (string) __('Erstunterweisung geplant'),
        ];

        $draft = EmployeeDraft::query()->create([
            'organization_id' => $application->organization_id,
            'job_application_id' => $application->id,
            'name' => (string) ($application->candidate_name ?? __('Unbenannt')),
            'email' => $application->email,
            'qualifications' => $qualifications,
            'checklist' => array_map(fn(string $label): array => ['label' => $label, 'done' => false], $defaultChecklist),
            'status' => 'draft',
            'created_by' => $actor->id,
        ]);
        $application->audit('recruiting.onboarding_draft_created', ['draft_id' => $draft->id]);

        return $draft;
    }

    /**
     * Bewusste Übernahme (D4): löst den bestehenden Invite-Pfad aus —
     * neuer User mit must_change_password + is_new_system, Standardrolle
     * 'user'; SCIM-/Rollenvergabe bleibt Admin-Sache.
     */
    public function inviteFromDraft(EmployeeDraft $draft, User $actor): User {
        if ($draft->status !== 'draft') {
            throw new \RuntimeException((string) __('Der Entwurf wurde bereits übernommen oder verworfen.'));
        }
        $email = trim((string) $draft->email);
        if ($email === '') {
            throw new \RuntimeException((string) __('Für die Einladung braucht der Entwurf eine E-Mail-Adresse.'));
        }
        if (User::query()->where('email', $email)->exists()) {
            throw new \RuntimeException((string) __('Ein Konto mit dieser E-Mail existiert bereits.'));
        }

        // Vollaudit 2026-07 (H8): Lizenz-Nutzerlimit auch bei Übernahme aus dem Recruiting.
        app(\App\Services\Licensing\LimitGuard::class)->ensureCanCreateUser(
            \App\Models\Organization::query()->withoutGlobalScopes()->findOrFail((int) $draft->organization_id),
            $actor,
        );

        return DB::transaction(function () use ($draft, $actor, $email): User {
            $user = User::query()->create([
                'organization_id' => $draft->organization_id,
                'name' => $draft->name,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'must_change_password' => true,
                'is_new_system' => true,
            ]);
            $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('user', 'web'));

            $draft->update(['status' => 'invited', 'invited_user_id' => $user->id]);
            $draft->audit('recruiting.draft_invited', ['user_id' => $user->id, 'by' => $actor->id]);

            return $user;
        });
    }
}
