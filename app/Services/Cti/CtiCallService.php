<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiCallService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Notification\NotificationEvent;
use App\Models\{CtiConnection, Customer, ExternalReference, Organization, User};
use App\Notifications\GenericEventNotification;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Contacts\PhoneNumberMatcher;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, PhoneNumberHelper};
use Illuminate\Support\Carbon;

/**
 * Protokolliert ein normalisiertes Anruf-Ereignis als Kommunikationseintrag
 * (Feature 056, MVP-118). **Nur Metadaten**, nie Gesprächsinhalte (DoD/
 * Datenschutz). Nummer→Kunde über {@see PhoneNumberHelper::toE164()} (Toolkit);
 * nur bekannte Kunden werden protokolliert. **Idempotent** über
 * {@see ExternalReference} (Plugin `cti`, Typ `call`, Call-ID) — ein erneut
 * zugestelltes Ereignis erzeugt keinen zweiten Eintrag.
 */
class CtiCallService {
    public const PLUGIN_ID = 'cti';

    public const EXTERNAL_TYPE = 'call';

    public function __construct(private readonly CommunicationNoteService $notes) {}

    /**
     * @return 'recorded'|'skipped'|'unmatched'
     */
    public function record(CtiConnection $connection, CtiCall $call): string {
        $exists = ExternalReference::query()
            ->forPlugin($connection->organization_id, self::PLUGIN_ID, self::EXTERNAL_TYPE)
            ->forExternalId($call->callId)
            ->exists();
        if ($exists) {
            return 'skipped';
        }

        $number = $call->counterpartyNumber();
        $customer = $this->matchCustomer((int) $connection->organization_id, $number);

        // Anrufer-Pop-up an den opted-in Mitarbeiter der Durchwahl (auch bei unbekanntem Anrufer):
        // vor der unmatched-Rückkehr, aber hinter dem Idempotenz-Skip (kein Doppel bei Replay).
        $this->notifyCalleeOptIn($connection, $call, $number, $customer instanceof Customer ? $customer : null);

        if (! $customer instanceof Customer) {
            return 'unmatched'; // nur bekannte Kunden protokollieren
        }

        $actor = $this->systemActor((int) $connection->organization_id);
        if (! $actor instanceof User) {
            return 'unmatched';
        }

        $note = $this->notes->create($customer, $actor, [
            'type' => CommunicationNoteType::Call->value,
            'direction' => $call->direction === CtiCall::OUTBOUND
                ? CommunicationDirection::Outbound->value
                : CommunicationDirection::Inbound->value,
            'subject' => $this->subject($call, $number),
            'body' => '', // bewusst keine Inhalte
            'occurred_at' => $call->occurredAt->toIso8601String(),
        ]);

        ExternalReference::query()->withoutGlobalScopes()->create([
            'organization_id' => $connection->organization_id,
            'plugin_id' => self::PLUGIN_ID,
            'external_type' => self::EXTERNAL_TYPE,
            'referenceable_type' => $note->getMorphClass(),
            'referenceable_id' => $note->getKey(),
            'external_id' => $call->callId,
            'payload' => [
                'direction' => $call->direction,
                'number' => $number,
                'duration_seconds' => $call->durationSeconds,
            ],
            'synced_at' => Carbon::now(),
        ]);

        $connection->forceFill(['last_event_at' => Carbon::now()])->save();

        return 'recorded';
    }

    private function subject(CtiCall $call, string $number): string {
        $key = $call->direction === CtiCall::OUTBOUND ? 'cti.note.subject_outbound' : 'cti.note.subject_inbound';

        return (string) __($key, ['number' => $number !== '' ? $number : '—']);
    }

    /**
     * Stammdaten-Treffer zur Anrufernummer. Der Abgleich läuft seit W2.4 über
     * den gemeinsamen {@see PhoneNumberMatcher} (vorher Kopie des
     * Fritzbox-Algorithmus, inkl. rohem LIKE statt whereLikeEscaped).
     */
    private function matchCustomer(int $organizationId, string $number): ?Customer {
        $e164 = $number !== '' ? PhoneNumberHelper::toE164($number, 'DE') : null;
        if ($e164 === null) {
            return null;
        }

        $match = app(PhoneNumberMatcher::class)->match($organizationId, $e164, [Customer::class]);

        return $match instanceof Customer ? $match : null;
    }

    /**
     * Anrufer-Pop-up (MVP-118, Rang 9): löst die angerufene Durchwahl auf den
     * Mitarbeiter mit passendem Opt-in-Hash (gleiche Organisation!) auf und
     * schickt ihm eine In-App-Benachrichtigung „Anruf von …". Nur eingehende
     * Anrufe; ohne hinterlegte Durchwahl passiert nichts (Datenschutz).
     */
    private function notifyCalleeOptIn(CtiConnection $connection, CtiCall $call, string $callerNumber, ?Customer $customer): void {
        if ($call->direction !== CtiCall::INBOUND) {
            return;
        }

        $dialled = $call->toNumber !== '' ? PhoneNumberHelper::toE164($call->toNumber, 'DE') : null;
        if ($dialled === null) {
            return;
        }

        $callee = User::query()
            ->where('organization_id', $connection->organization_id)
            ->where('cti_extension_hash', CryptoHelper::hash($dialled, HashAlgorithm::SHA256))
            ->first();
        if (! $callee instanceof User) {
            return; // kein Opt-in → kein Pop-up
        }

        $callerLabel = $callerNumber !== '' ? $callerNumber : (string) __('cti.popup.unknown_number');
        $title = $customer instanceof Customer
            ? (string) __('cti.popup.title_customer', ['name' => $this->customerLabel($customer)])
            : (string) __('cti.popup.title_unknown', ['number' => $callerLabel]);

        $callee->notify(new GenericEventNotification(
            NotificationEvent::CtiIncomingCall,
            [
                'title' => $title,
                'message' => (string) __('cti.popup.message', ['number' => $callerLabel]),
                'url' => $customer instanceof Customer ? route('customers.show', $customer) : null,
            ],
            ['database'],
        ));
    }

    private function customerLabel(Customer $customer): string {
        $name = trim((string) $customer->name);
        if ($name !== '') {
            return $name;
        }

        $company = trim((string) $customer->company);

        return $company !== '' ? $company : (string) __('cti.popup.unknown_number');
    }

    private function systemActor(int $organizationId): ?User {
        $organization = Organization::query()->find($organizationId);
        if (! $organization instanceof Organization) {
            return null;
        }

        return $organization->owner
            ?? User::query()->where('organization_id', $organizationId)->orderBy('id')->first();
    }
}
