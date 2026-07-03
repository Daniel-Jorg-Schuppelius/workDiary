<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_01_120000_drop_legacy_pending_entry_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * MVP-103 Phase 4 (Aufräumen): entfernt die alten Plugin-Staging-Tabellen.
     * Offene Einträge wurden zuvor durch die Backfill-Migrationen
     * 2026_08_24_120000 (Toggl) bzw. 2026_08_25_120000 (OpenProject) in die
     * universelle `integration_inbox_items`-Tabelle übertragen.
     * `remote_pending_sessions` (RemoteSupport) und
     * `pending_external_conflicts` (Inventory-Outbox) bleiben bewusst bestehen.
     */
    public function up(): void {
        Schema::dropIfExists('toggl_pending_entries');
        Schema::dropIfExists('openproject_pending_entries');
    }

    public function down(): void {
        // Bewusst kein Rebuild – die alten Staging-Tabellen werden nicht
        // wiederhergestellt (Ersatz: integration_inbox_items).
    }
};
