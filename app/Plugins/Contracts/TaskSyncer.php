<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskSyncer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\Organization;

/**
 * Providerneutraler Aufgaben-Sync-Vertrag (Feature 055, MVP-111): ein Plugin
 * mit {@see PluginCapability::TaskSync} gleicht Aufgaben zwischen WorkDiary
 * und einem externen Aufgabensystem ab — nur ausdrücklich zugeordnete
 * Projekte, über `ExternalReference` + Integrations-Inbox (MVP-103), nie per
 * Last-write-wins. Der Zuschnitt ist bewusst schmal: Zuordnungs-/Preflight-/
 * Webhook-Details bleiben pluginspezifisch.
 */
interface TaskSyncer {
    /**
     * Führt den Aufgaben-Abgleich aller aktiven Projektzuordnungen der
     * Organisation aus (idempotent; wiederholte Läufe erzeugen keine
     * Dubletten).
     *
     * @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}
     */
    public function syncTasks(Organization $organization): array;
}
