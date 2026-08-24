<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierAssessmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{IncidentSeverity, SupplierAssessmentStatus};
use App\Models\Isms\{IsmsScope, IsmsSupplierAssessment};
use App\Models\Privacy\ProcessingAgreement;
use App\Models\{Supplier, User};
use App\Services\Concerns\AssignsSequentialNo;
use App\Services\Isms\Concerns\AssertsIsmsTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service ISMS-Lieferantenbewertung (Feature 044, MVP 2/3).
 *
 * Geschäftsregeln:
 * - assessment_no: laufende Nummer je Organisation (Vergabe in der
 *   Transaktion, Unique-Index isms_supplier_org_no_uq).
 * - Supplier-Bezug ist OPTIONAL und wird org-gescopt aufgelöst (fremde
 *   Lieferanten ⇒ null). Der Anzeigename (supplier_name) bleibt als
 *   Freitext-Fallback erhalten — bei verknüpftem Supplier wird er, falls
 *   leer, aus dem Lieferantennamen befüllt.
 * - AVV-Kopplung (Feature 044, Welle D): optionaler FK auf ProcessingAgreement
 *   (Feature 043 stabil), org-gescopt aufgelöst (fremde AVV ⇒ null). Die alten
 *   losen Felder (Flag has_dpa + Freitext dpa_ref) bleiben als Fallback.
 * - Statusübergänge ausschließlich über transition() entlang
 *   SupplierAssessmentStatus::allowedTransitions().
 *
 * Audit über den Auditable-Trait plus ein gezieltes audit()-Event für
 * Statusübergänge.
 */
class SupplierAssessmentService {
    use AssertsIsmsTransition;

    use AssignsSequentialNo;

    /**
     * Legt eine Lieferantenbewertung an (Status default draft).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): IsmsSupplierAssessment {
        return DB::transaction(function () use ($creator, $attributes): IsmsSupplierAssessment {
            $supplierId = $this->resolveSupplierId($creator, $attributes['supplier_id'] ?? null);
            $supplierName = $this->resolveSupplierName($supplierId, $attributes['supplier_name'] ?? null);

            return IsmsSupplierAssessment::query()->create([
                'organization_id' => $creator->organization_id,
                'assessment_no' => $this->nextNo(IsmsSupplierAssessment::class, 'assessment_no', 'organization_id', (int) $creator->organization_id),
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'criticality' => $this->severity($attributes['criticality'] ?? null)->value,
                'service_description' => $attributes['service_description'] ?? null,
                'isms_scope_id' => $this->resolveScopeId($creator, $attributes['isms_scope_id'] ?? null),
                'security_requirements' => $attributes['security_requirements'] ?? null,
                'has_nda' => (bool) ($attributes['has_nda'] ?? false),
                'has_dpa' => (bool) ($attributes['has_dpa'] ?? false),
                'dpa_ref' => $attributes['dpa_ref'] ?? null,
                'processing_agreement_id' => $this->resolveProcessingAgreementId($creator, $attributes['processing_agreement_id'] ?? null),
                'audit_right' => (bool) ($attributes['audit_right'] ?? false),
                'last_review_on' => $attributes['last_review_on'] ?? null,
                'next_review_on' => $attributes['next_review_on'] ?? null,
                'risk_rating' => $this->severity($attributes['risk_rating'] ?? null)->value,
                'status' => $attributes['status'] ?? SupplierAssessmentStatus::Draft->value,
                'findings' => $attributes['findings'] ?? null,
                'owner_user_id' => $attributes['owner_user_id'] ?? null,
            ]);
        });
    }

    /**
     * Aktualisiert die Stammdaten. Der Status wird hier NICHT verändert —
     * dafür gibt es transition().
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(IsmsSupplierAssessment $assessment, User $actor, array $attributes): IsmsSupplierAssessment {
        return DB::transaction(function () use ($assessment, $actor, $attributes): IsmsSupplierAssessment {
            $supplierId = array_key_exists('supplier_id', $attributes)
                ? $this->resolveSupplierId($actor, $attributes['supplier_id'])
                : $assessment->supplier_id;
            $supplierName = $this->resolveSupplierName(
                $supplierId,
                $attributes['supplier_name'] ?? $assessment->supplier_name,
            );

            $assessment->update([
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'criticality' => $this->severity($attributes['criticality'] ?? null, $assessment->criticality)->value,
                'service_description' => array_key_exists('service_description', $attributes) ? $attributes['service_description'] : $assessment->service_description,
                'isms_scope_id' => array_key_exists('isms_scope_id', $attributes)
                    ? $this->resolveScopeId($actor, $attributes['isms_scope_id'])
                    : $assessment->isms_scope_id,
                'security_requirements' => array_key_exists('security_requirements', $attributes) ? $attributes['security_requirements'] : $assessment->security_requirements,
                'has_nda' => array_key_exists('has_nda', $attributes) ? (bool) $attributes['has_nda'] : $assessment->has_nda,
                'has_dpa' => array_key_exists('has_dpa', $attributes) ? (bool) $attributes['has_dpa'] : $assessment->has_dpa,
                'dpa_ref' => array_key_exists('dpa_ref', $attributes) ? $attributes['dpa_ref'] : $assessment->dpa_ref,
                'processing_agreement_id' => array_key_exists('processing_agreement_id', $attributes)
                    ? $this->resolveProcessingAgreementId($actor, $attributes['processing_agreement_id'])
                    : $assessment->processing_agreement_id,
                'audit_right' => array_key_exists('audit_right', $attributes) ? (bool) $attributes['audit_right'] : $assessment->audit_right,
                'last_review_on' => array_key_exists('last_review_on', $attributes) ? $attributes['last_review_on'] : $assessment->last_review_on,
                'next_review_on' => array_key_exists('next_review_on', $attributes) ? $attributes['next_review_on'] : $assessment->next_review_on,
                'risk_rating' => $this->severity($attributes['risk_rating'] ?? null, $assessment->risk_rating)->value,
                'findings' => array_key_exists('findings', $attributes) ? $attributes['findings'] : $assessment->findings,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes) ? $attributes['owner_user_id'] : $assessment->owner_user_id,
            ]);

            return $assessment;
        });
    }

    /**
     * Statusübergang entlang der State-Machine
     * ({@see SupplierAssessmentStatus::allowedTransitions()}).
     *
     * @throws ValidationException bei unzulässigem Übergang
     */
    public function transition(IsmsSupplierAssessment $assessment, SupplierAssessmentStatus $target, User $actor): IsmsSupplierAssessment {
        if ($assessment->status === $target) {
            return $assessment;
        }

        // Gemeinsamer ISMS-Guard (Vollaudit 2026-07, M44).
        $this->assertIsmsTransition($assessment->status, $target);

        return DB::transaction(function () use ($assessment, $target, $actor): IsmsSupplierAssessment {
            $from = $assessment->status;
            $changes = ['status' => $target->value];

            // Die Freigabe dokumentiert den letzten Prüfzeitpunkt, falls noch
            // keiner gesetzt ist (last_review_on = heute).
            if ($target === SupplierAssessmentStatus::Approved && $assessment->last_review_on === null) {
                $changes['last_review_on'] = now()->toDateString();
            }

            $assessment->update($changes);
            $assessment->audit('isms.supplier_assessment.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $assessment;
        });
    }

    /** Soft-Delete (Policy: isms.manage bzw. Admin). */
    public function delete(IsmsSupplierAssessment $assessment, User $actor): void {
        DB::transaction(function () use ($assessment, $actor): void {
            $assessment->audit('isms.supplier_assessment.deleted', ['actor_user_id' => $actor->id]);
            $assessment->delete();
        });
    }

    /** Severity-Wert mit Fallback (explizit → bisheriger → medium). */
    private function severity(?string $explicit, ?IncidentSeverity $current = null): IncidentSeverity {
        if ($explicit !== null && IncidentSeverity::tryFrom($explicit) !== null) {
            return IncidentSeverity::from($explicit);
        }

        return $current ?? IncidentSeverity::Medium;
    }

    /**
     * Löst die Supplier-ID org-gescopt auf (fremde Lieferanten ⇒ null), damit
     * über die Mandantengrenze hinweg keine Verknüpfung möglich ist.
     */
    private function resolveSupplierId(User $actor, mixed $supplierId): ?int {
        if (empty($supplierId)) {
            return null;
        }

        $exists = Supplier::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $actor->organization_id)
            ->whereKey((int) $supplierId)
            ->exists();

        return $exists ? (int) $supplierId : null;
    }

