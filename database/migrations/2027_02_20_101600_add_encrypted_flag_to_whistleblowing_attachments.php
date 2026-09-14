<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101600_add_encrypted_flag_to_whistleblowing_attachments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meldeanhänge werden ab jetzt mit dem Fall-Schlüssel verschlüsselt abgelegt
 * (Sicherheitsaudit 2026-09-13). Bis dahin lagen sie im Klartext auf der
 * Platte, während Betreff, Inhalt und Kontaktdaten längst mit dem Fall-DEK
 * geschützt waren — das Crypto-Shredding beim Löschen eines Falls wirkte
 * dadurch nicht auf die Beweismittel: In jedem Backup blieben Fotos und PDFs
 * samt EXIF-/Autor-Metadaten lesbar, also genau der Weg, der den Hinweisgeber
 * enttarnt.
 *
 * Das Kennzeichen unterscheidet neue von alten Dateien, damit Bestandsanhänge
 * weiter ausgeliefert werden können.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('whistleblowing_attachments', function (Blueprint $table): void {
            $table->boolean('encrypted')->default(false)->after('storage_key');
        });
    }

    public function down(): void {
        Schema::table('whistleblowing_attachments', function (Blueprint $table): void {
            $table->dropColumn('encrypted');
        });
    }
};
