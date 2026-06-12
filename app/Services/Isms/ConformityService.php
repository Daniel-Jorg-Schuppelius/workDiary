<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConformityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\NormConformityStatus;
use App\Models\{Document, User};
use App\Models\Isms\{IsmsCertificate, IsmsNormStatus, IsmsRequirement, IsmsScope};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service Konformitätsstatus + Zertifikatsregister (Feature 046,
 * Inkrement B). Erzwingt zentral die STRIKTE 046-Regel:
 *
 * - Der Wechsel auf `certified` ist NUR zulässig, wenn ein hinterlegtes
 *   Zertifikat existiert, das HEUTE gültig ist (valid_from ≤ heute ≤
 *   valid_until) und alle 046-Pflichtfelder trägt. Ein Reifegrad, eine
 *   vollständige Checkliste oder das Fehlen offener Maßnahmen löst NIE
 *   automatisch `certified` aus.
 * - Statusübergänge laufen ausschließlich entlang
 *   {@see NormConformityStatus::allowedTransitions()}.
 * - expireOverdue() setzt `certified`-Status ohne heute gültiges
 *   Zertifikat automatisch auf `certificateExpired` (Fristen-Scanner,
 *   auditiert via Auditable).
 */
class ConformityService {
    /**
     * 046-Pflichtfelder des Zertifikats (Norm + Ausgabe ergeben sich aus
     * dem NormStatus; Überwachungstermine sind optional).
     *
     * @var list<string>
     */
    private const REQUIRED_CERTIFICATE_FIELDS = [
        'certified_organization',
        'scope_description',
        'certification_body',
        'certificate_no',
        'issued_on',
        'valid_from',
        'valid_until',
    ];

