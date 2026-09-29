<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RepointMergedServiceArticles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Invoicing;

use App\Events\Article\ArticlesMerged;
use App\Listeners\ModuleListener;
use App\Models\Platform\Organization;
use App\Services\Article\Catalog\LocalArticleCatalogSource;
use App\Services\Platform\Catalog\ArticleCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Standardleistungen folgen dem zusammengeführten Artikel (MVP-1026):
 * Projekt-Abrechnungsregeln, noch vorbereitete Übergabe-Positionen und die
 * Standardleistung der Organisation.
 */
final class RepointMergedServiceArticles extends ModuleListener {
    protected function module(): string {
        return 'invoicing';
    }

    public function handle(ArticlesMerged $event): void {
        $source = ArticleCatalog::key(LocalArticleCatalogSource::PREFIX, $event->sourceId);
        $target = ArticleCatalog::key(LocalArticleCatalogSource::PREFIX, $event->targetId);

        foreach (['project_billing_rules', 'billing_transfer_positions'] as $table) {
            DB::table($table)->where('organization_id', $event->organizationId)->where('article_ref', $source)->update(['article_ref' => $target]);
        }

        $organization = Organization::query()->withoutGlobalScopes()->find($event->organizationId);
        if ($organization instanceof Organization && data_get($organization->settings, 'invoicing.default_service_article') === $source) {
            $settings = (array) $organization->settings;
            data_set($settings, 'invoicing.default_service_article', $target);
            $organization->forceFill(['settings' => $settings])->save();
        }
    }
}
