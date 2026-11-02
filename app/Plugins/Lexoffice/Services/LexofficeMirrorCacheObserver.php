<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMirrorCacheObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\Integration\ExternalReference;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Register\{ResaleCacheStamp, ResaleRequestMemo};
use Illuminate\Database\Eloquent\Model;

/**
 * Gespiegelte Belege, Positionen, Serienvorlagen oder Kontaktverknüpfungen
 * haben sich geändert (Leistungsanalyse 2026-11-02): das Request-Memo des
 * Registers vergisst Spiegelzeilen, Vorlagen und Kontaktkarte, der Org-Stempel
 * verwirft Übersicht, Zähler und Belegtexte — Sync und Lesen im selben Prozess
 * (Kommando, Test) bleiben stimmig. Massen-Updates per Abfrage (Archivieren)
 * laufen über die TTL.
 */
class LexofficeMirrorCacheObserver {
    public function saved(Model $model): void {
        $this->changed($model);
    }

    public function deleted(Model $model): void {
        $this->changed($model);
    }

    private function changed(Model $model): void {
        if ($model instanceof ExternalReference && ((string) $model->plugin_id !== LexofficePlugin::ID || (string) $model->external_type !== LexofficePlugin::EXT_TYPE_CONTACT)) {
            return;
        }
        $memo = app(ResaleRequestMemo::class);
        $memo->forget('mirror-lines');
        $memo->forget('plan-templates');
        $memo->forget('lexoffice-contact-map');
        $organizationId = $model->getAttribute('organization_id');
        if (is_numeric($organizationId)) {
            ResaleCacheStamp::bump((int) $organizationId);
        }
    }
}
