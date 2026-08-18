<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeRouter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeItemStatus, CloudIntakeRouteTarget};
use App\Models\{Asset, Customer, DiaryEntry, Document, IntegrationInboxItem, Project, User};
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Models\Contract\Contract;
use App\Plugins\Support\Intake\IntakeItem;
use App\Services\Document\DocumentService;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Ziel-Übergabe des Cloud-Dokumenteingangs (Feature 080, MVP-357) — KEINE
 * zweite Geschäftslogik:
 *
 *  - `incoming_invoice` → bestehende {@see IncomingEInvoiceService}-Pipeline
 *    (inhaltsbasierter Dedup deckt Mail/Upload/Cloud kanalübergreifend);
 *  - `document` → {@see DocumentService} (Neuanlage bzw. Versionsvorschlag);
 *  - Pfadvariablen lösen NUR vorhandene Objekte auf (org-gescopt über die
 *    globalen Scopes) — fehlend/mehrdeutig ⇒ {@see IntegrationInboxItem}.
 *
 * Neue Revisionen bereits importierter Dateien werden standardmäßig als
 * Versionsvorschlag in die Inbox gelegt; nur Routen mit `auto_version`
 * legen automatisch eine neue DMS-Version an (Konzept §Änderungen).
 */
class CloudIntakeRouter {
    public function __construct(
        private readonly DocumentService $documents,
        private readonly IncomingEInvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, string>  $variables
     * @return array{status: CloudIntakeItemStatus, imported: Model|null, reason: string|null}
     */
    public function route(
        CloudDocumentConnection $connection,
        CloudDocumentRoute $route,
        array $variables,
        IntakeItem $item,
        string $quarantinePath,
        User $actor,
    ): array {
        return match ($route->target) {
            CloudIntakeRouteTarget::IncomingInvoice => $this->routeInvoice($item, $quarantinePath, $actor),
            CloudIntakeRouteTarget::Document => $this->routeDocument($connection, $route, $variables, $item, $quarantinePath, $actor),
            CloudIntakeRouteTarget::B2bOrder => $this->routeB2bOrder($connection, $quarantinePath),
            CloudIntakeRouteTarget::GaebPackage => $this->routeGaebPackage($connection, $item, $quarantinePath, $actor),
        };
    }

    /**
     * Vergabeunterlagen (Feature 108, MVP-627): ZIP zerlegen, GAEB-Dateien als
     * Vorschlag ablegen, Rest ins DMS.
     *
     * Der Ordnerweg hat **keinen Vergabevorgang** — er kennt nur den Ordner.
     * Deshalb entstehen hier ausschließlich GAEB-Vorschläge; die Zuordnung zur
     * Akte macht, wer den Vorschlag annimmt. Restdokumente ohne Akte blind ins
     * DMS zu legen, machte sie unauffindbar.
     *
     * @return array{status: CloudIntakeItemStatus, imported: Model|null, reason: string|null}
     */
    private function routeGaebPackage(CloudDocumentConnection $connection, IntakeItem $item, string $quarantinePath, User $actor): array {
        $organizationId = (int) $connection->organization_id;

        try {
            $result = app(\App\Services\Gaeb\GaebPackageIntakeService::class)->intake(
                (string) file_get_contents($quarantinePath),
                $item->name,
                $organizationId,
                $actor,
            );
        } catch (\RuntimeException $e) {
            return ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => $e->getMessage()];
        }

        if ($result['gaeb'] === []) {
            // Kein GAEB im Paket ist kein Fehler - nur nichts für diesen Weg.
            return ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => 'gaeb_package_without_gaeb'];
        }

