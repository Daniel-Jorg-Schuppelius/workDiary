<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Protocol;

use App\Enums\Classification\ClassificationRequirementPhase;
use App\Enums\Diary\Status as DiaryStatus;
use App\Enums\Notification\NotificationEvent;
use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueVisibility};
use App\Enums\Protocol\{ProtocolEventType, ProtocolItemResult, ProtocolItemType, ProtocolSignatureMethod, ProtocolSignatureRole, ProtocolStatus, ProtocolType, ProtocolVisibility};
use App\Exceptions\{ClassificationRequirementException, InvalidProtocolTransitionException, ProtocolValidationException};
use App\Models\Attachments\Attachment;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolItem, ProtocolSignature, ProtocolTemplate};
use App\Services\Attachments\FileAttacher;
use App\Services\Classification\ClassificationRequirementValidator;
use App\Services\Diary\OrderService;
use App\Services\Integration\LifecycleWebhookPublisher;
use App\Services\Notification\NotificationDispatcher;
use App\Services\OpenIssue\OpenIssueService;
use App\Support\MorphMap;
use CommonToolkit\Helper\Data\DataUrlHelper;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{DB, Storage};
use InvalidArgumentException;

/**
 * Domain-Service für Protokolle (MVP-020).
 *
 * Verantwortlich für Anlage, Statuswechsel (draft → in_review → signed →
 * archived / superseded), Item-Pflege und Audit-Trail. Controller greifen
 * nie direkt auf die Modelle zu, damit Pflicht-Checks und Events konsistent
 * bleiben.
 */
class ProtocolService {
    /** Obergrenze des gezeichneten Unterschriftsbilds (wie Fahrtenbuch und Unterweisung). */
    public const int SIGNATURE_MAX_BYTES = 1_000_000;

    public function __construct(
        private readonly ProtocolItemValidator $itemValidator,
        private readonly ClassificationRequirementValidator $requirements,
        private readonly OpenIssueService $openIssues,
        private readonly ProtocolHasher $hasher,
        private readonly ProtocolPdfRenderer $pdfRenderer,
    ) {}

    /**
     * Legt ein neues Protokoll im Status `draft` an.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Model $subject, User $creator, array $attributes): Protocol {
        $type = $this->parseType($this->stringAttribute($attributes, 'type', ProtocolType::Service->value));
        $visibility = $this->parseVisibility(
            $this->stringAttribute($attributes, 'visibility', ProtocolVisibility::Internal->value)
        );

        $occurredAt = $this->dateAttribute($attributes['occurred_at'] ?? null, 'occurred_at') ?? Carbon::now();

        return DB::transaction(function () use ($subject, $creator, $attributes, $type, $visibility, $occurredAt): Protocol {
            $protocol = Protocol::query()->create([
                'organization_id' => $subject->getAttribute('organization_id') ?: $creator->organization_id,
                'type' => $type->value,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'state_initial' => $attributes['state_initial'] ?? null,
                'state_final' => null,
                'status' => ProtocolStatus::Draft->value,
                'revision' => 1,
                'visibility' => $visibility->value,
                'occurred_at' => $occurredAt,
                'created_by_user_id' => $creator->id,
            ]);

            $this->record($protocol, ProtocolEventType::Created, $creator, [
                'type' => $type->value,
                'visibility' => $visibility->value,
            ]);

            $template = isset($attributes['template_id'])
                ? ProtocolTemplate::query()->usable()->where('organization_id', $protocol->organization_id)->find((int) $attributes['template_id'])
                : null;
            if ($template instanceof ProtocolTemplate) {
                $protocol->forceFill(['template_id' => $template->id, 'template_version' => $template->version])->save();
                $this->addTemplateItems($protocol, $creator, $template->items);
            }

            return $protocol->fresh(['journal']) ?? $protocol;
        });
    }

    /**
     * Aktualisiert mutable Felder. Nur im Status `draft` erlaubt.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Protocol $protocol, User $actor, array $attributes): Protocol {
        $this->assertEditable($protocol);

        $protocol->update(array_intersect_key($attributes, array_flip([
            'title',
            'description',
            'state_initial',
            'state_final',
            'occurred_at',
            'visibility',
            'type',
        ])));

        return $protocol->refresh();
    }

    public function requestReview(Protocol $protocol, User $actor): Protocol {
        $this->assertActionAllowed($protocol, 'requestReview');
        $this->assertProtocolValid($protocol);
        $protocol->update(['status' => ProtocolStatus::InReview->value]);
        $this->record($protocol, ProtocolEventType::RequestedReview, $actor);

        // Ab hier ist der Inhalt eingefroren. Eine Unterschrift von früher, die
        // nicht mehr zu ihm passt, gilt nicht weiter (MVP-1572).
        foreach ($this->staleSignatures($protocol) as $signature) {
            $this->voidSignature($protocol, $signature, $actor, 'content_changed');
        }

        return $protocol->refresh();
    }

    public function returnToDraft(Protocol $protocol, User $actor, ?string $reason = null): Protocol {
        $this->assertActionAllowed($protocol, 'returnToDraft');

        return DB::transaction(function () use ($protocol, $actor, $reason): Protocol {
            $protocol->update(['status' => ProtocolStatus::Draft->value]);
            $this->record($protocol, ProtocolEventType::ReturnedToDraft, $actor, [
                'reason' => $reason,
            ]);

            // Der Entwurf ist wieder änderbar: Unterschriften und offene Links
            // bezogen sich auf den eingefrorenen Stand und verfallen mit ihm.
            foreach ($protocol->signatures()->get() as $signature) {
                $this->voidSignature($protocol, $signature, $actor, 'returned_to_draft');
            }
            $this->expireOpenTokens($protocol, $actor, 'returned_to_draft');

            return $protocol->refresh();
        });
    }

    /**
     * Schliesst die Erstellungsphase ab und setzt das Protokoll auf `signed`.
     * Optional kann gleichzeitig eine Signatur erfasst werden. Abnahme und
     * Übergabe verlangen die Unterschriften ihres Typs (MVP-1572) — fehlt eine,
     * bleibt das Protokoll offen.
     *
     * @param  array<string, mixed>|null  $signatureData
     */
    public function sign(Protocol $protocol, User $actor, ?array $signatureData = null): Protocol {
        $this->assertActionAllowed($protocol, $protocol->status === ProtocolStatus::Draft ? 'signDirect' : 'sign');
        $this->assertProtocolValid($protocol, ClassificationRequirementPhase::BeforeSign);

        // Vor dem ersten Schreiben prüfen: ein gespeichertes Unterschriftsbild macht kein Rollback rückgängig.
        $incoming = $signatureData !== null
            ? $this->parseRole($this->stringAttribute($signatureData, 'role', ProtocolSignatureRole::Contractor->value))
            : null;
        $missing = $protocol->missingSignatureRoles($incoming);
        if ($missing !== []) {
            throw new ProtocolValidationException([self::missingSignaturesMessage($missing)]);
        }
        // Ein Nachweis trägt nur Unterschriften, deren Hash zum Inhalt passt.
        $stale = $this->staleSignatures($protocol);
        if ($stale !== []) {
            throw new ProtocolValidationException([(string) __('protocol.validation.signature.stale', [
                'names' => implode(', ', array_map(static fn (ProtocolSignature $signature): string => $signature->signer_name, $stale)),
            ])]);
        }

        $image = null;
        try {
            $signed = $this->finalize($protocol, $actor, $signatureData, $image);
        } catch (\Throwable $e) {
            // Scheitert der Abschluss nach der Unterschrift, rollt ihre Zeile zurück — die Datei nicht.
            if (is_string($image) && ! Attachment::query()->withoutGlobalScopes()->where('path', $image)->exists()) {
                Storage::disk('local')->delete($image);
            }

            throw $e;
        }

        // Lifecycle-Webhook (MVP-718): protocol.signed erst nach der Transaktion.
        app(LifecycleWebhookPublisher::class)->protocolSigned($signed);

        return $signed;
    }