    /**
     * Legt eine Statuszeile (Org + Scope + Norm/Ausgabe) manuell an —
     * Start immer bei `notAssessed` (Statuskette nur über transition()).
     *
     * @param  array<string, mixed>  $attributes  norm, edition?, notes?
     *
     * @throws ValidationException bei bereits vorhandener Norm/Ausgabe im Scope
     */
    public function create(User $creator, IsmsScope $scope, array $attributes): IsmsNormStatus {
        $norm = trim((string) ($attributes['norm'] ?? ''));
        $edition = trim((string) ($attributes['edition'] ?? ''));
        $edition = $edition !== '' ? $edition : '-';

        $exists = IsmsNormStatus::query()
            ->withTrashed()
            ->where('isms_scope_id', $scope->id)
            ->where('norm', $norm)
            ->where('edition', $edition)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'norm' => __('isms.error.norm_status_exists'),
            ]);
        }

        return IsmsNormStatus::query()->create([
            'organization_id' => $creator->organization_id,
            'isms_scope_id' => $scope->id,
            'norm' => $norm,
            'edition' => $edition,
            'status' => NormConformityStatus::NotAssessed->value,
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    /**
     * Legt fehlende Statuszeilen für einen Geltungsbereich an — je
     * norm/edition-Paar der Org-Anforderungen eine Zeile (notAssessed).
     * Idempotent; soft-gelöschte Zeilen gelten als bewusst entfernt.
     *
     * @return int Anzahl neu angelegter Statuszeilen
     */
    public function ensureStatusesForScope(IsmsScope $scope): int {
        return DB::transaction(function () use ($scope): int {
            $pairs = IsmsRequirement::query()
                ->select(['norm', 'edition'])
                ->distinct()
                ->get();

            $existing = IsmsNormStatus::query()
                ->withTrashed()
                ->where('isms_scope_id', $scope->id)
                ->get()
                ->map(fn(IsmsNormStatus $s): string => $s->norm . '|' . $s->edition)
                ->flip();

            $created = 0;
            foreach ($pairs as $pair) {
                if (isset($existing[$pair->norm . '|' . $pair->edition])) {
                    continue;
                }

                IsmsNormStatus::query()->create([
                    'organization_id' => $scope->organization_id,
                    'isms_scope_id' => $scope->id,
                    'norm' => $pair->norm,
                    'edition' => $pair->edition,
                    'status' => NormConformityStatus::NotAssessed->value,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /**
     * Statusübergang entlang der State-Machine — `certified` NUR mit
     * heute gültigem, vollständigem Zertifikat (strikte 046-Regel).
     *
     * @throws ValidationException bei unzulässigem Übergang oder
     *                             fehlendem/ungültigem Zertifikat
     */
    public function transition(IsmsNormStatus $status, NormConformityStatus $target, User $actor): IsmsNormStatus {
        if ($status->status === $target) {
            return $status;
        }

        if (! in_array($target, $status->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.invalid_transition', [
                    'from' => $status->status->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        if ($target === NormConformityStatus::Certified) {
            $certificate = $status->activeCertificate();

            if ($certificate === null || ! $this->certificateComplete($certificate)) {
                throw ValidationException::withMessages([
                    'status' => __('isms.error.certificate_required'),
                ]);
            }
        }

        return DB::transaction(function () use ($status, $target, $actor): IsmsNormStatus {
            $from = $status->status;
            $status->update(['status' => $target->value]);
            $status->audit('isms.norm_status.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $status;
        });
    }

    /**
     * Hinterlegt ein Zertifikat zu einem Konformitätsstatus. Validiert die
     * 046-Pflichtfelder, den Gültigkeitszeitraum (valid_until > valid_from)
     * und löst die optionale Dokumentenreferenz org-sicher auf (die
     * org-gescopte Document-Query sieht fremde Dokumente nicht).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function addCertificate(IsmsNormStatus $status, User $actor, array $attributes): IsmsCertificate {
        $errors = [];

        foreach (self::REQUIRED_CERTIFICATE_FIELDS as $field) {
            if (trim((string) ($attributes[$field] ?? '')) === '') {
                $errors[$field] = __('validation.required', ['attribute' => __('isms.field.' . $field)]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $validFrom = Carbon::parse((string) $attributes['valid_from'])->startOfDay();
        $validUntil = Carbon::parse((string) $attributes['valid_until'])->startOfDay();

        if ($validUntil->lte($validFrom)) {
            throw ValidationException::withMessages([
                'valid_until' => __('isms.error.certificate_period_invalid'),
            ]);
        }

        $documentId = $this->resolveDocumentId($attributes['document_id'] ?? null);

        return DB::transaction(function () use ($status, $actor, $attributes, $documentId): IsmsCertificate {
            $certificate = IsmsCertificate::query()->create([
                'organization_id' => $status->organization_id,
                'isms_norm_status_id' => $status->id,
                'certified_organization' => trim((string) $attributes['certified_organization']),
                'scope_description' => trim((string) $attributes['scope_description']),
                'certification_body' => trim((string) $attributes['certification_body']),
                'certificate_no' => trim((string) $attributes['certificate_no']),
                'issued_on' => $attributes['issued_on'],
                'valid_from' => $attributes['valid_from'],
                'valid_until' => $attributes['valid_until'],
                'surveillance_audit_1_on' => $attributes['surveillance_audit_1_on'] ?? null,
                'surveillance_audit_2_on' => $attributes['surveillance_audit_2_on'] ?? null,
                'document_id' => $documentId,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $certificate->audit('isms.certificate.added', ['actor_user_id' => $actor->id]);

            return $certificate;
        });
    }

    /**
     * Automatischer Verfall: setzt `certified`-Status ohne heute gültiges
     * Zertifikat auf `certificateExpired` (auditiert). Läuft im
     * Fristen-Scanner ohne Mandantenkontext und sieht damit alle
     * Organisationen; optional auf eine Organisation begrenzbar.
     *
     * @return int Anzahl umgestellter Statuszeilen
     */
    public function expireOverdue(?int $organizationId = null): int {
        $today = Carbon::today();

        $expired = 0;
        IsmsNormStatus::query()
            ->where('status', NormConformityStatus::Certified->value)
            ->when($organizationId !== null, fn($query) => $query->where('organization_id', $organizationId))
            ->whereNotExists(function ($query) use ($today): void {
                $query->selectRaw('1')
                    ->from('isms_certificates')
                    ->whereColumn('isms_certificates.isms_norm_status_id', 'isms_norm_statuses.id')
                    ->whereNull('isms_certificates.deleted_at')
                    ->whereDate('isms_certificates.valid_from', '<=', $today)
                    ->whereDate('isms_certificates.valid_until', '>=', $today);
            })
            ->chunkById(100, function ($statuses) use (&$expired): void {
                foreach ($statuses as $status) {
                    DB::transaction(function () use ($status): void {
                        $status->update(['status' => NormConformityStatus::CertificateExpired->value]);
                        $status->audit('isms.norm_status.expired', [
                            'from' => NormConformityStatus::Certified->value,
                            'to' => NormConformityStatus::CertificateExpired->value,
                        ]);
                    });
                    $expired++;
                }
            });

        return $expired;
    }

    /** Trägt das Zertifikat alle 046-Pflichtfelder? (defensiv zur DB) */
    private function certificateComplete(IsmsCertificate $certificate): bool {
        foreach (self::REQUIRED_CERTIFICATE_FIELDS as $field) {
            if (trim((string) $certificate->getAttribute($field)) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Löst die optionale Dokumentenreferenz org-sicher auf: die
     * org-gescopte Document-Query (BelongsToOrganization) sieht fremde
     * Dokumente nicht — unbekannte/fremde IDs werden abgewiesen.
     *
     * @throws ValidationException bei unbekanntem/fremdem Dokument
     */
    private function resolveDocumentId(mixed $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $id = is_numeric($value) ? (int) $value : null;
        $document = $id !== null && $id > 0 ? Document::query()->whereKey($id)->first() : null;

        if ($document === null) {
            throw ValidationException::withMessages([
                'document_id' => __('validation.exists', ['attribute' => __('isms.field.document')]),
            ]);
        }

        return (int) $document->id;
    }
}