        return ['status' => CloudIntakeItemStatus::Inbox, 'imported' => $result['gaeb'][0], 'reason' => null];
    }

    /**
     * openTRANS-Bestellungen (Feature 099, MVP-458): Datei als
     * openTRANS-2.1-ORDER parsen und Inbox-First spiegeln — kein Blind-Import.
     *
     * @return array{status: CloudIntakeItemStatus, imported: Model|null, reason: string|null}
     */
    private function routeB2bOrder(CloudDocumentConnection $connection, string $quarantinePath): array {
        $organization = $connection->organization;
        if ($organization === null
            || ! app(\App\Services\Licensing\ModuleStatusResolver::class)->isActiveFor($organization, 'module.b2b_katalog')) {
            return ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => 'b2b_order_module_inactive'];
        }

        try {
            $result = app(\App\Services\B2bCatalog\B2bOrderIntakeService::class)->intake(
                $organization,
                (string) file_get_contents($quarantinePath),
                \App\Models\B2b\B2bOrder::SOURCE_CLOUD,
            );
        } catch (\RuntimeException) {
            return ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => 'b2b_order_unreadable'];
        }

        return $result['status'] === 'duplicate'
            ? ['status' => CloudIntakeItemStatus::Duplicate, 'imported' => $result['order'], 'reason' => 'b2b_order_duplicate']
            : ['status' => CloudIntakeItemStatus::Imported, 'imported' => $result['order'], 'reason' => null];
    }

    /** @return array{status: CloudIntakeItemStatus, imported: Model|null, reason: string|null} */
    private function routeInvoice(IntakeItem $item, string $quarantinePath, User $actor): array {
        $result = $this->invoices->storeIncoming(
            $actor,
            (string) file_get_contents($quarantinePath),
            $item->mime,
            $quarantinePath,
            source: 'cloud_intake',
            originalName: $item->name,
        );

        return match ((string) $result['status']) {
            'duplicate' => ['status' => CloudIntakeItemStatus::Duplicate, 'imported' => $result['incoming'], 'reason' => 'invoice_duplicate'],
            'unreadable' => ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => 'invoice_unreadable'],
            default => ['status' => CloudIntakeItemStatus::Imported, 'imported' => $result['incoming'], 'reason' => null],
        };
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{status: CloudIntakeItemStatus, imported: Model|null, reason: string|null}
     */
    private function routeDocument(
        CloudDocumentConnection $connection,
        CloudDocumentRoute $route,
        array $variables,
        IntakeItem $item,
        string $quarantinePath,
        User $actor,
    ): array {
        // Neue Revision eines bereits importierten Items ⇒ Versionsweg.
        $previous = CloudDocumentItem::query()
            ->where('connection_id', $connection->id)
            ->where('external_item_id', $item->itemId)
            ->where('imported_type', Document::class)
            ->whereNotNull('imported_id')
            ->latest('id')
            ->first();

        if ($previous !== null) {
            /** @var Document|null $document */
            $document = Document::query()->find($previous->imported_id);
            if ($document !== null) {
                if (! $route->auto_version) {
                    $this->raiseInbox($connection, $item, 'version_proposal', $document);

                    return ['status' => CloudIntakeItemStatus::Inbox, 'imported' => $document, 'reason' => 'version_proposal'];
                }

                $version = $this->documents->addVersionFromContents(
                    $document,
                    $actor,
                    (string) file_get_contents($quarantinePath),
                    $item->name,
                    $item->mime,
                    note: 'Cloud-Import: neue Revision ' . $item->revision,
                );

                return ['status' => CloudIntakeItemStatus::Imported, 'imported' => $version, 'reason' => 'new_version'];
            }
        }

        // Zielkontext auflösen: feste Referenz gewinnt, sonst Pfadvariablen.
        $resolution = $this->resolveDocumentable($route, $variables);
        if ($resolution['error'] !== null) {
            $this->raiseInbox($connection, $item, $resolution['error']);

            return ['status' => CloudIntakeItemStatus::Inbox, 'imported' => null, 'reason' => $resolution['error']];
        }

        $upload = new UploadedFile($quarantinePath, $item->name, $item->mime, null, true);
        $document = $this->documents->create($resolution['documentable'], $actor, [
            'title' => $item->name,
            'document_type' => (string) ($route->document_type ?: 'other'),
        ], $upload);

        return ['status' => CloudIntakeItemStatus::Imported, 'imported' => $document, 'reason' => null];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{documentable: Model|null, error: string|null}
     */
    private function resolveDocumentable(CloudDocumentRoute $route, array $variables): array {
        if ($route->target_ref_type !== null && $route->target_ref_id !== null) {
            $ref = $route->targetRef;

            return $ref instanceof Model
                ? ['documentable' => $ref, 'error' => null]
                : ['documentable' => null, 'error' => 'target_ref_missing'];
        }

        if ($variables === []) {
            return ['documentable' => null, 'error' => null]; // freies Dokument
        }

        if (count($variables) > 1) {
            return ['documentable' => null, 'error' => 'ambiguous_variables'];
        }

        $variable = (string) array_key_first($variables);
        $value = trim((string) reset($variables));
        $documentable = $this->lookup($variable, $value);

        return $documentable !== null
            ? ['documentable' => $documentable, 'error' => null]
            : ['documentable' => null, 'error' => 'unresolved_' . $variable];
    }

    /**
     * Nummern-Lookup je Variable — NUR vorhandene Objekte, nie Auto-Anlage.
     * Aufträge (DiaryEntry) haben keine fachliche Nummer; ihre kanonische
     * externe Kennung ist die Sqid (URLs/Formulare) — der Ordnername trägt
     * also die Auftrags-Sqid.
     */
    private function lookup(string $variable, string $value): ?Model {
        if ($value === '') {
            return null;
        }

        return match ($variable) {
            'customer_number' => Customer::query()->where('number', $value)->first(),
            'project_number' => Project::query()->where('number', $value)->first(),
            'order_number' => DiaryEntry::query()->whereKey(\App\Support\Sqid::decodeOrNumeric(DiaryEntry::class, $value))->first(),
            'asset_number' => Asset::query()->where('asset_no', $value)->first(),
            'contract_number' => Contract::query()->where('number', $value)->first(),
            default => null,
        };
    }

    /** Inbox-Fall mit Vorschau + Quellnachweis (Konzept §Sichere Übernahme). */
    private function raiseInbox(CloudDocumentConnection $connection, IntakeItem $item, string $caseReason, ?Document $document = null): void {
        IntegrationInboxItem::query()->withoutGlobalScopes()->firstOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'plugin_id' => $connection->provider->pluginId(),
                'dedupe_key' => 'cloud-intake:' . $connection->id . ':' . CloudDocumentItem::itemRevisionHash($item->itemId, $item->revision),
            ],
            [
                'source' => 'cloud_intake',
                'target_type' => Document::class,
                'external_type' => 'cloud_document',
                'external_id' => $item->itemId,
                'case_type' => $caseReason === 'ambiguous_variables'
                    ? IntegrationInboxItem::CASE_AMBIGUOUS
                    : IntegrationInboxItem::CASE_UNMATCHED,
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'referenceable_type' => $document?->getMorphClass(),
                'referenceable_id' => $document?->getKey(),
                'remote_snapshot' => [
                    'path' => $item->path,
                    'name' => $item->name,
                    'revision' => $item->revision,
                    'size' => $item->size,
                    'reason' => $caseReason,
                ],
                'display_title' => $item->name,
                'display_subtitle' => $item->path,
                'occurred_at' => Carbon::now(),
            ],
        );
    }
}
