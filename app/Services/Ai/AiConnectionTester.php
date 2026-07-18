<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiConnectionTester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\AiConnectionStatus;
use App\Models\Ai\AiProviderConnection;
use App\Services\Ai\Exceptions\AiException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Preflight einer KI-Provider-Verbindung (Feature 025, MVP-399/400):
 * billigster Testaufruf über den Adapter. Erfolg aktiviert die
 * Verbindung (Draft → Active, `preflight_at`); Fehlschlag bleibt im
 * bisherigen Status und wird redigiert im Health-Tracking vermerkt.
 * Bewusst gesperrte Verbindungen (Blocked) werden nie automatisch
 * reaktiviert.
 */
class AiConnectionTester {
    public function __construct(private readonly AiProviderFactory $providers) {}

    public function test(AiProviderConnection $connection): bool {
        try {
            $this->providers->make($connection)->preflight();
        } catch (Throwable $e) {
            $connection->recordConnectionFailure(AiException::describe($e));

            return false;
        }

        $connection->recordConnectionSuccess();

        $updates = ['preflight_at' => Carbon::now()];
        if ($connection->status === AiConnectionStatus::Draft) {
            $updates['status'] = AiConnectionStatus::Active;
        }
        $connection->forceFill($updates)->save();

        return true;
    }
}
