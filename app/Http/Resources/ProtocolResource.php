<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\{Protocol, ProtocolItem, ProtocolSignature};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only-Darstellung eines Protokolls (MVP-718). Signaturen nur mit
 * Rolle/Name/Methode/Zeitpunkt — KEINE Token-, IP-, User-Agent-, Hash- oder
 * Bildpfad-Interna; Signatur-Token (Fernsignatur) erscheinen gar nicht.
 *
 * @mixin Protocol
 */
class ProtocolResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        $subjectClass = $this->subject_type;

        return [
            'id' => $this->sqid,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'title' => $this->title,
            'description' => $this->description,
            'subject' => [
                'type' => class_basename($this->subject_type),
                'id' => class_exists($subjectClass) ? Sqid::encode($subjectClass, $this->subject_id) : null,
            ],
            'revision' => (int) $this->revision,
            'supersedes_id' => Sqid::encodeOrNull(Protocol::class, $this->supersedes_id),
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn(): array => $this->items->map(static fn(ProtocolItem $item): array => [
                'id' => $item->sqid,
                'sort_order' => (int) $item->sort_order,
                'label' => $item->label,
                'item_type' => $item->item_type->value,
                'required' => (bool) $item->required,
                'result' => $item->result?->value,
                'value' => $item->value_json,
                'note' => $item->note,
                'measured_at' => $item->measured_at?->toIso8601String(),
            ])->all()),
            'signatures' => $this->whenLoaded('signatures', fn(): array => $this->signatures->map(static fn(ProtocolSignature $signature): array => [
                'role' => $signature->role->value,
                'signer_name' => $signature->signer_name,
                'method' => $signature->method->value,
                'signed_at' => $signature->signed_at->toIso8601String(),
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
