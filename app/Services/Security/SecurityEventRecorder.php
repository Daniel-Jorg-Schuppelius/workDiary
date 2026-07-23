<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityEventRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Request;

/**
 * Selektive Persistenz der Security-Events (Feature 096, MVP-445):
 * speist Schwellwert-Alarme + Admin-Dashboard. Datensparsam — nur die
 * alarm-relevanten Kontextfelder, Werte gedeckelt; das Voll-Log lebt in
 * der rotierten security.log ({@see SecurityEventLogger}).
 */
class SecurityEventRecorder {
    /** @param array<string, scalar|null> $context */
    public static function record(SecurityEventType $type, array $context): void {
        $meta = [];
        foreach ($context as $key => $value) {
            if (in_array($key, ['ip', 'user_id', 'organization_id'], true) || $value === null || $value === '') {
                continue;
            }
            $meta[$key] = mb_substr((string) $value, 0, 200);
        }

        SecurityEvent::query()->create([
            'event' => $type,
            'ip' => (string) ($context['ip'] ?? Request::ip() ?? '') ?: null,
            'user_id' => isset($context['user_id']) ? (int) $context['user_id'] : null,
            'organization_id' => isset($context['organization_id']) ? (int) $context['organization_id'] : null,
            'meta' => $meta !== [] ? $meta : null,
            'occurred_at' => now(),
        ]);
    }
}
