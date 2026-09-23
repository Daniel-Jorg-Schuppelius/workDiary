<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSigningService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\Contract\{ContractObligationKind, EvidenceReviewStatus, SignatureLinkPurpose, SignatureMethod, SignatureParty, SignatureRequestStatus, SigningRevisionStatus};
use App\Enums\Notification\NotificationEvent;
use App\Mail\AgreementLinkMail;
use App\Models\Contract\{Contract, ContractSignatureEvidence, ContractSignatureLink, ContractSignatureRequest, ContractSigningManifestItem, ContractSigningRevision};
use App\Models\Document\DocumentVersion;
use App\Models\Platform\{Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Notification\NotificationDispatcher;
use App\Support\DocumentLocale;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\{CryptoHelper, DataUrlHelper};
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{DB, Log, Mail, Storage};
use RuntimeException;

/**
 * Unterzeichnungsschicht der Kundenvereinbarungen (Feature 157, MVP-822):
 * Fassungen einfrieren (Manifest mit SHA-256 je gebundener Dokumentversion),
 * Einmal-Links ausgeben/versenden/widerrufen, Browser-Signatur und
 * PDF-Upload mit Prüfung entgegennehmen, Gegenzeichnung, atomarer Abschluss,
 * Rückzug, Ablösung, Portal-Freigabe und das Exportpaket. Alle Zustandsprüfungen
 * liegen hier — Controller (intern, öffentlich, Portal) rufen nur.
 */
class ContractSigningService {
    use AssertsStatusTransition;

    public const DEFAULT_TTL_DAYS = 7;

    public const DOWNLOAD_TTL_DAYS = 30;

    public const MAX_SIGNATURE_BYTES = 1_000_000;

    public const DISK = 'local';

    public function __construct(
        private readonly NotificationDispatcher $notifier,
        private readonly SigningCertificatePdfRenderer $certificates,
    ) {}

    // ── Fassungen ──────────────────────────────────────────────────────────

    /**
     * Neue Fassung (Entwurf) mit gebundenen Dokumentversionen und den beiden
     * Parteien. Nur eine offene Fassung je Vertrag.
     *
     * @param array<string, mixed> $data
     */
    public function createRevision(Contract $contract, User $actor, array $data): ContractSigningRevision {
        $this->assertSigningContract($contract);
        if ($contract->signingRevisions()->whereIn('status', [SigningRevisionStatus::Draft->value, SigningRevisionStatus::Ready->value, SigningRevisionStatus::PartiallySigned->value])->exists()) {
            throw new RuntimeException((string) __('contract-signing.error.open_revision_exists'));
        }

        return DB::transaction(function () use ($contract, $actor, $data): ContractSigningRevision {
            $revisionNo = (int) $contract->signingRevisions()->max('revision_no') + 1;
            $revision = ContractSigningRevision::query()->create([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
                'revision_no' => $revisionNo,
                'status' => SigningRevisionStatus::Draft->value,
                'controller_party' => $data['controller_party'] ?? null,
                'declaration_text' => trim((string) $data['declaration_text']),
                'review_on' => $data['review_on'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->syncManifest($revision, $contract, $data);
            $this->syncRequests($revision, $data);

            $contract->audit('contract.signing.revisionCreated', ['revision_id' => $revision->id, 'revision_no' => $revisionNo, 'by' => $actor->id]);

            return $revision->fresh(['manifestItems', 'requests']) ?? $revision;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateRevision(ContractSigningRevision $revision, User $actor, array $data): ContractSigningRevision {
        if ($revision->status !== SigningRevisionStatus::Draft) {
            throw new RuntimeException((string) __('contract-signing.error.frozen'));
        }
        $contract = $revision->contract()->firstOrFail();

        return DB::transaction(function () use ($revision, $contract, $actor, $data): ContractSigningRevision {
            $revision->forceFill([
                'controller_party' => $data['controller_party'] ?? null,
                'declaration_text' => trim((string) $data['declaration_text']),
                'review_on' => $data['review_on'] ?? null,
            ])->save();

            $revision->manifestItems()->delete();
            $revision->requests()->delete();
            $this->syncManifest($revision, $contract, $data);
            $this->syncRequests($revision, $data);

            $contract->audit('contract.signing.revisionUpdated', ['revision_id' => $revision->id, 'by' => $actor->id]);

            return $revision->fresh(['manifestItems', 'requests']) ?? $revision;
        });
    }

    /**
     * Bereitstellen = Einfrieren: Hashes gegen den Speicher prüfen,
     * Manifest-Hash bilden, Status „zur Unterschrift bereit".
     */
    public function prepare(ContractSigningRevision $revision, User $actor): ContractSigningRevision {
        $this->assertStatusTransition($revision->status, SigningRevisionStatus::Ready);
        $contract = $revision->contract()->firstOrFail();
        if (! $contract->status->isOpen()) {
            throw new RuntimeException((string) __('contract-signing.error.contract_closed'));
        }
        if (! $revision->requests()->where('required', true)->exists()) {
            throw new RuntimeException((string) __('contract-signing.error.no_required_party'));
        }

        return DB::transaction(function () use ($revision, $contract, $actor): ContractSigningRevision {
            $mismatches = $this->verifyManifest($revision);
            if ($mismatches !== []) {
                throw new RuntimeException((string) __('contract-signing.error.manifest_changed', ['files' => implode(', ', $mismatches)]));
            }

            $revision->forceFill([
                'status' => SigningRevisionStatus::Ready->value,
                'manifest_hash' => $this->manifestHash($revision),
                'prepared_at' => now(),
                'prepared_by' => $actor->id,
            ])->save();

            $contract->audit('contract.signing.prepared', ['revision_id' => $revision->id, 'manifest_hash' => $revision->manifest_hash, 'by' => $actor->id]);

            return $revision;
        });
    }

    public function withdraw(ContractSigningRevision $revision, User $actor, string $reason): ContractSigningRevision {
        $this->assertStatusTransition($revision->status, SigningRevisionStatus::Withdrawn);

        return DB::transaction(function () use ($revision, $actor, $reason): ContractSigningRevision {
            $this->expireOpenLinks($revision, $actor);
            $revision->forceFill([
                'status' => SigningRevisionStatus::Withdrawn->value,
                'withdrawn_at' => now(),
                'withdrawn_by' => $actor->id,
                'withdrawal_reason' => trim($reason),
            ])->save();

            $revision->contract?->audit('contract.signing.withdrawn', ['revision_id' => $revision->id, 'reason' => trim($reason), 'by' => $actor->id]);

            return $revision;
        });
    }

    /**
     * Ausdrückliche Ablösung: die neue, vollständig unterzeichnete Fassung
     * ersetzt die bisherige zum Wirksamkeitsdatum. Ein Entwurf ersetzt nie.
     */
    public function supersede(ContractSigningRevision $successor, ContractSigningRevision $predecessor, User $actor, CarbonInterface $effectiveOn): ContractSigningRevision {
        if ((int) $successor->contract_id !== (int) $predecessor->contract_id || $successor->id === $predecessor->id) {
            throw new RuntimeException((string) __('contract-signing.error.supersede_mismatch'));
        }
        if ($successor->status !== SigningRevisionStatus::Signed) {
            throw new RuntimeException((string) __('contract-signing.error.successor_not_signed'));
        }
        $this->assertStatusTransition($predecessor->status, SigningRevisionStatus::Superseded);

        return DB::transaction(function () use ($successor, $predecessor, $actor, $effectiveOn): ContractSigningRevision {
            $predecessor->forceFill([
                'status' => SigningRevisionStatus::Superseded->value,
                'superseded_by_id' => $successor->id,
                'superseded_at' => now(),
            ])->save();
            $successor->forceFill([
                'predecessor_id' => $predecessor->id,
                'effective_on' => $effectiveOn->toDateString(),
            ])->save();

            $successor->contract?->audit('contract.signing.superseded', [
                'revision_id' => $successor->id,
                'predecessor_id' => $predecessor->id,
                'effective_on' => $effectiveOn->toDateString(),
                'by' => $actor->id,
            ]);

            return $successor;
        });
    }

    public function releaseToCustomer(ContractSigningRevision $revision, User $actor): ContractSigningRevision {
        if (! $revision->status->isSigned()) {
            throw new RuntimeException((string) __('contract-signing.error.not_signed'));
        }
        $revision->forceFill(['customer_visible_at' => now(), 'customer_visible_by' => $actor->id])->save();
        $revision->contract?->audit('contract.signing.portalReleased', ['revision_id' => $revision->id, 'by' => $actor->id]);

        return $revision;
    }

    public function revokeFromCustomer(ContractSigningRevision $revision, User $actor): ContractSigningRevision {
        $revision->forceFill(['customer_visible_at' => null, 'customer_visible_by' => null])->save();
        $revision->contract?->audit('contract.signing.portalRevoked', ['revision_id' => $revision->id, 'by' => $actor->id]);

        return $revision;
    }

    // ── Links ──────────────────────────────────────────────────────────────

    /**
     * Signaturlink für eine Anforderung; ein neuer Link sperrt den bisherigen.
     * Liefert das Klartext-Token — es wird sonst nirgends gespeichert.
     *
     * @return array{token: string, link: ContractSignatureLink}
     */
    public function issueSignLink(ContractSignatureRequest $request, User $actor, ?int $ttlDays = null): array {
        $revision = $request->revision()->firstOrFail();
        if (! $revision->status->acceptsSignatures()) {
            throw new RuntimeException((string) __('contract-signing.error.revision_not_ready'));
        }
        if (! $request->status->acceptsSubmission()) {
            throw new RuntimeException((string) __('contract-signing.error.request_fulfilled'));
        }

        return DB::transaction(function () use ($request, $revision, $actor, $ttlDays): array {
            foreach ($request->links()->get() as $previous) {
                if ($previous->isUsable()) {
                    $previous->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
                }
            }

            $issued = $this->createLink($revision, $request, SignatureLinkPurpose::Sign, $actor, $ttlDays ?? self::DEFAULT_TTL_DAYS);
            $revision->contract?->audit('contract.signing.linkIssued', [
                'revision_id' => $revision->id,
                'request_id' => $request->id,
                'party' => $request->party->value,
                'expires_at' => $issued['link']->expires_at->toIso8601String(),
                'by' => $actor->id,
            ]);

            return $issued;
        });
    }

    /**
     * Befristeter, widerrufbarer Abruflink auf das Ergebnis — getrennt vom
     * (verbrauchten) Signaturtoken.
     *
     * @return array{token: string, link: ContractSignatureLink}
     */
    public function issueDownloadLink(ContractSigningRevision $revision, User $actor, ?int $ttlDays = null): array {
        if (! $revision->status->isSigned()) {
            throw new RuntimeException((string) __('contract-signing.error.not_signed'));
        }

        return DB::transaction(function () use ($revision, $actor, $ttlDays): array {
            $issued = $this->createLink($revision, null, SignatureLinkPurpose::Download, $actor, $ttlDays ?? self::DOWNLOAD_TTL_DAYS);
            $revision->contract?->audit('contract.signing.downloadLinkIssued', [
                'revision_id' => $revision->id,
                'expires_at' => $issued['link']->expires_at->toIso8601String(),
                'by' => $actor->id,
            ]);

            return $issued;
        });
    }

    /**
     * Link erzeugen und an den angezeigten Empfänger senden. Versandfehler
     * bleiben am Link sichtbar und erzeugen keinen neuen Vorgang.
     */
    public function sendSignLink(ContractSignatureRequest $request, User $actor): ContractSignatureLink {
        $email = trim((string) $request->signer_email);
        if ($email === '') {
            throw new RuntimeException((string) __('contract-signing.error.no_recipient'));
        }
        $issued = $this->issueSignLink($request, $actor);

        return $this->deliver($issued['link'], $issued['token'], $email, $actor);
    }

    public function sendDownloadLink(ContractSigningRevision $revision, User $actor, string $email): ContractSignatureLink {
        $issued = $this->issueDownloadLink($revision, $actor);

        return $this->deliver($issued['link'], $issued['token'], $email, $actor);
    }

    public function revokeLink(ContractSignatureLink $link, User $actor): ContractSignatureLink {
        if ($link->used_at !== null) {
            throw new RuntimeException((string) __('contract-signing.error.link_used'));
        }
        $link->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
        $link->revision?->contract?->audit('contract.signing.linkRevoked', ['revision_id' => $link->revision_id, 'link_id' => $link->id, 'purpose' => $link->purpose->value, 'by' => $actor->id]);

        return $link;
    }

    /**
     * Öffentliche Auflösung: nur über den Token-Hash, ohne Mandantenkontext.
     * Der Aufrufer bindet danach die Organisation der Fassung.
     */
    public function resolveLink(string $token, SignatureLinkPurpose $purpose): ContractSignatureLink {
        $link = ContractSignatureLink::query()
            ->withoutGlobalScopes()
            ->where('token_hash', CryptoHelper::hash($token))
            ->where('purpose', $purpose->value)
            ->first();
        if ($link === null) {
            throw new RuntimeException((string) __('contract-signing.error.link_unknown'));
        }
        if (! $link->isUsable()) {
            throw new RuntimeException((string) __('contract-signing.error.link_unusable'));
        }

        return $link;
    }

    /** Erster Aufruf des Links — GET verändert sonst nichts. */
    public function markOpened(ContractSignatureLink $link): void {
        if ($link->opened_at !== null) {
            return;
        }
        $link->forceFill(['opened_at' => now()])->save();
        $link->revision?->contract?->audit('contract.signing.linkOpened', ['revision_id' => $link->revision_id, 'link_id' => $link->id, 'purpose' => $link->purpose->value]);
    }

    // ── Unterschriften und Nachweise ───────────────────────────────────────

    /**
     * Browser-Unterzeichnung über den öffentlichen Link (gezeichnet oder
     * getippt). Der Link wird verbraucht.
     *
     * @param array{signer_name: string, signer_function?: ?string, signature_method: string, signature?: ?string, declaration_accepted: bool, authority_confirmed: bool} $data
     */
    public function signViaLink(ContractSignatureLink $link, array $data, ?Request $http = null): ContractSignatureEvidence {
        $request = $link->request()->firstOrFail();

        return $this->recordSignature($request, $link, null, $data, $http);
    }

    /**
     * Gegenzeichnung der Organisationsseite im angemeldeten Bereich.
     *
     * @param array{signer_name: string, signer_function?: ?string, signature_method: string, signature?: ?string, declaration_accepted: bool, authority_confirmed: bool} $data
     */
    public function countersign(ContractSignatureRequest $request, User $actor, array $data, ?Request $http = null): ContractSignatureEvidence {
        if ($request->party !== SignatureParty::Organization) {
            throw new RuntimeException((string) __('contract-signing.error.countersign_party'));
        }

        return $this->recordSignature($request, null, $actor, $data, $http);
    }

    /**
     * Unterschriebenes PDF über den öffentlichen Link — Ergebnis „Nachweis
     * eingegangen", die Unterzeichnung zählt erst nach bestätigter Prüfung.
     *
     * @param array{signer_name: string, stated_signed_on?: ?string} $data
     */
    public function submitUploadViaLink(ContractSignatureLink $link, UploadedFile $file, array $data, ?Request $http = null): ContractSignatureEvidence {
        $request = $link->request()->firstOrFail();

        return $this->recordUpload($request, $link, null, $file, $data, $http);
    }

    /**
     * Extern eingegangenes PDF (Post/E-Mail) durch einen berechtigten
     * Mitarbeiter nachtragen — Erfasser und Unterzeichner bleiben getrennt.
     *
     * @param array{signer_name: string, stated_signed_on?: ?string} $data
     */
    public function recordUploadInternally(ContractSignatureRequest $request, User $actor, UploadedFile $file, array $data, ?Request $http = null): ContractSignatureEvidence {
        return $this->recordUpload($request, null, $actor, $file, $data, $http);
    }

    /** Prüfentscheidung zum hochgeladenen Nachweis; Ablehnung braucht eine Begründung. */
    public function reviewEvidence(ContractSignatureEvidence $evidence, User $actor, bool $accept, ?string $note): ContractSignatureEvidence {
        if (! $evidence->isPendingReview()) {
            throw new RuntimeException((string) __('contract-signing.error.review_done'));
        }
        $note = trim((string) $note);
        if (! $accept && $note === '') {
            throw new RuntimeException((string) __('contract-signing.error.rejection_needs_note'));
        }

        return DB::transaction(function () use ($evidence, $actor, $accept, $note): ContractSignatureEvidence {
            /** @var ContractSignatureRequest $request */
            $request = ContractSignatureRequest::query()->whereKey($evidence->request_id)->lockForUpdate()->firstOrFail();
            $revision = $request->revision()->firstOrFail();
            $contract = $revision->contract()->firstOrFail();

            $evidence->forceFill([
                'review_status' => ($accept ? EvidenceReviewStatus::Accepted : EvidenceReviewStatus::Rejected)->value,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note !== '' ? $note : null,
            ])->save();

            if ($accept) {
                if ($request->status->isFulfilled()) {
                    throw new RuntimeException((string) __('contract-signing.error.request_fulfilled'));
                }
                $this->assertManifestIntact($revision);
                $request->forceFill(['status' => SignatureRequestStatus::Signed->value, 'fulfilled_at' => now()])->save();
                $contract->audit('contract.signing.evidenceAccepted', ['revision_id' => $revision->id, 'evidence_id' => $evidence->id, 'party' => $request->party->value, 'by' => $actor->id]);
                $this->settleRevision($revision, $actor);
            } else {
                $request->forceFill(['status' => SignatureRequestStatus::EvidenceRejected->value])->save();
                $contract->audit('contract.signing.evidenceRejected', ['revision_id' => $revision->id, 'evidence_id' => $evidence->id, 'party' => $request->party->value, 'note' => $note, 'by' => $actor->id]);
            }

            return $evidence;
        });
    }

    // ── Nachweis und Paket ─────────────────────────────────────────────────

    /** Abschlussnachweis als PDF (Parteien, Fassungskennung, Dateihashes, Methoden, Zeitpunkte). */
    public function certificatePdf(ContractSigningRevision $revision): string {
        if (! $revision->status->isSigned()) {
            throw new RuntimeException((string) __('contract-signing.error.not_signed'));
        }

        return $this->certificates->output($revision);
    }

    /**
     * Downloadpaket: Originale, Anlagen, eingegangene unterschriebene PDFs
     * (nur bestätigte) und der Abschlussnachweis — Dateiname → Bytes.
     *
     * @return array<string, string>
     */
    public function packageFiles(ContractSigningRevision $revision): array {
        if (! $revision->status->isSigned()) {
            throw new RuntimeException((string) __('contract-signing.error.not_signed'));
        }
        $files = [];
        foreach ($revision->manifestItems()->with('documentVersion')->get() as $index => $item) {
            $files[sprintf('%02d-%s-%s', $index + 1, $item->role, File::sanitizeDisplayName($item->original_name))] = $this->readVersion($item->documentVersion);
        }
        $n = 0;
        foreach ($revision->evidences()->get() as $evidence) {
            if (! $evidence->countsAsSignature() || ! $evidence->hasFile()) {
                continue;
            }
            $ext = Str::of((string) $evidence->path)->afterLast('.')->toString();
            $files[sprintf('nachweis-%02d-%s.%s', ++$n, $evidence->party->value, $ext !== '' ? $ext : 'bin')] = (string) Storage::disk((string) $evidence->disk)->get((string) $evidence->path);
        }
        $files['abschlussnachweis.pdf'] = $this->certificatePdf($revision);

        return $files;
    }

    /**
     * Hashes der gebundenen Versionen gegen den Speicher prüfen — Namen der
     * abweichenden oder fehlenden Dateien.
     *
     * @return list<string>
     */
    public function verifyManifest(ContractSigningRevision $revision): array {
        $mismatches = [];
        foreach ($revision->manifestItems()->with('documentVersion')->get() as $item) {
            $version = $item->documentVersion;
            if ($version === null || ! Storage::disk($version->disk)->exists($version->path)) {
                $mismatches[] = $item->original_name;

                continue;
            }
            if (CryptoHelper::hash($this->readVersion($version)) !== $item->sha256) {
                $mismatches[] = $item->original_name;
            }
        }

        return $mismatches;
    }

    /** Öffentlich lesbare Datei des Manifests (nur die gebundene Version, nie „aktuell"). */
    public function manifestItem(ContractSigningRevision $revision, int $sort): ContractSigningManifestItem {
        return $revision->manifestItems()->where('sort', $sort)->with('documentVersion')->firstOrFail();
    }

    public function readVersion(?DocumentVersion $version): string {
        if ($version === null) {
            throw new RuntimeException((string) __('contract-signing.error.file_missing'));
        }

        return (string) Storage::disk($version->disk)->get($version->path);
    }

    /** Organisation der Fassung ohne Mandantenkontext (öffentliche Wege). */
    public function organizationOf(ContractSignatureLink $link): ?Organization {
        return Organization::query()->withoutGlobalScopes()->find($link->organization_id);
    }

    // ── intern ─────────────────────────────────────────────────────────────

    /**
     * @param array{signer_name: string, signer_function?: ?string, signature_method: string, signature?: ?string, declaration_accepted: bool, authority_confirmed: bool} $data
     */
    private function recordSignature(ContractSignatureRequest $target, ?ContractSignatureLink $link, ?User $actor, array $data, ?Request $http): ContractSignatureEvidence {
        $method = SignatureMethod::tryFrom((string) $data['signature_method']);
        if ($method === null || $method === SignatureMethod::Upload) {
            throw new RuntimeException((string) __('contract-signing.error.method_invalid'));
        }
        if (! $data['declaration_accepted'] || ! $data['authority_confirmed']) {
            throw new RuntimeException((string) __('contract-signing.error.declaration_required'));
        }

        $image = null;
        if ($method === SignatureMethod::Drawn) {
            $image = DataUrlHelper::decode((string) ($data['signature'] ?? ''), ['image/png'], self::MAX_SIGNATURE_BYTES);
            if ($image === false || $image === '') {
                throw new RuntimeException((string) __('contract-signing.error.signature_empty'));
            }
        }

        return DB::transaction(function () use ($target, $link, $actor, $data, $http, $method, $image): ContractSignatureEvidence {
            [$request, $revision, $contract] = $this->lockRequest($target);
            $this->assertAcceptsSubmission($request, $revision);
            $this->assertManifestIntact($revision);
            if ($link !== null) {
                $this->consumeLink($link);
            }

            $file = $image !== null ? $this->storeEvidenceFile($image, 'png') : null;
            $evidence = ContractSignatureEvidence::query()->create(array_merge([
                'organization_id' => $revision->organization_id,
                'revision_id' => $revision->id,
                'request_id' => $request->id,
                'link_id' => $link?->id,
                'manifest_hash' => (string) $revision->manifest_hash,
                'party' => $request->party->value,
                'method' => $method->value,
                'submitted_via' => $link !== null ? ContractSignatureEvidence::VIA_LINK : ContractSignatureEvidence::VIA_INTERNAL,
                'signer_name' => trim((string) $data['signer_name']),
                'signer_function' => filled($data['signer_function'] ?? null) ? trim((string) $data['signer_function']) : null,
                'declaration_text' => $revision->declaration_text,
                'declaration_accepted' => true,
                'authority_confirmed' => true,
                'signed_at' => now(),
                'recorded_by_user_id' => $actor?->id,
                'review_status' => null,
                'ip' => $http?->ip(),
                'user_agent' => $http !== null ? substr((string) $http->userAgent(), 0, 255) : null,
            ], $file ?? []));

            $request->forceFill(['status' => SignatureRequestStatus::Signed->value, 'fulfilled_at' => now()])->save();
            $contract->audit('contract.signing.signed', [
                'revision_id' => $revision->id,
                'evidence_id' => $evidence->id,
                'party' => $request->party->value,
                'method' => $method->value,
                'via' => $evidence->submitted_via,
                'by' => $actor?->id,
            ]);
            $this->settleRevision($revision, $actor);

            return $evidence;
        });
    }

    /** @param array{signer_name: string, stated_signed_on?: ?string} $data */
    private function recordUpload(ContractSignatureRequest $target, ?ContractSignatureLink $link, ?User $actor, UploadedFile $file, array $data, ?Request $http): ContractSignatureEvidence {
        $bytes = (string) $file->get();
        $this->assertReadablePdf($file, $bytes);

        return DB::transaction(function () use ($target, $link, $actor, $file, $bytes, $data, $http): ContractSignatureEvidence {
            [$request, $revision, $contract] = $this->lockRequest($target);
            $this->assertAcceptsSubmission($request, $revision);
            if ($link !== null) {
                $this->consumeLink($link);
            }

            $stored = $this->storeEvidenceFile($bytes, 'pdf');
            $evidence = ContractSignatureEvidence::query()->create(array_merge($stored, [
                'organization_id' => $revision->organization_id,
                'revision_id' => $revision->id,
                'request_id' => $request->id,
                'link_id' => $link?->id,
                'manifest_hash' => (string) $revision->manifest_hash,
                'party' => $request->party->value,
                'method' => SignatureMethod::Upload->value,
                'submitted_via' => $link !== null ? ContractSignatureEvidence::VIA_LINK : ContractSignatureEvidence::VIA_INTERNAL,
                'signer_name' => trim((string) $data['signer_name']),
                'declaration_text' => $revision->declaration_text,
                'declaration_accepted' => false,
                'authority_confirmed' => false,
                'signed_at' => now(),
                'stated_signed_on' => $data['stated_signed_on'] ?? null,
                'original_name' => File::sanitizeDisplayName($file->getClientOriginalName()),
                'recorded_by_user_id' => $actor?->id,
                'review_status' => EvidenceReviewStatus::Pending->value,
                'ip' => $http?->ip(),
                'user_agent' => $http !== null ? substr((string) $http->userAgent(), 0, 255) : null,
            ]));

            $request->forceFill(['status' => SignatureRequestStatus::EvidenceReceived->value])->save();
            $contract->audit($link !== null ? 'contract.signing.evidenceReceived' : 'contract.signing.evidenceRecorded', [
                'revision_id' => $revision->id,
                'evidence_id' => $evidence->id,
                'party' => $request->party->value,
                'by' => $actor?->id,
            ]);

            $this->notifier->notify(NotificationEvent::ContractSignatureReceived, $contract, $contract->responsible, [
                'title' => (string) __('contract-signing.notification.evidence_received.title', ['number' => $contract->number]),
                'title_key' => 'contract-signing.notification.evidence_received.title',
                'title_params' => ['number' => $contract->number],
                'message' => (string) __('contract-signing.notification.evidence_received.message', ['party' => $request->party->label(), 'title' => $contract->title]),
                'message_key' => 'contract-signing.notification.evidence_received.message',
                'message_params' => ['party' => ['key' => 'contract-signing.party.' . $request->party->value], 'title' => $contract->title],
                'url' => route('contracts.show', $contract),
            ], dedup: false);

            return $evidence;
        });
    }

    /**
     * Fassung nach einer erfüllten Anforderung nachführen: teilweise oder
     * vollständig unterzeichnet. Der Abschluss läuft in der Transaktion des
     * Aufrufers — nie ein halber Abschluss.
     */
    private function settleRevision(ContractSigningRevision $revision, ?User $actor): void {
        $requests = $revision->requests()->get();
        $required = $requests->where('required', true);
        $allRequiredDone = $required->every(fn (ContractSignatureRequest $r): bool => $r->status->isFulfilled());
        $anyDone = $requests->contains(fn (ContractSignatureRequest $r): bool => $r->status === SignatureRequestStatus::Signed);

        if ($allRequiredDone) {
            $this->assertStatusTransition($revision->status, SigningRevisionStatus::Signed);
            $this->assertManifestIntact($revision);
            $this->expireOpenLinks($revision, $actor);
            $revision->forceFill(['status' => SigningRevisionStatus::Signed->value, 'completed_at' => now()])->save();
            $contract = $revision->contract()->firstOrFail();
            $contract->audit('contract.signing.completed', ['revision_id' => $revision->id, 'manifest_hash' => $revision->manifest_hash, 'by' => $actor?->id]);
            $this->scheduleReview($revision, $contract);

            return;
        }

        if ($anyDone && $revision->status === SigningRevisionStatus::Ready) {
            $revision->forceFill(['status' => SigningRevisionStatus::PartiallySigned->value])->save();
        }
    }

    /** Review-Wiedervorlage über die Vertragsobligationen (Feature 079). */
    private function scheduleReview(ContractSigningRevision $revision, Contract $contract): void {
        if ($revision->review_on === null) {
            return;
        }
        $exists = $contract->obligations()
            ->where('kind', ContractObligationKind::Review->value)
            ->where('status', 'open')
            ->whereBetween('due_on', DateRange::days($revision->review_on, $revision->review_on))
            ->exists();
        if ($exists) {
            return;
        }
        DocumentLocale::within(null, $contract->organization, fn () => app(ContractService::class)->addObligation($contract, [
            'kind' => ContractObligationKind::Review->value,
            'title' => (string) __('contract-signing.obligation.review_title', ['no' => $revision->revision_no]),
            'due_on' => $revision->review_on->toDateString(),
            'warn_days_before' => 30,
            'responsible_user_id' => $contract->responsible_user_id,
        ]));
    }

    /** @return array{0: ContractSignatureRequest, 1: ContractSigningRevision, 2: Contract} */
    private function lockRequest(ContractSignatureRequest $target): array {
        /** @var ContractSignatureRequest $request */
        $request = ContractSignatureRequest::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
        /** @var ContractSigningRevision $revision */
        $revision = ContractSigningRevision::query()->whereKey($request->revision_id)->lockForUpdate()->firstOrFail();
        $contract = $revision->contract()->firstOrFail();

        return [$request, $revision, $contract];
    }

    private function assertAcceptsSubmission(ContractSignatureRequest $request, ContractSigningRevision $revision): void {
        if (! $revision->status->acceptsSignatures()) {
            throw new RuntimeException((string) __('contract-signing.error.revision_not_ready'));
        }
        if (! $request->status->acceptsSubmission()) {
            throw new RuntimeException((string) __('contract-signing.error.request_fulfilled'));
        }
    }

    private function assertManifestIntact(ContractSigningRevision $revision): void {
        if ($revision->manifest_hash === null || $revision->manifest_hash !== $this->manifestHash($revision)) {
            throw new RuntimeException((string) __('contract-signing.error.manifest_changed', ['files' => '#']));
        }
        $mismatches = $this->verifyManifest($revision);
        if ($mismatches !== []) {
            throw new RuntimeException((string) __('contract-signing.error.manifest_changed', ['files' => implode(', ', $mismatches)]));
        }
    }

    private function consumeLink(ContractSignatureLink $link): void {
        /** @var ContractSignatureLink $fresh */
        $fresh = ContractSignatureLink::query()->withoutGlobalScopes()->whereKey($link->id)->lockForUpdate()->firstOrFail();
        if (! $fresh->isUsable()) {
            throw new RuntimeException((string) __('contract-signing.error.link_unusable'));
        }
        $fresh->forceFill(['used_at' => now()])->save();
        $link->used_at = $fresh->used_at;
    }

    private function expireOpenLinks(ContractSigningRevision $revision, ?User $actor): void {
        foreach ($revision->links()->where('purpose', SignatureLinkPurpose::Sign->value)->get() as $link) {
            if ($link->isUsable()) {
                $link->forceFill(['revoked_at' => now(), 'revoked_by' => $actor?->id])->save();
            }
        }
    }

    /** @return array{token: string, link: ContractSignatureLink} */
    private function createLink(ContractSigningRevision $revision, ?ContractSignatureRequest $request, SignatureLinkPurpose $purpose, User $actor, int $ttlDays): array {
        $token = Str::random(48);
        $link = ContractSignatureLink::query()->create([
            'organization_id' => $revision->organization_id,
            'revision_id' => $revision->id,
            'request_id' => $request?->id,
            'purpose' => $purpose->value,
            'token_hash' => CryptoHelper::hash($token),
            'expires_at' => Carbon::now()->addDays(max(1, $ttlDays)),
            'created_by' => $actor->id,
        ]);

        return ['token' => $token, 'link' => $link];
    }

    private function deliver(ContractSignatureLink $link, string $token, string $email, User $actor): ContractSignatureLink {
        $revision = $link->revision()->with('contract.customer', 'contract.organization')->firstOrFail();
        $contract = $revision->contract;
        $url = $link->purpose === SignatureLinkPurpose::Sign
            ? route('agreements.public-sign', ['token' => $token])
            : route('agreements.public-download', ['token' => $token]);

        try {
            DocumentLocale::within($contract?->customer, $contract?->organization, fn () => Mail::to($email)->send(new AgreementLinkMail($revision, $link->purpose, $url, $link->expires_at)));
            $link->forceFill(['sent_at' => now(), 'sent_to' => $email, 'send_error' => null])->save();
            $contract?->audit('contract.signing.linkSent', ['revision_id' => $revision->id, 'link_id' => $link->id, 'purpose' => $link->purpose->value, 'to' => $email, 'by' => $actor->id]);
        } catch (\Throwable $e) {
            Log::warning('contract_signing.link_send_failed', ['link_id' => $link->id, 'error' => $e->getMessage()]);
            $link->forceFill(['sent_to' => $email, 'send_error' => Str::limit(get_class($e), 500, '')])->save();
            $contract?->audit('contract.signing.linkSendFailed', ['revision_id' => $revision->id, 'link_id' => $link->id, 'to' => $email, 'by' => $actor->id]);
            throw new RuntimeException((string) __('contract-signing.error.send_failed'), 0, $e);
        }

        return $link;
    }

    /** @param array<string, mixed> $data */
    private function syncManifest(ContractSigningRevision $revision, Contract $contract, array $data): void {
        $ids = array_values(array_unique(array_merge(
            [(int) $data['contract_version_id']],
            array_map('intval', (array) ($data['attachment_version_ids'] ?? [])),
        )));
        $versions = DocumentVersion::query()
            ->whereIn('id', $ids)
            ->whereHas('document', fn ($q) => $q
                ->where('organization_id', $contract->organization_id)
                ->where('documentable_type', $contract->getMorphClass())
                ->where('documentable_id', $contract->getKey()))
            ->get()
            ->keyBy('id');
        if ($versions->count() !== count($ids)) {
            throw new RuntimeException((string) __('contract-signing.error.version_not_on_contract'));
        }

        $sort = 0;
        foreach ($ids as $id) {
            /** @var DocumentVersion $version */
            $version = $versions[$id];
            if (! Storage::disk($version->disk)->exists($version->path)) {
                throw new RuntimeException((string) __('contract-signing.error.file_missing'));
            }
            $bytes = $this->readVersion($version);
            ContractSigningManifestItem::query()->create([
                'organization_id' => $revision->organization_id,
                'revision_id' => $revision->id,
                'document_version_id' => $version->id,
                'role' => $sort === 0 ? ContractSigningManifestItem::ROLE_CONTRACT : ContractSigningManifestItem::ROLE_ATTACHMENT,
                'sort' => $sort++,
                'original_name' => $version->original_name,
                'sha256' => (string) CryptoHelper::hash($bytes),
                'size' => strlen($bytes),
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function syncRequests(ContractSigningRevision $revision, array $data): void {
        $customer = (array) ($data['customer'] ?? []);
        $organization = (array) ($data['organization'] ?? []);
        $organizationRequired = (bool) ($organization['required'] ?? true);
        $waiver = trim((string) ($organization['waiver_reason'] ?? ''));
        if (! $organizationRequired && $waiver === '') {
            throw new RuntimeException((string) __('contract-signing.error.waiver_needs_reason'));
        }

        ContractSignatureRequest::query()->create([
            'organization_id' => $revision->organization_id,
            'revision_id' => $revision->id,
            'party' => SignatureParty::Customer->value,
            'required' => true,
            'signer_name' => trim((string) $customer['signer_name']),
            'signer_function' => filled($customer['signer_function'] ?? null) ? trim((string) $customer['signer_function']) : null,
            'signer_email' => filled($customer['signer_email'] ?? null) ? trim((string) $customer['signer_email']) : null,
            'status' => SignatureRequestStatus::Pending->value,
        ]);
        ContractSignatureRequest::query()->create([
            'organization_id' => $revision->organization_id,
            'revision_id' => $revision->id,
            'party' => SignatureParty::Organization->value,
            'required' => $organizationRequired,
            'signer_name' => trim((string) $organization['signer_name']),
            'signer_function' => filled($organization['signer_function'] ?? null) ? trim((string) $organization['signer_function']) : null,
            'signer_email' => filled($organization['signer_email'] ?? null) ? trim((string) $organization['signer_email']) : null,
            'status' => ($organizationRequired ? SignatureRequestStatus::Pending : SignatureRequestStatus::Waived)->value,
            'waiver_reason' => $organizationRequired ? null : $waiver,
            'fulfilled_at' => $organizationRequired ? null : now(),
        ]);
    }

    /**
     * Kanonischer Hash der eingefrorenen Fassung: Dateien, Parteien, Erklärung.
     * Ein Hash allein verhindert keine Änderung der Bytes — die Prüfung gegen
     * den Speicher läuft bei Auslieferung und Abschluss zusätzlich.
     */
    private function manifestHash(ContractSigningRevision $revision): string {
        $lines = [
            'revision|' . $revision->revision_no,
            'controller|' . ($revision->controller_party->value ?? ''),
            'declaration|' . $revision->declaration_text,
        ];
        foreach ($revision->requests()->orderBy('party')->get() as $request) {
            $lines[] = implode('|', ['party', $request->party->value, $request->required ? '1' : '0', $request->signer_name, (string) $request->signer_function]);
        }
        foreach ($revision->manifestItems()->orderBy('sort')->get() as $item) {
            $lines[] = implode('|', ['file', $item->role, (string) $item->document_version_id, $item->sha256, $item->original_name, (string) $item->size]);
        }

        return (string) CryptoHelper::hash(implode("\n", $lines));
    }

    /** @return array{disk: string, path: string, mime: string, size: int, file_hash: string} */
    private function storeEvidenceFile(string $bytes, string $ext): array {
        $path = 'contracts/signatures/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.' . $ext;
        Storage::disk(self::DISK)->put($path, $bytes);

        return [
            'disk' => self::DISK,
            'path' => $path,
            'mime' => $ext === 'pdf' ? 'application/pdf' : 'image/png',
            'size' => strlen($bytes),
            'file_hash' => (string) CryptoHelper::hash($bytes),
        ];
    }

    /**
     * Nur tatsächlich lesbare PDFs — defekte oder verschlüsselte mit
     * verständlichem Fehler. Der Upload liegt ohne Endung im Temp-Ordner,
     * deshalb die Kopfprüfung auf den Bytes statt
     * {@see \PDFToolkit\Helper\PDFHelper::isValidPdf()} (die verlangt eine
     * .pdf-Endung); pdfinfo ist nicht auf jedem Host da.
     */
    private function assertReadablePdf(UploadedFile $file, string $bytes): void {
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
        if ($ext !== 'pdf' || $file->getMimeType() !== 'application/pdf') {
            throw new RuntimeException((string) __('contract-signing.error.pdf_required'));
        }
        if ($bytes === '' || ! str_contains(substr($bytes, 0, 1024), '%PDF-') || ! str_contains($bytes, '%%EOF')) {
            throw new RuntimeException((string) __('contract-signing.error.pdf_unreadable'));
        }
        if (preg_match('~/Encrypt\s*\d+\s+\d+\s+R~', $bytes) === 1 || preg_match('~/Encrypt\s*<<~', $bytes) === 1) {
            throw new RuntimeException((string) __('contract-signing.error.pdf_encrypted'));
        }
    }

    private function assertSigningContract(Contract $contract): void {
        if (! $contract->kind->requiresSigning()) {
            throw new RuntimeException((string) __('contract-signing.error.kind_not_signing'));
        }
        if ($contract->customer_id === null) {
            throw new RuntimeException((string) __('contract-signing.error.customer_required'));
        }
        if (! $contract->status->isOpen()) {
            throw new RuntimeException((string) __('contract-signing.error.contract_closed'));
        }
    }
}
