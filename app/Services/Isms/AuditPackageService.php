<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditPackageService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{AssessmentStatus, AuditPackageStatus, ReviewStatus};
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsAudit, IsmsAuditFinding, IsmsAuditPackage, IsmsAuditPackageToken, IsmsCertificate, IsmsControl, IsmsCorrectiveAction, IsmsManagementReview, IsmsNormStatus, IsmsRequirement, IsmsRisk, IsmsRiskAssessment, IsmsScope, IsmsSoftwareProduct};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Auditpakete (Feature 046, Inkrement E / 044 „Auditbereitschaft"):
 * stichtagsbezogene, integritätsgeschützte JSON-Exportpakete mit zeitlich
 * begrenztem Prüfer-Download.
 *
 * - create(): Entwurf mit laufender Nummer je Organisation (package_no).
 * - finalize(): baut den JSON-Snapshot (SoA, Risikoregister inkl.
 *   freigegebener Bewertungen, Maßnahmen, Konformität + Zertifikate,
 *   Audits + Feststellungen + Korrekturmaßnahmen, freigegebene
 *   Managementbewertungen, Softwareinventar), legt die Datei auf der
 *   Export-Disk ab (Pfad isms/audit-packages/{org}/...), persistiert den
 *   SHA-256 (file_hash) und friert das Paket ein (unveränderlich).
 * - STICHTAGS-SEMANTIK (MVP, ehrlich): as_of_date ist der dokumentierte
 *   Berichtsstichtag; eingefroren wird der Datenstand zum Zeitpunkt der
 *   Finalisierung (meta.data_captured_at) — KEINE rückwirkende
 *   Zeitreise-Rekonstruktion (das wäre Event-Sourcing).
 * - verify(): vergleicht file_hash gegen die abgelegte Datei
 *   (Integritätsnachweis; Command `isms:verify-packages` + UI-Button).
 * - createToken()/revokeToken(): Prüfer-Links — random 64-hex-Token, nur
 *   SHA-256-Hash persistiert, Klartext EINMALIG zurückgegeben (Muster
 *   ProtocolSignatureTokenService).
 */
class AuditPackageService {
    public const DISK = \App\Services\Export\ExportRunner::DISK;

    public const BASE_PATH = 'isms/audit-packages';

    public const MIN_TOKEN_DAYS = 1;

    public const MAX_TOKEN_DAYS = 90;

    /**
     * Legt ein Auditpaket als Entwurf an (Vergabe der laufenden Nummer
     * innerhalb der Transaktion, Muster AuditService::nextNo()).
     *
     * @param  array<string, mixed>  $attributes  title, as_of_date, norm?, edition?
     */
    public function create(User $creator, IsmsScope $scope, array $attributes): IsmsAuditPackage {
        return DB::transaction(function () use ($creator, $scope, $attributes): IsmsAuditPackage {
            $package = IsmsAuditPackage::query()->create([
                'organization_id' => $creator->organization_id,
                'isms_scope_id' => $scope->id,
                'package_no' => $this->nextPackageNo((int) $creator->organization_id),
                'title' => trim((string) ($attributes['title'] ?? '')),
                'as_of_date' => $attributes['as_of_date'],
                'norm' => $this->trimmedOrNull($attributes['norm'] ?? null),
                'edition' => $this->trimmedOrNull($attributes['edition'] ?? null),
                'status' => AuditPackageStatus::Draft->value,
                'created_by_user_id' => $creator->id,
            ]);

            $package->audit('isms.audit_package.created', ['actor_user_id' => $creator->id]);

            return $package;
        });
    }

    /**
     * Finalisiert ein Entwurfs-Paket: baut den JSON-Snapshot, legt die
     * Datei ab, persistiert SHA-256 + finalized_by/at und friert das
     * Paket damit ein (Model-Guard: finalized = unveränderlich).
     *
     * @throws ValidationException wenn das Paket bereits finalisiert ist
     */
    public function finalize(IsmsAuditPackage $package, User $actor): IsmsAuditPackage {
        if ($package->isFinalized()) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.package_already_finalized'),
            ]);
        }

        $snapshot = $this->buildSnapshot($package, $actor);
        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Auditpaket-Snapshot konnte nicht serialisiert werden.');
        }

        $relativePath = sprintf(
            '%s/%d/auditpaket-%d-%s-%s.json',
            self::BASE_PATH,
            (int) $package->organization_id,
            (int) $package->package_no,
            $package->as_of_date->format('Y-m-d'),
            Carbon::now()->format('Ymd_His'),
        );

        if (! Storage::disk(self::DISK)->put($relativePath, $json)) {
            throw new RuntimeException('Auditpaket konnte nicht gespeichert werden: ' . $relativePath);
        }

        return DB::transaction(function () use ($package, $actor, $relativePath, $json): IsmsAuditPackage {
            $package->update([
                'status' => AuditPackageStatus::Finalized->value,
                'file_path' => $relativePath,
                'file_hash' => hash('sha256', $json),
                'finalized_by_user_id' => $actor->id,
                'finalized_at' => Carbon::now(),
            ]);

            $package->audit('isms.audit_package.finalized', [
                'actor_user_id' => $actor->id,
                'file_path' => $relativePath,
                'file_hash' => $package->file_hash,
            ]);

            return $package;
        });
    }

    /**
     * Integritätsprüfung: existiert die Datei und stimmt ihr SHA-256 mit
     * dem bei der Finalisierung persistierten file_hash überein?
     */
    public function verify(IsmsAuditPackage $package): bool {
        if (! $package->isFinalized() || $package->file_path === null || $package->file_hash === null) {
            return false;
        }

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($package->file_path)) {
            return false;
        }

        $content = $disk->get($package->file_path);

        return $content !== null && hash_equals($package->file_hash, hash('sha256', $content));
    }

    /**
     * Erstellt einen zeitlich begrenzten Prüfer-Token (nur für
     * finalisierte Pakete). Der Klartext-Token (64 hex) wird NUR hier
     * zurückgegeben und nirgends gespeichert — persistiert wird der
     * SHA-256-Hash.
     *
     * @return array{token: string, model: IsmsAuditPackageToken}
     *
     * @throws ValidationException wenn das Paket nicht finalisiert ist
     */
    public function createToken(IsmsAuditPackage $package, User $actor, string $label, int $days): array {
        if (! $package->isFinalized()) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.package_token_requires_finalized'),
            ]);
        }

        $days = max(self::MIN_TOKEN_DAYS, min(self::MAX_TOKEN_DAYS, $days));
        $token = bin2hex(random_bytes(32)); // 64 Hex-Zeichen

        $model = IsmsAuditPackageToken::query()->create([
            'isms_audit_package_id' => $package->id,
            'token_hash' => hash('sha256', $token),
            'label' => trim($label),
            'expires_at' => Carbon::now()->addDays($days),
            'created_by_user_id' => $actor->id,
            'created_at' => Carbon::now(),
        ]);

        $package->audit('isms.audit_package.token_created', [
            'actor_user_id' => $actor->id,
            'token_id' => $model->id,
            'label' => $model->label,
            'expires_at' => $model->expires_at->toIso8601String(),
        ]);

        return ['token' => $token, 'model' => $model];
    }

    /** Widerruft einen Prüfer-Token (idempotent). */
    public function revokeToken(IsmsAuditPackageToken $token, User $actor): IsmsAuditPackageToken {
        if ($token->revoked_at === null) {
            $token->forceFill(['revoked_at' => Carbon::now()])->save();

            $token->package()->withoutGlobalScopes()->first()?->audit('isms.audit_package.token_revoked', [
                'actor_user_id' => $actor->id,
                'token_id' => $token->id,
            ]);
        }

        return $token;
    }

    /**
     * Löst einen Klartext-Token für den öffentlichen Prüfer-Download auf:
     * Hash-Match + nicht widerrufen + nicht abgelaufen — sonst null
     * (Controller antwortet 404, keine Detail-Preisgabe).
     */
    public function resolveUsableToken(string $plainToken): ?IsmsAuditPackageToken {
        $token = IsmsAuditPackageToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        return $token !== null && $token->isUsable() ? $token : null;
    }

    // ── Snapshot-Aufbau ────────────────────────────────────────────────

    /**
     * Baut den vollständigen JSON-Snapshot (ein Dokument). Alle Queries
     * filtern explizit nach organization_id des Pakets — der Aufbau läuft
     * damit auch in Command-/Queue-Kontexten ohne Org-Bindung korrekt.
     *
     * @return array<string, mixed>
     */
    private function buildSnapshot(IsmsAuditPackage $package, User $actor): array {
        $package->loadMissing(['scope', 'organization']);
        $orgId = (int) $package->organization_id;

        return [
            'meta' => $this->metaSection($package, $actor),
            'soa' => $this->soaSection($package, $orgId),
            'risks' => $this->riskSection($orgId),
            'controls' => $this->controlSection($orgId),
            'conformity' => $this->conformitySection($package, $orgId),
            'audits' => $this->auditSection($package, $orgId),
            'reviews' => $this->reviewSection($package, $orgId),
            'software' => $this->softwareSection($orgId),
        ];
    }

    /**
     * meta: Organisation, Scope, Stichtag, Norm-Filter, erzeugt von/am,
     * App-Version und Datenstand. `as_of_date` ist der dokumentierte
     * Berichtsstichtag, `data_captured_at` der tatsächliche Datenstand
     * (= Zeitpunkt der Finalisierung) — bewusst getrennt ausgewiesen.
     *
     * @return array<string, mixed>
     */
    private function metaSection(IsmsAuditPackage $package, User $actor): array {
        return [
            'package_no' => $package->displayNo(),
            'title' => $package->title,
            'organization' => $package->organization?->name,
            'scope' => $package->scope?->name,
            'as_of_date' => $package->as_of_date->toDateString(),
            'data_captured_at' => Carbon::now()->toIso8601String(),
            'as_of_note' => 'as_of_date ist der dokumentierte Berichtsstichtag; der Inhalt ist der Datenstand zum Zeitpunkt data_captured_at (Snapshot bei Finalisierung, keine rückwirkende Rekonstruktion).',
            'norm_filter' => $package->normLabel(),
            'generated_by' => $actor->name,
            'app_version' => (string) config('app.version', '0.1.0-dev'),
        ];
    }

    /**
     * soa: alle SoA-Aussagen des Paket-Scopes inkl. Anforderungs-Referenz,
     * Anwendbarkeit, Begründung, Umsetzungsstatus und gemappten
     * Maßnahmen-Titeln. Optionaler Norm-Filter des Pakets.
     *
     * @return array<int, array<string, mixed>>
     */
    private function soaSection(IsmsAuditPackage $package, int $orgId): array {
        return IsmsApplicabilityStatement::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('isms_scope_id', $package->isms_scope_id)
            ->with(['requirement' => fn($q) => $q->withoutGlobalScopes()->with(['controls' => fn($c) => $c->withoutGlobalScopes()])])
            ->get()
            ->filter(fn(IsmsApplicabilityStatement $s): bool => $this->matchesNormFilter($package, $s->requirement?->getAttribute('norm'), $s->requirement?->getAttribute('edition')))
            ->sortBy(fn(IsmsApplicabilityStatement $s): string => ($s->requirement?->normLabel() ?? '') . '|' . ($s->requirement?->getAttribute('ref_no') ?? ''))
            ->values()
            ->map(fn(IsmsApplicabilityStatement $s): array => [
                'norm' => $s->requirement?->normLabel(),
                'ref_no' => $s->requirement?->getAttribute('ref_no'),
                'title' => $s->requirement?->getAttribute('title'),
                'applicable' => (bool) $s->applicable,
                'justification' => $s->justification,
                'implementation_status' => $s->implementation_status->value,
                'evidence_note' => $s->evidence_note,
                'controls' => $s->requirement?->controls->pluck('title')->values()->all() ?? [],
            ])
            ->all();
    }

    /**
     * risks: Risikoregister inkl. der jüngsten FREIGEGEBENEN Bewertung je
     * Art (gross/net/target) — Entwürfe bleiben außen vor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function riskSection(int $orgId): array {
        return IsmsRisk::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->with([
                'owner:id,name',
                'assessments' => fn($q) => $q->withoutGlobalScopes()
                    ->where('status', AssessmentStatus::Approved->value)
                    ->orderByDesc('assessment_no'),
            ])
            ->orderBy('risk_no')
            ->get()
            ->map(function (IsmsRisk $risk): array {
                // Jüngste freigegebene Bewertung je Art: Assessments sind
                // absteigend nach assessment_no geladen, unique() behält
                // das erste (= jüngste) Vorkommen je kind.
                $latestApprovedByKind = $risk->assessments
                    ->unique(fn(IsmsRiskAssessment $a): string => $a->kind->value)
                    ->values();

                return [
                    'no' => $risk->displayNo(),
                    'title' => $risk->title,
                    'category' => $risk->category->value,
                    'likelihood' => $risk->likelihood,
                    'impact' => $risk->impact,
                    'score' => $risk->score,
                    'treatment' => $risk->treatment->value,
                    'status' => $risk->status->value,
                    'owner' => $risk->owner?->name,
                    'approved_assessments' => $latestApprovedByKind
                        ->map(fn(IsmsRiskAssessment $a): array => [
                            'no' => $a->displayNo(),
                            'kind' => $a->kind->value,
                            'likelihood' => $a->likelihood,
                            'impact' => $a->impact,
                            'score' => $a->score,
                            'approved_at' => $a->approved_at?->toIso8601String(),
                            'valid_until' => $a->valid_until?->toDateString(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * controls: Maßnahmen mit Umsetzungsstatus, Owner und den Referenzen
     * der erfüllten Anforderungen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function controlSection(int $orgId): array {
        return IsmsControl::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->with(['owner:id,name', 'requirements' => fn($q) => $q->withoutGlobalScopes()])
            ->orderBy('title')
            ->get()
            ->map(fn(IsmsControl $control): array => [
                'title' => $control->title,
                'implementation_status' => $control->implementation_status->value,
                'owner' => $control->owner?->name,
                'evidence_note' => $control->evidence_note,
                'requirement_refs' => $control->requirements
                    ->map(fn(IsmsRequirement $r): string => $r->normLabel() . ' ' . $r->ref_no)
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * conformity: Konformitätsstatus des Paket-Scopes (optional
     * norm-gefiltert) inkl. Zertifikate-Metadaten.
     *
     * @return array<int, array<string, mixed>>
     */
    private function conformitySection(IsmsAuditPackage $package, int $orgId): array {
        return IsmsNormStatus::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('isms_scope_id', $package->isms_scope_id)
            ->with(['certificates' => fn($q) => $q->withoutGlobalScopes()->orderByDesc('valid_until')])
            ->get()
            ->filter(fn(IsmsNormStatus $s): bool => $this->matchesNormFilter($package, $s->norm, $s->edition))
            ->sortBy(fn(IsmsNormStatus $s): string => $s->normLabel())
            ->values()
            ->map(fn(IsmsNormStatus $s): array => [
                'norm' => $s->normLabel(),
                'status' => $s->status->value,
                'certificates' => $s->certificates
                    ->map(fn(IsmsCertificate $c): array => [
                        'certificate_no' => $c->certificate_no,
                        'certification_body' => $c->certification_body,
                        'certified_organization' => $c->certified_organization,
                        'issued_on' => $c->issued_on->toDateString(),
                        'valid_from' => $c->valid_from->toDateString(),
                        'valid_until' => $c->valid_until->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * audits: Audits des Paket-Scopes mit Feststellungen und dem Status
     * der Korrekturmaßnahmen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function auditSection(IsmsAuditPackage $package, int $orgId): array {
        return IsmsAudit::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('isms_scope_id', $package->isms_scope_id)
            ->with(['findings' => fn($q) => $q->withoutGlobalScopes()
                ->with(['correctiveActions' => fn($a) => $a->withoutGlobalScopes()->with('owner:id,name')])])
            ->orderBy('audit_no')
            ->get()
            ->map(fn(IsmsAudit $audit): array => [
                'no' => $audit->displayNo(),
                'title' => $audit->title,
                'norm' => $audit->normLabel(),
                'kind' => $audit->kind->value,
                'status' => $audit->status->value,
                'performed_from' => $audit->performed_from?->toDateString(),
                'performed_to' => $audit->performed_to?->toDateString(),
                'summary' => $audit->summary,
                'findings' => $audit->findings
                    ->map(fn(IsmsAuditFinding $finding): array => [
                        'no' => $finding->displayNo(),
                        'kind' => $finding->kind->value,
                        'title' => $finding->title,
                        'status' => $finding->status->value,
                        'corrective_actions' => $finding->correctiveActions
                            ->map(fn(IsmsCorrectiveAction $action): array => [
                                'title' => $action->title,
                                'status' => $action->status->value,
                                'owner' => $action->owner?->name,
                                'due_on' => $action->due_on?->toDateString(),
                                'completed_on' => $action->completed_on?->toDateString(),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * reviews: ausschließlich FREIGEGEBENE Managementbewertungen des
     * Paket-Scopes (Entwürfe sind kein belastbarer Nachweis).
     *
     * @return array<int, array<string, mixed>>
     */
    private function reviewSection(IsmsAuditPackage $package, int $orgId): array {
        return IsmsManagementReview::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('isms_scope_id', $package->isms_scope_id)
            ->where('status', ReviewStatus::Approved->value)
            ->with('approvedBy:id,name')
            ->orderBy('review_no')
            ->get()
            ->map(fn(IsmsManagementReview $review): array => [
                'no' => $review->displayNo(),
                'held_on' => $review->held_on->toDateString(),
                'participants' => $review->participants,
                'inputs' => $review->inputs,
                'decisions' => $review->decisions,
                'follow_ups' => $review->follow_ups,
                'approved_by' => $review->approvedBy?->name,
                'approved_at' => $review->approved_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * software: Inventar-Produkte mit Support-Status und End-of-Life.
     *
     * @return array<int, array<string, mixed>>
     */
    private function softwareSection(int $orgId): array {
        return IsmsSoftwareProduct::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->orderBy('name')
            ->get()
            ->map(fn(IsmsSoftwareProduct $product): array => [
                'name' => $product->name,
                'vendor' => $product->vendor,
                'version' => $product->product_version,
                'category' => $product->category?->value,
                'support_status' => $product->support_status->value,
                'eol_on' => $product->eol_on?->toDateString(),
            ])
            ->all();
    }

    // ── intern ─────────────────────────────────────────────────────────

    /**
     * Optionaler Norm-Filter des Pakets: ohne norm gilt alles; mit norm
     * muss die Norm übereinstimmen, eine gesetzte edition zusätzlich.
     */
    private function matchesNormFilter(IsmsAuditPackage $package, ?string $norm, ?string $edition): bool {
        if ($package->norm === null || $package->norm === '') {
            return true;
        }

        if (! is_string($norm) || strcasecmp($norm, $package->norm) !== 0) {
            return false;
        }

        if ($package->edition === null || $package->edition === '') {
            return true;
        }

        return is_string($edition) && strcasecmp($edition, $package->edition) === 0;
    }

    /**
     * Nächste laufende Paketnummer je Organisation (innerhalb der
     * Transaktion, Muster AuditService::nextNo()).
     */
    private function nextPackageNo(int $organizationId): int {
        $max = IsmsAuditPackage::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->lockForUpdate()
            ->max('package_no');

        return ((int) $max) + 1;
    }

    private function trimmedOrNull(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
