<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DsarPortalIntakeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Privacy\DataSubjectRequestType;
use App\Mail\Privacy\DsarReceiptMail;
use App\Models\Privacy\{DataSubjectRequest, DsarPortal, PrivacyAttachment};
use App\Support\Filename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Log, Mail, URL};
use Throwable;

/**
 * Eingang aus dem oeffentlichen Betroffenenportal (G11, MVP-728).
 *
 * Bewusst **actorlos**: es gibt keinen angemeldeten Nutzer und es wird auch
 * keiner fingiert — `created_by` bleibt leer, die Hash-Kette schreibt den
 * Eingang als System-Ereignis. Die Frist laeuft ab Eingang (Art. 12 Abs. 3
 * DSGVO); die per Link bestaetigte Rueckadresse ist ein Baustein der spaeteren
 * Identitaetspruefung, KEIN Fristauslöser — sonst koennte eine nicht
 * zugestellte Mail die gesetzliche Frist faktisch aussetzen.
 */
class DsarPortalIntakeService {
    /** Gueltigkeitsdauer des Adress-Bestaetigungslinks. */
    public const CONFIRM_TTL_DAYS = 14;

    public function __construct(
        private readonly DataSubjectRequestService $requests,
        private readonly PrivacyEventService $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validierte Formulardaten
     * @param  list<UploadedFile>  $files
     */
    public function submit(DsarPortal $portal, array $data, array $files = []): DataSubjectRequest {
        $organization = $portal->organization;
        abort_if($organization === null, 404);

        $email = trim((string) ($data['email'] ?? ''));
        $files = $portal->allow_attachments ? $files : [];

        $dsr = DB::transaction(function () use ($organization, $data, $email, $files): DataSubjectRequest {
            $dsr = $this->requests->open(
                $organization,
                DataSubjectRequestType::from((string) $data['type']),
                $this->subjectBlock($data, $email),
                (string) $data['message'],
                DataSubjectRequest::CHANNEL_PORTAL,
                null,
                $email,
            );

            foreach ($files as $file) {
                $this->storeAttachment($dsr, $file);
            }

            // Metadaten bewusst PII-frei (nur Zaehler/Flags).
            $this->events->record($dsr, 'portal_submitted', null, [
                'attachments' => count($files),
                'has_reference' => trim((string) ($data['reference'] ?? '')) !== '' ? 1 : 0,
            ]);

            return $dsr;
        });

        $this->sendReceipt($dsr, $organization->name ?? '', $email);

        return $dsr;
    }

    /** Signierter, befristeter Link zur Bestaetigung der Rueckadresse. */
    public function confirmationUrl(DataSubjectRequest $dsr): string {
        return URL::temporarySignedRoute(
            'dsar.confirm',
            now()->addDays(self::CONFIRM_TTL_DAYS),
            ['dsr' => $dsr->sqid],
        );
    }

    /**
     * Identitaetsangaben als ein Block — sie liegen zusammen mit dem Anliegen
     * unter dem Fall-DEK und nehmen so am Crypto-Shredding teil.
     *
     * @param  array<string, mixed>  $data
     */
    private function subjectBlock(array $data, string $email): string {
        $lines = [(string) $data['full_name'], __('dsar.subject.email', ['value' => $email])];
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference !== '') {
            $lines[] = __('dsar.subject.reference', ['value' => $reference]);
        }

        return implode("\n", $lines);
    }

    private function storeAttachment(DataSubjectRequest $dsr, UploadedFile $file): void {
        $stored = $file->store('privacy/attachments', 'local');
        if ($stored === false) {
            return;
        }

        PrivacyAttachment::create([
            'organization_id' => $dsr->organization_id,
            'attachable_type' => $dsr->getMorphClass(),
            'attachable_id' => $dsr->getKey(),
            'filename' => Filename::sanitize($file->getClientOriginalName()),
            'path' => $stored,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'uploaded_by' => null,
        ]);
    }

    /**
     * Eingangsbestaetigung an die angegebene Adresse. Ein Mailfehler darf den
     * bereits angelegten Fall NIE zurueckrollen — die Frist laeuft trotzdem.
     */
    private function sendReceipt(DataSubjectRequest $dsr, string $organizationName, string $email): void {
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->queue(new DsarReceiptMail(
                requestNumber: (string) $dsr->request_number,
                organizationName: $organizationName,
                deadlineDate: $dsr->deadline_at?->format('d.m.Y') ?? '',
                confirmUrl: $this->confirmationUrl($dsr),
            ));
            $this->events->record($dsr, 'portal_receipt_sent');
        } catch (Throwable $e) {
            Log::warning('DSAR-Eingangsbestätigung konnte nicht zugestellt werden.', [
                'request_id' => $dsr->id,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
            $this->events->record($dsr, 'portal_receipt_failed');
        }
    }
}
