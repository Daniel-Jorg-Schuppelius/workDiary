<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorTargetRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

use App\Models\{Document, Invoice, Protocol};
use App\Plugins\Support\Mirror\Observers\{MirrorDocumentObserver, MirrorInvoiceObserver, MirrorProtocolObserver};

/**
 * Registry der Ablage-Ziele (MVP-330, Bauturbo A10). Container-Singleton;
 * jedes Mirror-Plugin registriert sein {@see MirrorTarget} im ServiceProvider.
 * Die GEMEINSAMEN Freigabe-Observer (Dokument aktiv / Rechnung gestellt /
 * Protokoll signiert → Outbox) werden beim ERSTEN register() genau einmal an
 * die Modelle gehängt — sie iterieren dann über alle Ziele, damit mehrere
 * Ablagen (WebDAV + SharePoint) nebeneinander gespiegelt werden können.
 */
class MirrorTargetRegistry {
    /** @var array<string, MirrorTarget> */
    private array $targets = [];

    private bool $observersAttached = false;

    public function register(MirrorTarget $target): void {
        $this->targets[$target->pluginId()] = $target;

        if (! $this->observersAttached) {
            Document::observe(MirrorDocumentObserver::class);
            Invoice::observe(MirrorInvoiceObserver::class);
            Protocol::observe(MirrorProtocolObserver::class);
            $this->observersAttached = true;
        }
    }

    /** @return array<string, MirrorTarget> */
    public function all(): array {
        return $this->targets;
    }

    public function get(string $pluginId): ?MirrorTarget {
        return $this->targets[$pluginId] ?? null;
    }
}