    /** @param  array<string, mixed>|null  $signatureData */
    private function finalize(Protocol $protocol, User $actor, ?array $signatureData, ?string &$image): Protocol {
        return DB::transaction(function () use ($protocol, $actor, $signatureData, &$image): Protocol {
            $this->lockUnchangedStatus($protocol);
            $this->assertActionAllowed($protocol, $protocol->status === ProtocolStatus::Draft ? 'signDirect' : 'sign');
            if ($signatureData !== null) {
                $image = $this->addSignature($protocol, $actor, $signatureData)->signature_image_path;
            }

            $protocol->update([
                'status' => ProtocolStatus::Signed->value,
                'signed_at' => Carbon::now(),
            ]);

            $this->record($protocol, ProtocolEventType::Signed, $actor, [
                'with_signature' => $signatureData !== null,
            ]);

            $subject = $protocol->subject;
            if ($subject instanceof DiaryEntry && $subject->status === DiaryStatus::Completed) {
                app(OrderService::class)->handover($subject, $actor, $protocol->refresh());
            }

            $this->expireOpenTokens($protocol, $actor, 'signed');
            $path = $this->renderPdfFor($protocol->refresh(), $actor);
            $this->pdfRenderer->discardSuperseded($protocol, $path);

            return $protocol->refresh();
        });
    }

    /**
     * Unterschrift vor dem Abschluss erfassen (MVP-1572): vor Ort am Gerät,
     * Papierabnahme oder Kundenportal — nur „in Prüfung“. Das Protokoll bleibt
     * offen; der Abschluss folgt mit {@see sign()}, sobald alle
     * Pflichtunterschriften da sind.
     *
     * @param  array<string, mixed>  $data
     */
    public function captureSignature(Protocol $protocol, User $actor, array $data): ProtocolSignature {
        if ($protocol->status->isImmutable()) {
            throw InvalidProtocolTransitionException::immutable($protocol->status);
        }
        // Nur „in Prüfung“ ist der Inhalt eingefroren; im Entwurf könnte er sich unter der Unterschrift ändern.
        if ($protocol->status !== ProtocolStatus::InReview) {
            throw new ProtocolValidationException([(string) __('protocol.validation.signature.reviewFirst')]);
        }
        $this->assertProtocolValid($protocol, ClassificationRequirementPhase::BeforeSign);

        return $this->addSignature($protocol, $actor, $data);
    }

