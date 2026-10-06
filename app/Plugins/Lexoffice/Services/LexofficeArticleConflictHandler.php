<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeArticleConflictHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\Integration\PendingExternalConflict;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Services\Inventory\Contracts\ArticleConflictHandler;
use App\Support\MorphMap;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * „Lexoffice-Stand übernehmen“ für Artikelkonflikte der Konfliktliste
 * (Entscheidung 2026-10-06): holt den Artikel über {@see LexofficeArticleSync::adoptRemote()}
 * frisch aus Lexoffice. Zugangsdaten gelten je Organisation des Konflikts;
 * der API-Lauf teilt sich die Sperre mit Sync-Kommandos und Jobs.
 */
final class LexofficeArticleConflictHandler implements ArticleConflictHandler {
    public function supports(PendingExternalConflict $conflict): bool {
        return $conflict->plugin_id === LexofficePlugin::ID
            && $conflict->conflict_type === PendingExternalConflict::TYPE_ARTICLE
            && MorphMap::is($conflict->referenceable_type, LexofficeArticle::class);
    }

    public function adoptRemote(PendingExternalConflict $conflict): void {
        $article = LexofficeArticle::query()->withoutGlobalScopes()
            ->whereKey($conflict->referenceable_id)
            ->where('organization_id', $conflict->organization_id)
            ->first();
        if (! $article instanceof LexofficeArticle) {
            throw new RuntimeException((string) __('Der Artikel wurde lokal nicht gefunden.'));
        }

        $config = LexofficeConfig::resolve($conflict->organization_id);
        if (! is_string($config['api_key']) || $config['api_key'] === '') {
            throw new RuntimeException((string) __('Lexoffice ist für diese Organisation nicht konfiguriert.'));
        }

        try {
            Cache::lock(LexofficeConfig::apiLockKey((int) $conflict->organization_id), 60)
                ->block(LexofficeConfig::API_LOCK_WAIT_SHORT, static fn (): LexofficeArticle => (new LexofficeArticleSync($config['api_key'], $config['base_url'], $config['request_interval']))->adoptRemote($article));
        } catch (LockTimeoutException) {
            throw new RuntimeException((string) __('Lexoffice wird gerade von einem anderen Lauf abgeglichen. Bitte versuchen Sie es in einigen Minuten erneut.'));
        }
    }
}
