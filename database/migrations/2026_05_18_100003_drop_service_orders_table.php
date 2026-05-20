<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_18_100003_drop_service_orders_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Entfernt die alte service_orders-Tabelle. Die Daten wurden zuvor durch
     * 2026_05_18_100002_migrate_service_orders_to_diary_entries.php nach
     * diary_entries übertragen.
     */
    public function up(): void {
        Schema::dropIfExists('service_orders');
    }

    public function down(): void {
        // Bewusst kein Rebuild – die alte Tabelle wird nicht wiederhergestellt.
    }
};
