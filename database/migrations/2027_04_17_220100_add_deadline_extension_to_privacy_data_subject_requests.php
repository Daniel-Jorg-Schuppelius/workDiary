<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_220100_add_deadline_extension_to_privacy_data_subject_requests.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fristverlängerung nach Art. 12 Abs. 3 DSGVO (MVP-1602): erste Frist,
 * Zeitpunkt der Verlängerung und der mit dem Fall-Schlüssel verschlüsselte Grund.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('privacy_data_subject_requests', function (Blueprint $table): void {
            $table->timestamp('original_deadline_at')->nullable()->after('deadline_at');
            $table->timestamp('deadline_extended_at')->nullable()->after('original_deadline_at');
            $table->text('deadline_extension_reason_ciphertext')->nullable()->after('deadline_extended_at');
        });
    }

    public function down(): void {
        Schema::table('privacy_data_subject_requests', function (Blueprint $table): void {
            $table->dropColumn(['original_deadline_at', 'deadline_extended_at', 'deadline_extension_reason_ciphertext']);
        });
    }
};
