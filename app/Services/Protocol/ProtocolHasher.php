<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolHasher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Protocol;

use App\Models\Protocol;
use CommonToolkit\Helper\Data\JsonHelper;

/**
 * Reproduzierbarer Inhalts-Hash eines Protokolls (MVP-022 §4).
 *
 * Die Serialisierung ist kanonisch (Schluessel alphabetisch sortiert,
 * keine Whitespaces), so dass derselbe Protokollinhalt deterministisch
 * denselben SHA-256-Hash erzeugt — auch ueber Prozesse / Server hinweg.
 */
class ProtocolHasher {
    /**
     * Liefert die kanonische JSON-Repraesentation des Protokoll-Inhalts.
     */
    public function canonicalize(Protocol $protocol): string {
        $protocol->loadMissing(['items']);

        $payload = [
            'description' => $protocol->description,
            'occurred_at' => $protocol->occurred_at->toIso8601String(),
            'revision' => $protocol->revision,
            'state_final' => $protocol->state_final,
            'state_initial' => $protocol->state_initial,
            'subject_id' => $protocol->subject_id,
            'subject_type' => $protocol->subject_type,
            'title' => $protocol->title,
            'type' => $protocol->type->value,
            'items' => $protocol->items
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($item) => [
                    'description' => $item->description,
                    'item_type' => $item->item_type->value,
                    'label' => $item->label,
                    'note' => $item->note,
                    'required' => (bool) $item->required,
                    'result' => $item->result?->value,
                    'sort_order' => $item->sort_order,
                    'value_json' => $item->value_json,
                ])
                ->all(),
        ];

        return $this->encodeCanonical($payload);
    }

    /**
     * SHA-256-Hash über kanonisierten Inhalt + Signer-Daten.
     */
    public function contentHash(Protocol $protocol, string $signerName, string $role, string $signedAtIso): string {
        return hash('sha256', implode('|', [
            $this->canonicalize($protocol),
            $signerName,
            $role,
            $signedAtIso,
        ]));
    }

    /**
     * @param  mixed  $value
     */
    private function encodeCanonical($value): string {
        if (is_array($value)) {
            if ($this->isList($value)) {
                $parts = array_map(fn ($v) => $this->encodeCanonical($v), $value);
                return '[' . implode(',', $parts) . ']';
            }
            ksort($value);
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = JsonHelper::encode((string) $k, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ':' . $this->encodeCanonical($v);
            }
            return '{' . implode(',', $parts) . '}';
        }
        return JsonHelper::encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<mixed, mixed>  $arr
     */
    private function isList(array $arr): bool {
        return $arr === [] || array_keys($arr) === range(0, count($arr) - 1);
    }
}
