<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeyHandoverService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\KeyHandover;

use App\Enums\KeyHandover\KeyHandoverDirection;
use App\Models\{Asset, KeyHandover, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KeyHandoverService {
    use \App\Services\Concerns\ParsesMixedDate;

    /** @param array<string, mixed> $payload */
    public function record(Asset $asset, User $actor, array $payload): KeyHandover {
        $direction = $this->parseDirection((string) ($payload['direction'] ?? KeyHandoverDirection::Out->value));
        $occurredAt = $this->parseDate($payload['occurred_at'] ?? null) ?? Carbon::now();
        $personName = trim((string) ($payload['person_name'] ?? ''));
        if ($personName === '') {
            throw new InvalidArgumentException('person_name is required');
        }

        $handover = new KeyHandover([
            'organization_id' => $asset->organization_id,
            'asset_id' => $asset->id,
            'customer_id' => isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
            'direction' => $direction->value,
            'person_name' => $personName,
            'person_reference' => $payload['person_reference'] ?? null,
            'handed_by_user_id' => $direction === KeyHandoverDirection::Out ? $actor->id : null,
            'returned_to_user_id' => $direction === KeyHandoverDirection::In ? $actor->id : null,
            'occurred_at' => $occurredAt,
            'expected_return_at' => $this->parseDate($payload['expected_return_at'] ?? null),
            'notes' => $payload['notes'] ?? null,
            'signature_token' => $payload['signature_token'] ?? null,
        ]);

        DB::transaction(function () use ($handover): void {
            $handover->save();
        });

        $handover->audit('key_handover.recorded', [
            'asset_id' => $handover->asset_id,
            'direction' => $handover->direction->value,
            'person_name' => $handover->person_name,
        ]);

        return $handover->refresh();
    }

    public function currentHolder(Asset $asset): ?KeyHandover {
        $latestOut = KeyHandover::query()
            ->where('asset_id', $asset->id)
            ->where('direction', KeyHandoverDirection::Out->value)
            ->orderByDesc('occurred_at')
            ->first();

        if ($latestOut === null) {
            return null;
        }

        $laterReturn = KeyHandover::query()
            ->where('asset_id', $asset->id)
            ->where('direction', KeyHandoverDirection::In->value)
            ->where('occurred_at', '>=', $latestOut->occurred_at)
            ->exists();

        return $laterReturn ? null : $latestOut;
    }

    private function parseDirection(string $value): KeyHandoverDirection {
        return KeyHandoverDirection::tryFrom($value)
            ?? throw new InvalidArgumentException('Unknown handover direction: ' . $value);
    }

}
