<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevClientDirectory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Services;

use App\Plugins\DatevOnline\Api\DatevOnlineClientFactory;
use App\Plugins\DatevOnline\Models\DatevOnlineConnection;
use Datev\API\Online\Endpoints\AccountingClients\ClientsEndpoint;
use Datev\API\Online\OnlineService;

/** Mandanten, auf die der angemeldete DATEV-Benutzer zugreifen darf. */
class DatevClientDirectory {
    private const MAX_PAGES = 10;

    public function __construct(private readonly DatevOnlineClientFactory $clients) {}

    /** @return list<array{id: string, name: string, services: list<string>}> */
    public function list(DatevOnlineConnection $connection): array {
        $endpoint = new ClientsEndpoint($this->clients->for($connection, OnlineService::AccountingClients));
        $result = [];
        foreach ($endpoint->searchAll([], [], self::MAX_PAGES) as $client) {
            if ($client->getConsultantNumber() === null || $client->getClientNumber() === null) {
                continue;
            }
            $services = [];
            foreach ($client->getServices() ?? [] as $service) {
                if ($service->getName() !== null) {
                    $services[] = $service->getName();
                }
            }
            $result[] = [
                // Verbundnummer — die client-id der Beleg- und EXTF-Dienste.
                'id' => $client->getConsultantNumber() . '-' . $client->getClientNumber(),
                'name' => (string) $client->getName(),
                'services' => $services,
            ];
        }

        return $result;
    }
}
