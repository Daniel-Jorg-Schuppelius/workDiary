<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineIncomingInvoiceTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Services;

use APIToolkit\Exceptions\ConflictException;
use App\Enums\Billing\DocumentDirection;
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\Organization;
use App\Plugins\DatevOnline\Api\DatevOnlineClientFactory;
use App\Plugins\DatevOnline\DatevOnlinePlugin;
use App\Plugins\DatevOnline\Enums\DatevTransferKind;
use App\Plugins\DatevOnline\Models\DatevOnlineConnection;
use App\Plugins\Support\{PluginSettingsResolver, PluginTenantGate};
use App\Services\Invoicing\Contracts\IncomingInvoiceTransferTarget;
use App\Services\Invoicing\Dto\IncomingInvoiceTransferResult;
use Datev\API\Online\Endpoints\AccountingDocuments\DocumentsEndpoint;
use Datev\API\Online\OnlineService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Rechnungseingang → DATEV Unternehmen online (Feature 163, MVP-1111):
 * das Original als Belegbild, Eingangsbelege als „Rechnungseingang“,
 * Ausgangsbelege als „Rechnungsausgang“, ab `documents_since`.
 *
 * Hochgeladen wird per PUT mit einer vorab im Journal vermerkten GUID: DATEV
 * weist eine schon vorhandene ID mit 409 ab, eine Wiederholung nach
 * abgerissener Antwort legt also keinen zweiten Beleg an.
 */
final class DatevOnlineIncomingInvoiceTarget implements IncomingInvoiceTransferTarget {
    public function key(): string {
        return DatevOnlinePlugin::ID;
    }

    public function label(): string {
        return (string) __('datev-online::datev.incoming.label');
    }

    public function isEnabled(Organization $organization): bool {
        $connection = self::connection((int) $organization->id);

        return $connection !== null && $connection->is_documents_enabled
            && ! PluginTenantGate::blocks((int) $organization->id)
            && PluginSettingsResolver::for(DatevOnlinePlugin::ID, (int) $organization->id)->enabled();
    }

    public function appliesTo(IncomingEInvoice $incoming): bool {
        $connection = self::connection((int) $incoming->organization_id);
        if ($connection === null) {
            return false;
        }
        $since = ($connection->documents_since ?? $connection->connected_at)?->copy()->startOfDay();

        return $since === null || $incoming->received_at->greaterThanOrEqualTo($since);
    }

    public function transfer(IncomingEInvoice $incoming, IncomingEInvoiceTransfer $journal): IncomingInvoiceTransferResult {
        $connection = self::connection((int) $incoming->organization_id);
        if ($connection === null) {
            return IncomingInvoiceTransferResult::waiting((string) __('datev-online::datev.error.not_ready'));
        }
        $version = $incoming->document?->currentVersion;
        $content = $version !== null ? Storage::disk($version->disk)->get($version->path) : null;
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Belegdatei fehlt.');
        }

        if ($journal->external_id === null) {
            $journal->forceFill(['external_id' => (string) Str::uuid()])->save();
        }
        $kind = $incoming->direction === DocumentDirection::Outgoing ? DatevTransferKind::OutgoingDocument : DatevTransferKind::IncomingDocument;
        $documents = new DocumentsEndpoint(app(DatevOnlineClientFactory::class)->for($connection, OnlineService::AccountingDocuments), (string) $connection->datev_client_number);
        try {
            $documents->uploadWithId((string) $journal->external_id, $content, (string) ($version->original_name ?: basename($version->path)), [
                'document_type' => $kind->documentType(),
                'note' => mb_substr((string) ($incoming->document->title ?? $incoming->invoice_number ?? ''), 0, 255),
            ]);
        } catch (ConflictException) {
            // Die ID liegt schon in DATEV: Der vorige Versuch ist angekommen, nur die Antwort nicht.
        }

        return IncomingInvoiceTransferResult::transferred((string) $journal->external_id, $incoming->invoice_number);
    }

    private static function connection(int $organizationId): ?DatevOnlineConnection {
        $connection = DatevOnlineConnection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();

        return $connection instanceof DatevOnlineConnection && $connection->isReady() ? $connection : null;
    }
}
