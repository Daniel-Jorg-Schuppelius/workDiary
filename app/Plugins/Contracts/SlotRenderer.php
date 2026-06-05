<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlotRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

/**
 * Plugins, die HTML in definierte View-Slots einklinken (z. B. Buttons in
 * invoices/show, Panels in assets/show). Wird vom Core über
 * {@see \App\Plugins\PluginManager::renderSlot()} aufgerufen — der Aufruf ist
 * exception-isoliert, ein Fehler reißt die Seite nicht.
 */
interface SlotRenderer {
    /**
     * Rendert (optional) HTML für den gegebenen Slot. `null`/leer = nichts beitragen.
     */
    public function renderActions(string $slot, mixed $context = null): ?string;
}
