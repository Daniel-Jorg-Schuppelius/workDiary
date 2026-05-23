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

use App\Enums\Protocol\ProtocolEventType;
use App\Enums\Protocol\ProtocolItemResult;
use App\Enums\Protocol\ProtocolSignatureMethod;
use App\Enums\Protocol\ProtocolSignatureRole;
use App\Enums\Protocol\ProtocolStatus;
use App\Enums\Protocol\ProtocolType;
use App\Enums\Protocol\ProtocolVisibility;
use App\Exceptions\InvalidProtocolTransitionException;
use App\Models\Protocol;
use App\Models\ProtocolEvent;
use App\Models\ProtocolItem;
use App\Models\ProtocolSignature;
use App\Models\User;
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
    /**
     * Legt ein neues Protokoll im Status `draft` an.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Model $subject, User $creator, array $attributes): Protocol {
        $type = $this->parseType($attributes['type'] ?? ProtocolType::Service->value);
        $visibility = $this->parseVisibility(
            $attributes['visibility'] ?? ProtocolVisibility::Internal->value
        );

        $occurredAt = isset($attributes['occurred_at'])
            ? Carbon::parse($attributes['occurred_at'])
            : Carbon::now();

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

            return $protocol->refresh();
        });
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

        $item = $protocol->items()->create([
            'parent_item_id' => $attributes['parent_item_id'] ?? null,
            'sort_order' => $attributes['sort_order'] ?? ($protocol->items()->max('sort_order') + 1),
            'item_type' => $attributes['item_type'] ?? 'checklist',
            'label' => $attributes['label'],
            'description' => $attributes['description'] ?? null,
            'required' => (bool) ($attributes['required'] ?? false),
        ]);

        $this->record($protocol, ProtocolEventType::ItemAdded, $actor, [
            'item_id' => $item->id,
            'label' => $item->label,
        ]);

        return $item;
    }

    public function removeItem(ProtocolItem $item, User $actor): void {
        $protocol = $item->protocol;
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
        $this->assertEditable($item->protocol);

        $result = isset($values['result'])
            ? ProtocolItemResult::tryFrom((string) $values['result'])
            : $item->result;

        $item->update([
            'value_json' => $values['value_json'] ?? $item->value_json,
            'result' => $result?->value,
            'note' => $values['note'] ?? $item->note,
            'measured_at' => Carbon::now(),
            'measured_by_user_id' => $actor->id,
        ]);

        $this->record($item->protocol, ProtocolEventType::ItemFilled, $actor, [
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
        $role = $this->parseRole($data['role'] ?? ProtocolSignatureRole::Contractor->value);
        $method = $this->parseMethod($data['method'] ?? ProtocolSignatureMethod::Onscreen->value);

        $signerName = (string) ($data['signer_name'] ?? $actor->name);
        $signedAt = isset($data['signed_at']) ? Carbon::parse($data['signed_at']) : Carbon::now();
        $imagePath = $data['signature_image_path'] ?? null;

        $hash = hash('sha256', implode('|', [
            $protocol->id,
            $role->value,
            $signerName,
            $signedAt->toIso8601String(),
            (string) $imagePath,
        ]));

        return ProtocolSignature::query()->create([
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
    }

    // ----- Helpers -----

    private function assertEditable(Protocol $protocol): void {
        if (! $protocol->status->isEditable()) {
            throw InvalidProtocolTransitionException::immutable($protocol->status);
        }
    }

    private function assertTransition(Protocol $protocol, string $action): void {
        if (! in_array($action, $protocol->status->allowedTransitions(), true)) {
            throw InvalidProtocolTransitionException::from($protocol->status, $action);
        }
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
