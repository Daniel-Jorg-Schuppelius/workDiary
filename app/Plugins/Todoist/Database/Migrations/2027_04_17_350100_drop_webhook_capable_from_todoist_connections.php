<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_350100_drop_webhook_capable_from_todoist_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1620: `webhook_capable` setzte und las niemand — der Webhook-Endpunkt
 * ordnet über den verbundenen Todoist-Benutzer zu, nicht über ein Kennzeichen.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('todoist_connections', 'webhook_capable')) {
            Schema::table('todoist_connections', function (Blueprint $table): void {
                $table->dropColumn('webhook_capable');
            });
        }
    }

    public function down(): void {
        if (Schema::hasTable('todoist_connections') && ! Schema::hasColumn('todoist_connections', 'webhook_capable')) {
            Schema::table('todoist_connections', function (Blueprint $table): void {
                $table->boolean('webhook_capable')->default(false);
            });
        }
    }
};