    /** @param  list<ProtocolSignatureRole>  $roles */
    public static function missingSignaturesMessage(array $roles): string {
        return (string) __('protocol.validation.signature.requiredRoles', [
            'roles' => implode(', ', array_map(static fn (ProtocolSignatureRole $role): string => $role->label(), $roles)),
        ]);
    }

    /**
     * Das PDF des Abschlusses als Anhang am Protokoll (abnahme-signatur.md §5.2);
     * je Datei genau einmal.
     */
    private function registerPdfAttachment(Protocol $protocol, string $path, User $actor): void {
        $disk = Storage::disk(ProtocolPdfRenderer::DISK);
        if ($protocol->attachments()->where('path', $path)->exists() || ! $disk->exists($path)) {
            return;
        }

        $protocol->attachments()->create([
            'organization_id' => $protocol->organization_id,
            'user_id' => $actor->id,
            'disk' => ProtocolPdfRenderer::DISK,
            'path' => $path,
            'original_name' => sprintf('%s v%d.pdf', $protocol->type->label(), $protocol->revision),
            'mime' => 'application/pdf',
            'size' => $disk->size($path),
            'meta_type' => Protocol::META_PDF,
        ]);
    }

    /**
     * Legt das PDF ab (idempotent) und vermerkt den Event mit Pfad + Hash.
     * Nur für Nachweise; Entwürfe liefert {@see ProtocolPdfRenderer::content()}.
     */
    public function renderPdfFor(Protocol $protocol, User $actor): string {
        $start = microtime(true);
        $path = $this->pdfRenderer->render($protocol);
        $this->record($protocol, ProtocolEventType::PdfRendered, $actor, [
            'path' => $path,
            'hash' => $this->pdfRenderer->hashFor($protocol),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
        // Als Anhang steht die Datei im Löschregister der Organisation — auch bei Nachweisen von früher.
        if ($protocol->status->isImmutable()) {
            $this->registerPdfAttachment($protocol, $path, $actor);
        }

        return $path;
    }

    public function recordPdfDownload(Protocol $protocol, User $actor): void {
        $this->record($protocol, ProtocolEventType::PdfDownloaded, $actor, [
            'hash' => $this->pdfRenderer->hashFor($protocol),
        ]);
    }

    public function recordPortalPdfDownload(Protocol $protocol, User $portalUser): void {
        $this->record($protocol, ProtocolEventType::PortalPdfDownloaded, $portalUser, [
            'hash' => $this->pdfRenderer->hashFor($protocol),
        ]);
    }

    public function archive(Protocol $protocol, User $actor): Protocol {
        $this->assertActionAllowed($protocol, 'archive');
        $protocol->update([
            'status' => ProtocolStatus::Archived->value,
            'archived_at' => Carbon::now(),
        ]);
        $this->record($protocol, ProtocolEventType::Archived, $actor);

        return $protocol->refresh();
    }

    /**
     * Erstellt eine Korrektur-Revision und markiert das alte Protokoll als
     * `superseded`. Die neue Revision uebernimmt Stammdaten, startet im
     * Status `draft` und referenziert das Original via `supersedes_id`.
     */
    public function supersede(Protocol $protocol, User $actor, string $reason): Protocol {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Begründung ist beim Ersetzen Pflicht.');
        }
        $this->assertActionAllowed($protocol, 'supersede');

        return DB::transaction(function () use ($protocol, $actor, $reason): Protocol {
            $copy = Protocol::query()->create([
                'organization_id' => $protocol->organization_id,
                'type' => $protocol->type->value,
                'subject_type' => $protocol->subject_type,
                'subject_id' => $protocol->subject_id,
                'title' => $protocol->title,
                'description' => $protocol->description,
                'state_initial' => $protocol->state_initial,
                'state_final' => $protocol->state_final,
                'status' => ProtocolStatus::Draft->value,
                'revision' => $protocol->revision + 1,
                'supersedes_id' => $protocol->id,
                'visibility' => $protocol->visibility->value,
                'occurred_at' => Carbon::now(),
                'created_by_user_id' => $actor->id,
            ]);

            $protocol->update(['status' => ProtocolStatus::Superseded->value]);
            $this->expireOpenTokens($protocol, $actor, 'superseded');

            $this->record($protocol, ProtocolEventType::SupersededBy, $actor, [
                'replacement_id' => $copy->id,
                'reason' => $reason,
            ]);
            $this->record($copy, ProtocolEventType::Created, $actor, [
                'supersedes_id' => $protocol->id,
                'reason' => $reason,
            ]);

            return $copy->refresh();
        });
    }

    // ----- Items -----

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addItem(Protocol $protocol, User $actor, array $attributes): ProtocolItem {
        $this->assertEditable($protocol);

        $type = $this->parseItemType($this->stringAttribute($attributes, 'item_type', ProtocolItemType::Text->value));

        $sortOrder = $attributes['sort_order'] ?? null;
        if ($sortOrder === null) {
            // Ans Ende einsortieren; max() liefert numerisch oder null (noch keine Items).
            $maxSort = $protocol->items()->max('sort_order');
            $sortOrder = is_numeric($maxSort) ? (int) $maxSort + 1 : 1;
        }

        $item = $protocol->items()->create([
            'parent_item_id' => $attributes['parent_item_id'] ?? null,
            'sort_order' => $sortOrder,
            'item_type' => $type->value,
            'label' => $attributes['label'],
            'description' => $attributes['description'] ?? null,
            'required' => (bool) ($attributes['required'] ?? false),
            'value_json' => $attributes['value_json'] ?? null,
        ]);

        $this->record($protocol, ProtocolEventType::ItemAdded, $actor, [
            'item_id' => $item->id,
            'label' => $item->label,
        ]);

        return $item;
    }

    /**
     * Punkte einer Vorlage als Kopie (MVP-901); Gruppen tragen ihre Kinder.
     *
     * @param list<array<string, mixed>> $items
     */
    private function addTemplateItems(Protocol $protocol, User $actor, array $items, ?int $parentId = null): void {
        foreach ($items as $spec) {
            $type = ProtocolItemType::tryFrom((string) ($spec['item_type'] ?? ''));
            if ($type === null || trim((string) ($spec['label'] ?? '')) === '') {
                continue;
            }
            $config = is_array($spec['config'] ?? null) ? $spec['config'] : [];
            $item = $this->addItem($protocol, $actor, [
                'label' => (string) $spec['label'],
                'item_type' => $type->value,
                'description' => $spec['description'] ?? null,
                'required' => (bool) ($spec['required'] ?? false),
                'value_json' => $config === [] ? null : $config,
                'parent_item_id' => $parentId,
            ]);
            if ($type === ProtocolItemType::Group && is_array($spec['children'] ?? null)) {
                $this->addTemplateItems($protocol, $actor, array_values($spec['children']), (int) $item->id);
            }
        }
    }

    public function removeItem(ProtocolItem $item, User $actor): void {
        $protocol = $this->protocolOf($item);
        $this->assertEditable($protocol);

        $id = $item->id;
        $item->delete();

        $this->record($protocol, ProtocolEventType::ItemRemoved, $actor, [
            'item_id' => $id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function fillItem(ProtocolItem $item, User $actor, array $values): ProtocolItem {
        $protocol = $this->protocolOf($item);
        $this->assertEditable($protocol);

        // Werte legen sich über die Konfiguration des Punkts (Optionen,
        // Grenzen, Einheit, verknüpfter Mangel-Punkt) — nie ersetzen.
        $provided = $values['value_json'] ?? null;
        $newValue = $provided !== null
            ? array_replace(is_array($item->value_json) ? $item->value_json : [], $this->valueJsonFrom($provided))
            : $item->value_json;

        // Defect-Punkt: bei erster Befuellung automatisch Open-Issue anlegen
        // (MVP-021 §3.12, Integration mit MVP-024).
        if (
            $item->item_type === ProtocolItemType::Defect
            && is_array($newValue)
            && empty($newValue['open_issue_id'])
            && $protocol->subject !== null
        ) {
            $descriptionRaw = $newValue['description'] ?? '';
            $description = is_scalar($descriptionRaw) ? (string) $descriptionRaw : '';
            $severityRaw = $newValue['severity'] ?? 'low';
            if (trim($description) !== '') {
                $issue = $this->openIssues->create($protocol->subject, $actor, [
                    'title' => sprintf('%s: %s', $item->label, mb_substr($description, 0, 80)),
                    'description' => $description,
                    'severity' => $this->mapDefectSeverity(is_scalar($severityRaw) ? (string) $severityRaw : 'low'),
                    'category' => $newValue['category'] ?? null,
                    'source_type' => 'protocolDefect',
                    'source_ref_id' => (string) $item->id,
                ]);
                $newValue['open_issue_id'] = $issue->id;
            }
        }

        $resultRaw = $values['result'] ?? null;
        $explicitResult = is_scalar($resultRaw)
            ? ProtocolItemResult::tryFrom((string) $resultRaw)
            : null;

        $tempItem = (clone $item);
        $tempItem->value_json = $newValue;
        $derived = $this->itemValidator->deriveResult($tempItem);
        $result = $explicitResult ?? $derived ?? $item->result;

        $item->update([
            'value_json' => $newValue,
            'result' => $result?->value,
            'note' => $values['note'] ?? $item->note,
            'measured_at' => Carbon::now(),
            'measured_by_user_id' => $actor->id,
        ]);

        $errors = $this->itemValidator->validate($item->refresh());
        if ($errors !== []) {
            throw new ProtocolValidationException($errors);
        }

        $this->record($protocol, ProtocolEventType::ItemFilled, $actor, [
            'item_id' => $item->id,
            'result' => $result?->value,
        ]);

        return $item->refresh();
    }

    /**
     * Beschreibungstext eines Punkts ersetzen (Feature 143, MVP-711: Übernahme
     * eines KI-Vorschlags) — dieselben Freeze-Regeln wie fillItem(), Ereignis
     * mit `ai_assisted`-Kennzeichen statt stiller Änderung.
     */
    public function updateItemText(ProtocolItem $item, User $actor, string $description, bool $aiAssisted = false): ProtocolItem {
        $protocol = $this->protocolOf($item);
        $this->assertEditable($protocol);

        $item->update(['description' => $description]);

        $this->record($protocol, ProtocolEventType::ItemFilled, $actor, [
            'item_id' => $item->id,
            'field' => 'description',
            'ai_assisted' => $aiAssisted,
        ]);

        return $item->refresh();
    }

    /**
     * Neue Reihenfolge der Punkte (MVP-1573). Unterpunkte bleiben hinter ihrer
     * Gruppe, damit PDF (flach nach `sort_order`) und Detailseite dieselbe
     * Reihenfolge zeigen; nicht genannte Punkte behalten ihren Platz am Ende,
     * fremde IDs zählen nicht.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorderItems(Protocol $protocol, User $actor, array $orderedIds): void {
        $this->assertEditable($protocol);

        $position = array_flip(array_values(array_unique($orderedIds)));
        $items = $protocol->items()->get(['id', 'protocol_id', 'parent_item_id', 'sort_order'])
            ->sortBy(static fn (ProtocolItem $item): array => [$position[$item->id] ?? PHP_INT_MAX, $item->sort_order, $item->id])
            ->values();
        $known = $items->pluck('id')->flip();
        $byParent = $items->groupBy(static fn (ProtocolItem $item): int => $known->has($item->parent_item_id) ? (int) $item->parent_item_id : 0);

        /** @var list<int> $final */
        $final = [];
        $walk = static function (int $parentId) use (&$walk, &$final, $byParent): void {
            foreach ($byParent->get($parentId, []) as $item) {
                $final[] = (int) $item->id;
                $walk((int) $item->id);
            }
        };
        $walk(0);

        DB::transaction(function () use ($protocol, $final): void {
            foreach ($final as $index => $itemId) {
                $protocol->items()->whereKey($itemId)->update(['sort_order' => $index + 1]);
            }
        });

        $this->record($protocol, ProtocolEventType::ItemReordered, $actor, [
            'order' => $final,
        ]);
    }

    // ----- Signatures -----

    /**
     * Schreibt die Unterschrift mit Inhalts-Hash. Ein Bild kommt nur aus
     * `signature_image` (gezeichnet, PNG als Data-URL) oder `signature_scan`
     * (Datei der Papierabnahme) und liegt als Anhang am Protokoll — ein Pfad
     * aus der Eingabe wird nie übernommen (Sicherheitsscan 2026-08-23, S-63).
     *
     * @param  array<string, mixed>  $data
     */
    public function addSignature(Protocol $protocol, User $actor, array $data): ProtocolSignature {
        $role = $this->parseRole($this->stringAttribute($data, 'role', ProtocolSignatureRole::Contractor->value));
        $method = $this->parseMethod($this->stringAttribute($data, 'method', ProtocolSignatureMethod::Onscreen->value));

        $signerNameRaw = $data['signer_name'] ?? null;
        $signerName = is_scalar($signerNameRaw) ? (string) $signerNameRaw : $actor->name;
        $signedAt = $this->dateAttribute($data['signed_at'] ?? null, 'signed_at') ?? Carbon::now();

        if ($protocol->signatures()->where('role', $role->value)->where('signer_name', $signerName)->exists()) {
            throw new ProtocolValidationException([(string) __('protocol.validation.signature.duplicate', ['name' => $signerName, 'role' => $role->label()])]);
        }

        // Gehasht wird der gespeicherte Stand, nicht eine früher geladene Punktliste.
        $hash = $this->hasher->contentHash(
            $protocol->unsetRelation('items'),
            $signerName,
            $role->value,
            $signedAt->toIso8601String(),
        );

        // Uploader: beim Link niemand aus dem Haus, im Portal das Portalkonto.
        $uploaderId = array_key_exists('uploaded_by_user_id', $data) ? $data['uploaded_by_user_id'] : $actor->id;
        $image = $this->storeSignatureImage($protocol, $role, $data, is_numeric($uploaderId) ? (int) $uploaderId : null);

        try {
            return $this->writeSignature($protocol, $actor, $data, $role, $method, $signerName, $signedAt, $hash, $image);
        } catch (\Throwable $e) {
            // Die Zeile rollt zurück, die Datei nicht: ohne Unterschrift bleibt kein Bild liegen.
            if ($image !== null) {
                Storage::disk($image->disk)->delete($image->path);
                $image->newQuery()->whereKey($image->id)->delete();
            }

            throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    private function writeSignature(Protocol $protocol, User $actor, array $data, ProtocolSignatureRole $role, ProtocolSignatureMethod $method, string $signerName, Carbon $signedAt, string $hash, ?Attachment $image): ProtocolSignature {
        return DB::transaction(function () use ($protocol, $actor, $data, $role, $method, $signerName, $signedAt, $hash, $image): ProtocolSignature {
            $this->lockUnchangedStatus($protocol);
            if ($protocol->status->isImmutable()) {
                throw InvalidProtocolTransitionException::immutable($protocol->status);
            }

            $signature = ProtocolSignature::query()->create([
                'protocol_id' => $protocol->id,
                'role' => $role->value,
                'signer_name' => $signerName,
                'signer_email' => $data['signer_email'] ?? null,
                'signed_at' => $signedAt,
                'method' => $method->value,
                'signature_image_path' => $image?->path,
                'signature_attachment_id' => $image?->id,
                'ip' => $data['ip'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'hash' => $hash,
            ]);

            $this->record($protocol, ProtocolEventType::SignatureAdded, $actor, [
                'signature_id' => $signature->id,
                'role' => $role->value,
                'method' => $method->value,
                'signer_name' => $signerName,
                'with_image' => $image !== null,
                'original_on_file' => $method === ProtocolSignatureMethod::Paper ? (bool) ($data['original_confirmed'] ?? false) : null,
            ]);

            // MVP-650: mit der (ersten) Signatur den Designstand einfrieren —
            // signierte Protokolle rendern dauerhaft mit dem damaligen Profil
            // (idempotent, weitere Signaturen ändern nichts).
            if ($protocol->organization !== null) {
                app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->snapshot(
                    $protocol,
                    \App\Enums\DocumentDesign\RenderDocumentKind::Protocol,
                    $protocol->organization,
                    user: $actor,
                );
            }

            return $signature;
        });
    }

    /**
     * Sperrt die Protokollzeile und prüft, dass der Status noch der geprüfte ist
     * (MVP-1627): Rückgabe in den Entwurf oder Abschluss dazwischen ließen die
     * Unterschrift sonst an einem anderen Stand hängen.
     */
    private function lockUnchangedStatus(Protocol $protocol): void {
        $current = Protocol::query()->whereKey($protocol->getKey())->lockForUpdate()->value('status');
        $current = $current instanceof ProtocolStatus ? $current : ProtocolStatus::tryFrom((string) $current);
        if ($current !== $protocol->status) {
            throw InvalidProtocolTransitionException::from($current ?? $protocol->status, 'sign');
        }
    }

    /** @param  array<string, mixed>  $data */
    private function storeSignatureImage(Protocol $protocol, ProtocolSignatureRole $role, array $data, ?int $uploaderId): ?Attachment {
        $extra = ['organization_id' => $protocol->organization_id, 'meta_type' => Protocol::META_SIGNATURE];
        $attacher = app(FileAttacher::class);

        $scan = $data['signature_scan'] ?? null;
        if ($scan instanceof UploadedFile) {
            return $attacher->store($protocol, $scan, $uploaderId, $extra, 'protocol-signatures');
        }

        $image = $data['signature_image'] ?? null;
        if (! is_string($image) || $image === '') {
            return null;
        }
        $binary = DataUrlHelper::decode($image, ['image/png'], self::SIGNATURE_MAX_BYTES);
        if ($binary === false) {
            throw new ProtocolValidationException([(string) __('protocol.validation.signature.imageInvalid')]);
        }

        return $attacher->storeContent($protocol, $binary, 'unterschrift-' . $role->value . '.png', 'image/png', $uploaderId, $extra, 'protocol-signatures');
    }

    /**
     * Unterschriften, deren Hash nicht (mehr) zum gespeicherten Inhalt passt.
     *
     * @return list<ProtocolSignature>
     */
    public function staleSignatures(Protocol $protocol): array {
        $protocol->unsetRelation('items');

        return array_values($protocol->signatures()->get()->reject(fn (ProtocolSignature $signature): bool => hash_equals(
            (string) $signature->hash,
            $this->hasher->contentHash($protocol, $signature->signer_name, $signature->role->value, $signature->signed_at->toIso8601String()),
        ))->all());
    }

    /** Verwirft eine Unterschrift samt Bild; im Journal bleibt, wer unterschrieben hatte und warum sie nicht mehr gilt. */
    private function voidSignature(Protocol $protocol, ProtocolSignature $signature, User $actor, string $reason): void {
        $this->record($protocol, ProtocolEventType::SignatureVoided, $actor, [
            'signature_id' => $signature->id,
            'role' => $signature->role->value,
            'method' => $signature->method->value,
            'signer_name' => $signature->signer_name,
            'signed_at' => $signature->signed_at->toIso8601String(),
            'hash' => $signature->hash,
            'reason' => $reason,
        ]);
        $signature->signatureAttachment?->delete();
        $signature->delete();
    }

    /** Offene Signaturlinks verfallen, sobald das Protokoll nicht mehr zur Unterschrift vorliegt. */
    private function expireOpenTokens(Protocol $protocol, User $actor, string $reason): void {
        foreach ($protocol->signatureTokens()->whereNull('used_at')->where('expires_at', '>', Carbon::now())->get() as $token) {
            $token->forceFill(['expires_at' => Carbon::now()])->save();
            $this->record($protocol, ProtocolEventType::SignatureLinkRevoked, $actor, [
                'token_id' => $token->id,
                'role' => $token->role->value,
                'signer_email' => $token->signer_email,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * Ablehnung der Abnahme durch den Kunden (Link oder Portal): je Mangel ein
     * offener Punkt am Auftrag, das Protokoll geht zurück in den Entwurf, das
     * Haus wird benachrichtigt.
     *
     * @param  array{reason: string, signer_name?: string|null, issues?: list<string>, ip?: string|null, user_agent?: string|null, token_id?: int|null, role?: string|null, channel: string}  $data
     */
    public function rejectByCustomer(Protocol $protocol, User $actor, ?User $notify, array $data): void {
        $reason = trim($data['reason']);
        $name = $data['signer_name'] ?? null;

        DB::transaction(function () use ($protocol, $actor, $data, $reason, $name): void {
            $issueSubject = $protocol->subject instanceof Model ? $protocol->subject : $protocol;
            $issues = array_values(array_filter(array_map(
                static fn ($line): string => trim((string) $line),
                $data['issues'] ?? []
            ), static fn (string $line): bool => $line !== ''));
            if ($issues === []) {
                // Mindestens ein Offener Punkt aus der Pflicht-Begründung.
                $issues = [$reason];
            }

            foreach ($issues as $line) {
                $this->openIssues->create($issueSubject, $actor, [
                    'organization_id' => $protocol->organization_id,
                    'source_type' => OpenIssueSource::CustomerRejection->value,
                    'source_ref_id' => $protocol->id,
                    'title' => Str::limit($line, 200, ''),
                    'description' => __('protocol.signature.rejectIssueDescription', [
                        'protocol' => $protocol->title,
                        'name' => $name ?? __('protocol.signature.customer'),
                    ]),
                    'severity' => OpenIssueSeverity::Medium->value,
                    'visibility' => OpenIssueVisibility::Customer->value,
                ]);
            }

            $this->record($protocol, ProtocolEventType::SignatureRejected, $actor, [
                'token_id' => $data['token_id'] ?? null,
                'role' => $data['role'] ?? ProtocolSignatureRole::Customer->value,
                'channel' => $data['channel'],
                'signer_name' => $name,
                'reason' => $reason,
                'issue_count' => count($issues),
                'ip' => $data['ip'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ]);

            if ($protocol->status === ProtocolStatus::InReview) {
                $this->returnToDraft($protocol, $actor, $reason);
            }
        });

        $this->notifyHouse(NotificationEvent::ProtocolCustomerRejected, $protocol, $notify, 'notification.message.protocol_customer_rejected', [
            'name' => $name ?? (string) __('protocol.signature.customer'),
            'title' => $protocol->title,
            'reason' => Str::limit($reason, 160),
        ]);
    }

    /** Meldung ans Haus nach einer Unterschrift des Kunden über Link oder Portal; sagt, ob jetzt abgeschlossen werden kann. */
    public function notifyCustomerSigned(Protocol $protocol, ?User $notify, string $signerName): void {
        $missing = $protocol->missingSignatureRoles();
        $this->notifyHouse(
            NotificationEvent::ProtocolCustomerSigned,
            $protocol,
            $notify,
            $missing === [] ? 'notification.message.protocol_customer_signed_ready' : 'notification.message.protocol_customer_signed_pending',
            [
                'name' => $signerName,
                'title' => $protocol->title,
                'roles' => implode(', ', array_map(static fn (ProtocolSignatureRole $role): string => $role->label(), $missing)),
            ],
        );
    }

    /** Wer das Protokoll zuletzt zur Prüfung gestellt hat, sonst wer es angelegt hat. */
    public function reviewRequester(Protocol $protocol): ?User {
        $event = $protocol->journal()->where('event', ProtocolEventType::RequestedReview)->latest('id')->first();

        $userId = $event?->getAttribute('actor_user_id') ?? $protocol->created_by_user_id;

        return User::query()->whereKey((int) $userId)->first();
    }

    /** @param  array<string, string>  $params */
    private function notifyHouse(NotificationEvent $event, Protocol $protocol, ?User $recipient, string $messageKey, array $params): void {
        // Erst nach dem Commit — die Meldung darf die Unterschrift nie aufhalten.
        DB::afterCommit(function () use ($event, $protocol, $recipient, $messageKey, $params): void {
            app(NotificationDispatcher::class)->notify($event, $protocol, $recipient, [
                'title' => $protocol->title,
                'message' => (string) __($messageKey, $params),
                'message_key' => $messageKey,
                'message_params' => $params,
                'url' => route('protocols.show', $protocol),
            ]);
        });
    }

    // ----- Helpers -----

    private function assertEditable(Protocol $protocol): void {
        if (! $protocol->status->isEditable()) {
            throw InvalidProtocolTransitionException::immutable($protocol->status);
        }
    }

    /**
     * @param  ClassificationRequirementPhase|null  $phase  Gesetzt nur dort, wo die
     *   Klassifikationspflicht des Auftrags mitgilt (Signatur) — die
     *   Pruefanforderung ist ein Zwischenschritt und bleibt frei davon.
     */
    private function assertProtocolValid(Protocol $protocol, ?ClassificationRequirementPhase $phase = null): void {
        $errors = $this->itemValidator->validateProtocol($protocol->load('items'));

        // Pflichtklassifikationen vor der Signatur (MVP-795): ein Protokoll
        // zu einem Auftrag erbt dessen Klassifikationspflichten. Die Lücken
        // reihen sich in die vorhandene Fehlerliste ein, statt einen zweiten
        // Fehlerkanal aufzumachen.
        if ($phase !== null && MorphMap::is($protocol->subject_type, DiaryEntry::class)) {
            $entry = DiaryEntry::query()->find($protocol->subject_id);
            if ($entry instanceof DiaryEntry) {
                try {
                    $this->requirements->assertSatisfied($entry, $phase);
                } catch (ClassificationRequirementException $exception) {
                    $errors = [...$errors, ...$exception->messages()];
                }
            }
        }

        if ($errors !== []) {
            throw new ProtocolValidationException($errors);
        }
    }

    private function parseItemType(string $value): ProtocolItemType {
        $type = ProtocolItemType::tryFrom($value);
        if (! $type instanceof ProtocolItemType) {
            throw new InvalidArgumentException(sprintf('Unbekannter Item-Typ „%s".', $value));
        }
        return $type;
    }

    private function mapDefectSeverity(string $severity): string {
        return match ($severity) {
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'critical' => 'critical',
            default => 'low',
        };
    }

    /**
     * Liest einen String-Wert aus einem Attribut-Array; fehlende/null-Werte
     * fallen auf den Default zurück, Nicht-Strings werden früh abgewiesen
     * (die aufrufenden Requests validieren diese Felder als String).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function stringAttribute(array $attributes, string $key, string $default): string {
        $value = $attributes[$key] ?? $default;
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Attribut „%s" muss ein String sein.', $key));
        }

        return $value;
    }

    /**
     * Verengt einen Attributwert auf einen Carbon-Zeitpunkt; null bleibt
     * null (Default setzt der Aufrufer), unparsebare Typen werden abgewiesen.
     */
    private function dateAttribute(mixed $value, string $key): ?Carbon {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface || is_string($value) || is_int($value) || is_float($value)) {
            return Carbon::parse($value);
        }

        throw new InvalidArgumentException(sprintf('Attribut „%s" ist kein gültiger Zeitpunkt.', $key));
    }

    /**
     * Verengt den übergebenen value_json-Wert auf das im Modell garantierte
     * string-indizierte Array — alle Item-Typen arbeiten auf oberster Ebene
     * mit benannten Schlüsseln; alles andere wird früh abgewiesen.
     *
     * @return array<string, mixed>
     */
    private function valueJsonFrom(mixed $value): array {
        if (! is_array($value)) {
            throw new InvalidArgumentException('value_json muss ein Array sein.');
        }

        $result = [];
        foreach ($value as $key => $entry) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('value_json muss string-indizierte Schlüssel haben.');
            }
            $result[$key] = $entry;
        }

        return $result;
    }

    private function protocolOf(ProtocolItem $item): Protocol {
        $protocol = $item->protocol ?? Protocol::query()->findOrFail($item->protocol_id);
        return $protocol;
    }

    private function assertActionAllowed(Protocol $protocol, string $action): void {
        if (! in_array($action, $protocol->status->allowedActions(), true)) {
            throw InvalidProtocolTransitionException::from($protocol->status, $action);
        }
    }

    /**
     * Erlaubt anderen Protokoll-Services (z. B. ProtocolItemPhotoService),
     * Audit-Eintraege ueber dieselbe Pipeline zu loggen.
     *
     * @param  array<string, mixed>  $payload
     */
    public function logEvent(Protocol $protocol, string $event, User $actor, array $payload = []): void {
        $this->record($protocol, $event, $actor, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(Protocol $protocol, string $event, User $actor, array $payload = []): void {
        $protocol->record($event, $payload, $actor);
    }

    private function parseType(string $value): ProtocolType {
        $type = ProtocolType::tryFrom($value);
        if (! $type instanceof ProtocolType) {
            throw new InvalidArgumentException(sprintf('Unbekannter Protokolltyp „%s".', $value));
        }

        return $type;
    }

    private function parseVisibility(string $value): ProtocolVisibility {
        $visibility = ProtocolVisibility::tryFrom($value);
        if (! $visibility instanceof ProtocolVisibility) {
            throw new InvalidArgumentException(sprintf('Unbekannte Sichtbarkeit „%s".', $value));
        }

        return $visibility;
    }

    private function parseRole(string $value): ProtocolSignatureRole {
        $role = ProtocolSignatureRole::tryFrom($value);
        if (! $role instanceof ProtocolSignatureRole) {
            throw new InvalidArgumentException(sprintf('Unbekannte Signatur-Rolle „%s".', $value));
        }

        return $role;
    }

    private function parseMethod(string $value): ProtocolSignatureMethod {
        $method = ProtocolSignatureMethod::tryFrom($value);
        if (! $method instanceof ProtocolSignatureMethod) {
            throw new InvalidArgumentException(sprintf('Unbekannte Signatur-Methode „%s".', $value));
        }

        return $method;
    }
}
