<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeArticleConflictHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Integration\PendingExternalConflict;
use App\Services\Inventory\Contracts\ArticleConflictHandler;
use RuntimeException;

/**
 * Beitrag eines Plugins zum Erweiterungspunkt der Konfliktliste — zeichnet
 * auf, welche Konflikte „Stand übernehmen“ erreicht hat, und scheitert auf
 * Wunsch wie ein Fremdsystem, das nicht antwortet.
 */
final class FakeArticleConflictHandler implements ArticleConflictHandler {
    /** @var list<int> */
    public static array $adopted = [];

    public static string $pluginId = 'lexoffice';

    public static ?RuntimeException $failure = null;

    public static function reset(): void {
        self::$adopted = [];
        self::$pluginId = 'lexoffice';
        self::$failure = null;
    }

    public function supports(PendingExternalConflict $conflict): bool {
        return $conflict->plugin_id === self::$pluginId && $conflict->conflict_type === PendingExternalConflict::TYPE_ARTICLE;
    }

    public function adoptRemote(PendingExternalConflict $conflict): void {
        if (self::$failure !== null) {
            throw self::$failure;
        }
        self::$adopted[] = (int) $conflict->getKey();
    }
}
