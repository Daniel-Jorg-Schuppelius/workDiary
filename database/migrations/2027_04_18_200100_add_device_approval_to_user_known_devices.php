<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_200100_add_device_approval_to_user_known_devices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freigegebene Geräte (Feature 205, MVP-1655): Wer das Recht `auth.remote`
 * hat, meldet sich außerhalb der Büronetze nur von einem Gerät an, das ein
 * Org-Admin freigegeben hat. Das Gerät erkennt die Anwendung an einem
 * signierten Cookie; gespeichert wird nur dessen Hash (`device_hash`), nie das
 * Cookie selbst. Bestandszeilen (Fingerprint für die Meldung „neues Gerät“)
 * bleiben ohne Hash. Eine Ablehnung bleibt stehen (`rejected_at`), damit
 * dasselbe Gerät nicht erneut anfragt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('user_known_devices', function (Blueprint $table): void {
            $table->string('device_hash', 64)->nullable();
            $table->dateTime('approval_requested_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['user_id', 'device_hash'], 'ukd_user_device_idx');
            $table->index('approval_requested_at', 'ukd_requested_idx');
        });
    }

    public function down(): void {
        Schema::table('user_known_devices', function (Blueprint $table): void {
            $table->dropIndex('ukd_user_device_idx');
            $table->dropIndex('ukd_requested_idx');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['device_hash', 'approval_requested_at', 'approved_at', 'rejected_at']);
        });
    }
};
