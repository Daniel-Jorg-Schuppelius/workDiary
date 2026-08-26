<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VoucherPuller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

/**
 * Beleg-Rückabruf eines Buchhaltungs-/Faktura-Systems (Feature 122, MVP-731).
 *
 * Bewusst KEINE {@see \App\Plugins\Contracts\PluginCapability}: die Faktura-
 * Anbindungen (Übergabe, Bestand, Beleg-Pull) laufen im Projekt über eigene
 * Registries statt über Plugin-Klassen-Interfaces (Audit 2026-08, W1.6) —
 * easybill und JTL kündigen deshalb heute schon keine Capabilities an.
 *
 * Ein Puller ist eine reine Leserichtung: gespiegelt wird, nicht übernommen.
 * workDiary schreibt nie in das Fremdsystem zurück und löscht dort nichts.
 */
interface VoucherPuller {
    /** Plugin-ID, unter der die Belege in `accounting_vouchers` landen. */
    public function pluginId(): string;

    /** Ist der Abruf für diese Organisation eingerichtet (Zugang + Schalter)? */
    public function isConfigured(int $organizationId): bool;

    /**
     * Belege abrufen und spiegeln.
     *
     * `$pages` begrenzt den Lauf (Seitenzahl); der Inkrement-Marker
     * (`accounting_vouchers.source_changed_at`) sorgt dafür, dass der nächste
     * Lauf hinter dem letzten Stand weitermacht statt alles neu zu lesen.
     *
     * @return array{read: int, created: int, updated: int, skipped: int}
     */
    public function pull(int $organizationId, int $pages = 2): array;
}
