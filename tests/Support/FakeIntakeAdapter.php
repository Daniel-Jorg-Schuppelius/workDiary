<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeIntakeAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Contracts\DocumentIntakeSource;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeItem};
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Skriptbarer Intake-Adapter für Runner-/Router-Tests (Feature 080):
 * `changes()` liefert die Antworten der Queue in Reihenfolge (Throwable-
 * Einträge werden geworfen), `download()` bedient sich aus einer
 * itemId→Inhalt-Map.
 */
class FakeIntakeAdapter implements DocumentIntakeSource {
    /** @var list<IntakeChangePage|Throwable> */
    public array $pages = [];

    /** @var array<string, string> */
    public array $contents = [];

    /** @var list<string|null> Übergebene Checkpoints (Assertions). */
    public array $seenCheckpoints = [];

    public function intakeAccount(CloudDocumentConnection $connection): IntakeAccount {
        return new IntakeAccount('fake-account', 'Fake <fake@example.test>');
    }

    public function intakeContainers(CloudDocumentConnection $connection): array {
        return [];
    }

    public function intakeChanges(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        $this->seenCheckpoints[] = $checkpoint;

        $next = array_shift($this->pages);
        if ($next === null) {
            return new IntakeChangePage([], [], (string) $checkpoint, false);
        }
        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public function intakeDownload(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        if (! array_key_exists($item->itemId, $this->contents)) {
            throw new RuntimeException('Kein Fake-Inhalt für ' . $item->itemId);
        }

        return Utils::streamFor($this->contents[$item->itemId]);
    }
}