    /**
     * Anzeigename: bei verknüpftem Supplier den Lieferantennamen übernehmen,
     * falls kein Freitext gepflegt ist; sonst den Freitext (Pflicht ohne
     * Verknüpfung, im Request validiert).
     */
    private function resolveSupplierName(?int $supplierId, mixed $name): string {
        $name = is_string($name) ? trim($name) : '';

        if ($name === '' && $supplierId !== null) {
            $supplier = Supplier::query()->withoutGlobalScopes()->find($supplierId);
            if ($supplier !== null) {
                $name = (string) $supplier->name;
            }
        }

        return $name;
    }

    /**
     * Löst die AVV-ID (ProcessingAgreement) org-gescopt auf (fremde AVV ⇒
     * null). Defense-in-depth zur org-gescopten Validierung im Request: über
     * die Mandantengrenze hinweg ist keine Verknüpfung möglich, auch nicht bei
     * direktem Service-Aufruf.
     */
    private function resolveProcessingAgreementId(User $actor, mixed $agreementId): ?int {
        if (empty($agreementId)) {
            return null;
        }

        $exists = ProcessingAgreement::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $actor->organization_id)
            ->whereKey((int) $agreementId)
            ->exists();

        return $exists ? (int) $agreementId : null;
    }

    /** Löst die Scope-ID org-gescopt auf (fremde Scopes ⇒ null). */
    private function resolveScopeId(User $actor, mixed $scopeId): ?int {
        if (empty($scopeId)) {
            return null;
        }

        $exists = IsmsScope::query()
            ->where('organization_id', $actor->organization_id)
            ->whereKey((int) $scopeId)
            ->exists();

        return $exists ? (int) $scopeId : null;
    }

}
