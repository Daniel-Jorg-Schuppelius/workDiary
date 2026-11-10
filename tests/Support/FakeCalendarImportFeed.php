<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeCalendarImportFeed.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Platform\Organization;
use App\Modules\ModuleRegistry;
use App\Services\Import\Contracts\CalendarImportFeed;
use DateTimeImmutable;
use Throwable;

/**
 * Kalenderverbindung für Tests des Zeitimports und des Kalender-Abos
 * (MVP-1531): je Organisation eine Verbindung `team-<org>`, liefert ein
 * einstellbares iCal-Dokument und merkt sich jeden Abruf.
 */
final class FakeCalendarImportFeed implements CalendarImportFeed {
    public string $ics = '';

    public ?Throwable $failure = null;

    /** @var list<array{organization: int, connection: string, from: DateTimeImmutable, until: DateTimeImmutable}> */
    public array $calls = [];

    /** Bindet ein Exemplar und meldet es am Erweiterungspunkt an. */
    public static function register(): self {
        $feed = new self;
        app()->instance(self::class, $feed);
        app(ModuleRegistry::class)->contribute(CalendarImportFeed::class, self::class);

        return $feed;
    }

    public static function source(Organization $organization): string {
        return 'testkalender:team-' . $organization->id;
    }

    public function key(): string {
        return 'testkalender';
    }

    public function label(): string {
        return 'Testkalender';
    }

    public function connections(Organization $organization): array {
        return [['id' => 'team-' . $organization->id, 'label' => 'Team']];
    }

    public function fetch(Organization $organization, string $connectionId, DateTimeImmutable $from, DateTimeImmutable $until): string {
        $this->calls[] = ['organization' => (int) $organization->id, 'connection' => $connectionId, 'from' => $from, 'until' => $until];
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->ics;
    }
}
