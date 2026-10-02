<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavImportFeed.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Models\Platform\Organization;
use App\Plugins\CalDav\Contracts\CalDavGatewayFactory;
use App\Plugins\CalDav\Models\CalDavConnection;
use App\Plugins\Support\Calendar\CalendarImportDocument;
use App\Services\Import\Contracts\CalendarImportFeed;
use App\Support\Sqid;
use DateTimeImmutable;
use RuntimeException;

/** CalDAV-Kalender als Quelle des Zeitimports (MVP-976): Objekte des Zeitraums per `calendar-query`. */
final class CalDavImportFeed implements CalendarImportFeed {
    public function __construct(
        private readonly CalDavGatewayFactory $gateways,
        private readonly CalendarImportDocument $document,
    ) {}

    public function key(): string {
        return 'caldav';
    }

    public function label(): string {
        return 'CalDAV';
    }

    public function connections(Organization $organization): array {
        return array_values(CalDavConnection::query()->where('organization_id', $organization->id)->where('active', true)->orderBy('name')->get()
            ->map(static fn (CalDavConnection $connection): array => ['id' => Sqid::encode(CalDavConnection::class, $connection->id), 'label' => (string) $connection->name])
            ->all());
    }

    public function fetch(Organization $organization, string $connectionId, DateTimeImmutable $from, DateTimeImmutable $until): string {
        $connection = CalDavConnection::query()->where('organization_id', $organization->id)->where('active', true)
            ->whereKey(Sqid::decode(CalDavConnection::class, $connectionId))->first()
            ?? throw new RuntimeException((string) __('import.error.calendar.unknown'));

        return $this->document->merge($this->gateways->for($connection)->eventsBetween($from, $until));
    }
}
