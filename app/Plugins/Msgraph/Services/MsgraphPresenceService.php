<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphPresenceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Models\{MsgraphConnection, Organization, User};
use App\Plugins\Msgraph\Api\MsgraphCalendarClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Teams-Presence fürs Team-Panel der Anwesenheitsseite (Feature 102, F):
 * läuft über die KALENDER-Verbindung der Organisation — Voraussetzung ist,
 * dass `MSGRAPH_SCOPES` um `Presence.Read.All User.ReadBasic.All` erweitert
 * und die Verbindung danach neu autorisiert wurde (granted scopes am
 * Verbindungsdatensatz). Ohne Scope bleibt das Feature still aus.
 *
 * Caching (Graph-Limit 1.500 Requests/30 s je App+Tenant):
 * E-Mail→AAD-ID 24 h je Org; Presence-Batch 60 s je Org.
 */
class MsgraphPresenceService {
    private const PRESENCE_TTL_SECONDS = 60;

    private const ID_TTL_SECONDS = 86400;

    /**
     * Presence je E-Mail (lowercase) — leer, wenn Verbindung/Scope fehlen.
     *
     * @param  Collection<int, User>  $users
     * @return array<string, string> email → availability (Available/Busy/Away/…)
     */
    public function presenceForUsers(Organization $organization, Collection $users): array {
        $connection = MsgraphConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->first();
        if (! $connection instanceof MsgraphConnection || ! $connection->isActive()) {
            return [];
        }
        if (! str_contains((string) $connection->scopes, 'Presence.Read.All')) {
            return []; // Scope nicht erteilt → Feature still aus
        }

        $emails = $users->map(fn (User $u): string => strtolower(trim((string) $u->email)))
            ->filter()
            ->unique()
            ->values();
        if ($emails->isEmpty()) {
            return [];
        }

        $cacheKey = 'msgraph:presence:' . $organization->id . ':' . \CommonToolkit\Helper\Data\CryptoHelper::hash($emails->implode(','), \CommonToolkit\Enums\HashAlgorithm::SHA1);

        /** @var array<string, string> $cached */
        $cached = Cache::remember($cacheKey, self::PRESENCE_TTL_SECONDS, function () use ($organization, $connection, $emails): array {
            try {
                $client = new MsgraphCalendarClient($connection);

                // E-Mail → AAD-ID (24-h-Cache je Adresse).
                $idByEmail = [];
                foreach ($emails as $email) {
                    $id = (string) Cache::remember(
                        'msgraph:aadid:' . $organization->id . ':' . \CommonToolkit\Helper\Data\CryptoHelper::hash($email, \CommonToolkit\Enums\HashAlgorithm::SHA1),
                        self::ID_TTL_SECONDS,
                        fn (): string => (string) ($client->userIdByEmail($email) ?? ''),
                    );
                    if ($id !== '') {
                        $idByEmail[$email] = $id;
                    }
                }
                if ($idByEmail === []) {
                    return [];
                }

                $presences = $client->presencesByUserIds(array_values($idByEmail));

                $out = [];
                foreach ($idByEmail as $email => $id) {
                    if (isset($presences[$id])) {
                        $out[$email] = $presences[$id];
                    }
                }

                return $out;
            } catch (Throwable) {
                return [];
            }
        });

        return $cached;
    }
}
