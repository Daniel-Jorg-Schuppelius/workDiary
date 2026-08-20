<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_22_100000_add_subscription_resource_to_cloud_document_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google-Drive-Push-Kanäle (Feature 080; Audit 2026-08, W4.4).
 *
 * Anders als Microsoft Graph verlangt Google zum Beenden eines Kanals NEBEN
 * der Kanal-ID auch die `resourceId` aus der Watch-Antwort. Ohne sie liefe
 * ein alter Kanal bis zum Ablauf weiter und weckte die Verbindung weiter.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('cloud_document_connections', function (Blueprint $table): void {
            $table->string('subscription_resource_id', 191)->nullable()->after('subscription_id');
        });
    }

    public function down(): void {
        Schema::table('cloud_document_connections', function (Blueprint $table): void {
            $table->dropColumn('subscription_resource_id');
        });
    }
};
