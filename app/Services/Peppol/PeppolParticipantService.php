<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolParticipantService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Peppol;

use App\Models\{Customer, PeppolParticipantLookup};
use App\Plugins\PeppolAccessPoint\PeppolAccessPointConfig;
use ERechnungToolkit\Contracts\{DnsNaptrResolverInterface, SmpHttpClientInterface};
use ERechnungToolkit\Peppol\{DocumentTypeId, ParticipantId, SmpLookup};
use RuntimeException;

/**
 * Peppol-Teilnehmerauflösung (Feature 066, MVP-734).
 *
 * Beantwortet die beiden Fragen, die vor jedem Versand geklärt sein müssen:
 * Ist der Empfänger in Peppol registriert, und nimmt er das Dokumentformat an?
 * Die Auflösung kostet eine DNS- (SML/NAPTR) und eine HTTP-Runde (SMP) — das
 * Ergebnis wird deshalb in {@see PeppolParticipantLookup} mit Ablaufzeit
 * festgehalten, statt bei jedem Versand neu geholt zu werden.
 *
 * **Nur eindeutige Auskünfte werden gespeichert.** Ein Netzfehler ist keine
 * Aussage über die Registrierung; er wirft und landet nie im Zwischenspeicher
 * (sonst gälte eine Störung 24 Stunden lang als „nicht registriert").
 */
class PeppolParticipantService {
    public function __construct(
        private readonly SmpHttpClientInterface $httpClient,
        private readonly DnsNaptrResolverInterface $dnsResolver,
    ) {}

    /**
     * Teilnehmerkennung aus der Eingabe. Akzeptiert die kanonische Form
     * (`schema::ICD:Kennung`) ebenso wie den blossen Wert (`9930:DE123456789`),
     * der dann mit dem Peppol-Standardschema ergänzt wird.
     */
    public static function parse(?string $raw, ?string $scheme = null): ?ParticipantId {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, ParticipantId::SEPARATOR)) {
            return ParticipantId::tryParse($raw);
        }

        $scheme = trim((string) $scheme);

        return ParticipantId::tryParse(($scheme !== '' ? $scheme : ParticipantId::DEFAULT_SCHEME) . ParticipantId::SEPARATOR . $raw);
    }

    /** Teilnehmerkennung des Kunden (null = nicht gepflegt oder ungültig). */
    public static function forCustomer(Customer $customer): ?ParticipantId {
        return self::parse($customer->peppol_participant_id, $customer->peppol_scheme);
    }

    /**
     * Aufgelöster Registrierungsstand. Ein gültiger Zwischenspeicher-Eintrag
     * wird zurückgegeben, ohne DNS oder SMP zu berühren.
     *
     * @throws RuntimeException wenn SML/SMP nicht antworten (keine Aussage, kein Eintrag).
     */
    public function lookup(int $organizationId, ParticipantId $participant, bool $refresh = false): PeppolParticipantLookup {
        $config = PeppolAccessPointConfig::resolve($organizationId);
        $canonical = $participant->canonical();

        $existing = PeppolParticipantLookup::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('participant', $canonical)
            ->first();

        if ($existing instanceof PeppolParticipantLookup && ! $refresh && ! $existing->isStale($config['lookup_ttl_hours'])) {
            return $existing;
        }

        $lookup = new SmpLookup($this->httpClient, $this->dnsResolver);

        // Wirft bei DNS-/SMP-Störungen (RuntimeException) — bewusst nicht
        // abgefangen: eine Störung ist keine Registrierungsauskunft.
        $smpBaseUrl = $lookup->resolveSmpBaseUrl($participant, $config['sml_zone']);

        if ($smpBaseUrl === null || $smpBaseUrl === '') {
            // Kein SML-Eintrag heißt: der Teilnehmer ist nicht in Peppol.
            return $this->store($organizationId, $canonical, false, null, [], (string) __('peppol.status.not_registered'));
        }

        $group = $lookup->fetchServiceGroup($participant, $smpBaseUrl);
        $types = array_map(static fn (DocumentTypeId $t): string => $t->canonical(), $group->getDocumentTypeIds());

        return $this->store($organizationId, $canonical, true, $smpBaseUrl, $types, null);
    }

    /**
     * Nimmt der Teilnehmer das Dokument an?
     *
     * Neben der exakten Kennung zählt die Customization von Peppol BIS
     * Billing 3.0: die XRechnung ist eine CIUS davon, und Empfänger
     * registrieren im SMP regelmäßig nur die BIS-Kennung. Eine exakte
     * Gleichheit zu verlangen würde reale, empfangsbereite Adressaten
     * fälschlich ausschließen.
     */
    public function accepts(PeppolParticipantLookup $lookup, DocumentTypeId $documentTypeId): bool {
        foreach ($lookup->document_types ?? [] as $canonical) {
            $candidate = DocumentTypeId::parse((string) $canonical);
            if ($candidate->equals($documentTypeId)) {
                return true;
            }
            if ($candidate->getRootNamespace() === $documentTypeId->getRootNamespace()
                && $candidate->getLocalName() === $documentTypeId->getLocalName()
                && $candidate->getCustomizationId() === DocumentTypeId::BIS_BILLING_3) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $documentTypes
     */
    private function store(int $organizationId, string $participant, bool $registered, ?string $smpBaseUrl, array $documentTypes, ?string $message): PeppolParticipantLookup {
        /** @var PeppolParticipantLookup $row */
        $row = PeppolParticipantLookup::query()->withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => $organizationId, 'participant' => $participant],
            [
                'registered' => $registered,
                'smp_base_url' => $smpBaseUrl,
                'document_types' => $documentTypes,
                'message' => $message,
                'checked_at' => now(),
            ],
        );

        return $row;
    }
}
