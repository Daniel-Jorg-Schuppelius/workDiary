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

use App\Enums\Diary\Status as DiaryStatus;
use App\Enums\Protocol\{ProtocolEventType, ProtocolItemResult, ProtocolItemType, ProtocolSignatureMethod, ProtocolSignatureRole, ProtocolStatus, ProtocolType, ProtocolVisibility};
use App\Exceptions\{InvalidProtocolTransitionException, ProtocolValidationException};
use App\Models\{DiaryEntry, Protocol, ProtocolEvent, ProtocolItem, ProtocolSignature, User};
use App\Services\Diary\OrderService;
use App\Services\OpenIssue\OpenIssueService;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    public function __construct(
        private readonly ProtocolItemValidator $itemValidator,
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
                'template_id' => $attributes['template_id'] ?? null,
                'template_version' => $attributes['template_version'] ?? null,
                'subject_type' => $subject::class,
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

            return $protocol->fresh(['events']) ?? $protocol;
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
        $this->assertTransition($protocol, 'requestReview');
        $this->assertProtocolValid($protocol);
        $protocol->update(['status' => ProtocolStatus::InReview->value]);
        $this->record($protocol, ProtocolEventType::RequestedReview, $actor);

        return $protocol->refresh();
    }

    public function returnToDraft(Protocol $protocol, User $actor, ?string $reason = null): Protocol {
        $this->assertTransition($protocol, 'returnToDraft');
        $protocol->update(['status' => ProtocolStatus::Draft->value]);
        $this->record($protocol, ProtocolEventType::ReturnedToDraft, $actor, [
            'reason' => $reason,
        ]);

        return $protocol->refresh();
    }

    /**
     * Schliesst die Erstellungsphase ab und setzt das Protokoll auf `signed`.
     * Optional kann gleichzeitig die erste Signatur erfasst werden.
     *
     * @param  array<string, mixed>|null  $signatureData
     */
    public function sign(Protocol $protocol, User $actor, ?array $signatureData = null): Protocol {
        $this->assertTransition($protocol, $protocol->status === ProtocolStatus::Draft ? 'signDirect' : 'sign');
        $this->assertProtocolValid($protocol);

        return DB::transaction(function () use ($protocol, $actor, $signatureData): Protocol {
            $protocol->update([
                'status' => ProtocolStatus::Signed->value,
                'signed_at' => Carbon::now(),
            ]);

            if ($signatureData !== null) {
                $this->addSignature($protocol, $actor, $signatureData);
            }

            $this->record($protocol, ProtocolEventType::Signed, $actor, [
                'with_signature' => $signatureData !== null,
            ]);

            $subject = $protocol->subject;
            if ($subject instanceof DiaryEntry && $subject->status === DiaryStatus::Completed) {
                app(OrderService::class)->handover($subject, $actor, $protocol->refresh());
            }

            $this->renderPdfFor($protocol->refresh(), $actor);

            return $protocol->refresh();
        });
    }

    /**
     * Erzeugt das PDF (idempotent) und vermerkt den Event mit Pfad + Hash.
     */
    public function renderPdfFor(Protocol $protocol, User $actor): string {
        $start = microtime(true);
        $path = $this->pdfRenderer->render($protocol);
        $this->record($protocol, ProtocolEventType::PdfRendered, $actor, [
            'path' => $path,
            'hash' => $this->pdfRenderer->hashFor($protocol),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
        return $path;
    }

    public function recordPdfDownload(Protocol $protocol, User $actor): void {
        $this->record($protocol, ProtocolEventType::PdfDownloaded, $actor, [
            'hash' => $this->pdfRenderer->hashFor($protocol),
        ]);
    }

    public function archive(Protocol $protocol, User $actor): Protocol {
        $this->assertTransition($protocol, 'archive');
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
        $this->assertTransition($protocol, 'supersede');

        return DB::transaction(function () use ($protocol, $actor, $reason): Protocol {
            $copy = Protocol::query()->create([
                'organization_id' => $protocol->organization_id,
                'type' => $protocol->type->value,
                'template_id' => $protocol->template_id,
                'template_version' => $protocol->template_version,
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

        $provided = $values['value_json'] ?? null;
        $newValue = $provided !== null ? $this->valueJsonFrom($provided) : $item->value_json;

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
     * @param  list<int>  $orderedIds
     */
    public function reorderItems(Protocol $protocol, User $actor, array $orderedIds): void {
        $this->assertEditable($protocol);

        DB::transaction(function () use ($protocol, $orderedIds): void {
            foreach ($orderedIds as $index => $itemId) {
                $protocol->items()
                    ->whereKey($itemId)
                    ->update(['sort_order' => $index]);
            }
        });

        $this->record($protocol, ProtocolEventType::ItemReordered, $actor, [
            'order' => $orderedIds,
        ]);
    }

    // ----- Signatures -----

    /**
     * @param  array<string, mixed>  $data
     */
    public function addSignature(Protocol $protocol, User $actor, array $data): ProtocolSignature {
        $role = $this->parseRole($this->stringAttribute($data, 'role', ProtocolSignatureRole::Contractor->value));
        $method = $this->parseMethod($this->stringAttribute($data, 'method', ProtocolSignatureMethod::Onscreen->value));

        $signerNameRaw = $data['signer_name'] ?? null;
        $signerName = is_scalar($signerNameRaw) ? (string) $signerNameRaw : $actor->name;
        $signedAt = $this->dateAttribute($data['signed_at'] ?? null, 'signed_at') ?? Carbon::now();
        $imagePath = $data['signature_image_path'] ?? null;

        $hash = $this->hasher->contentHash(
            $protocol,
            $signerName,
            $role->value,
            $signedAt->toIso8601String(),
        );

        $signature = ProtocolSignature::query()->create([
            'protocol_id' => $protocol->id,
            'role' => $role->value,
            'signer_name' => $signerName,
            'signer_email' => $data['signer_email'] ?? null,
            'signer_contact_id' => $data['signer_contact_id'] ?? null,
            'signed_at' => $signedAt,
            'method' => $method->value,
            'signature_image_path' => $imagePath,
            'ip' => $data['ip'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'hash' => $hash,
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
    }

    // ----- Helpers -----

    private function assertEditable(Protocol $protocol): void {
        if (! $protocol->status->isEditable()) {
            throw InvalidProtocolTransitionException::immutable($protocol->status);
        }
    }

    private function assertProtocolValid(Protocol $protocol): void {
        $errors = $this->itemValidator->validateProtocol($protocol->load('items'));
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

    private function assertTransition(Protocol $protocol, string $action): void {
        if (! in_array($action, $protocol->status->allowedTransitions(), true)) {
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
        ProtocolEvent::query()->create([
            'protocol_id' => $protocol->id,
            'event' => $event,
            'actor_user_id' => $actor->id,
            'payload' => $payload !== [] ? $payload : null,
            'created_at' => Carbon::now(),
        ]);
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
