<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RepointMergedArticleRefs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Reselling;

use App\Events\Article\ArticlesMerged;
use App\Listeners\ModuleListener;
use App\Services\Article\Catalog\LocalArticleCatalogSource;
use App\Services\Platform\Catalog\ArticleCatalog;
use App\Services\Reselling\Register\LicenseArticleClassifier;
use Illuminate\Support\Facades\DB;

/**
 * Abos, Lizenzprodukte und Einstufungen folgen dem zusammengeführten Artikel
 * (MVP-1025). Ohne Lizenzprüfung: die Daten bestehen auch bei ruhendem Modul
 * und dürfen nicht auf einen gelöschten Artikel zeigen.
 */
final class RepointMergedArticleRefs extends ModuleListener {
    protected function module(): string {
        return 'reselling';
    }

    public function handle(ArticlesMerged $event): void {
        $source = ArticleCatalog::key(LocalArticleCatalogSource::PREFIX, $event->sourceId);
        $target = ArticleCatalog::key(LocalArticleCatalogSource::PREFIX, $event->targetId);

        foreach (['resale_subscriptions', 'resale_license_products'] as $table) {
            DB::table($table)->where('organization_id', $event->organizationId)->where('article_ref', $source)->update(['article_ref' => $target]);
        }

        // Einstufung: die des Ziels gilt; nur ohne sie wandert die der Quelle.
        $classifications = DB::table('resale_article_classifications')->where('organization_id', $event->organizationId);
        if ((clone $classifications)->where('article_ref', $target)->exists()) {
            (clone $classifications)->where('article_ref', $source)->delete();
        } else {
            (clone $classifications)->where('article_ref', $source)->update(['article_ref' => $target]);
        }
        app(LicenseArticleClassifier::class)->flush();
    }
}
