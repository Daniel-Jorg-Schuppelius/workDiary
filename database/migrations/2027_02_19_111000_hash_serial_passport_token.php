<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_111000_hash_serial_passport_token.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Services\Inventory\SerialPassportService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Token des öffentlichen Geräte-Passes nur noch als Abdruck (Sicherheitsscan
 * 2026-08-23, S-44 — Nachzug zu 2027_02_19_110700).
 *
 * Der Token stand als `settings.serial_passport_token` im Klartext in der
 * JSON-Spalte der Organisation. Er war zugleich das einzige Zugangsmittel zu
 * einer Seite ohne Anmeldung — und es gab keinen Weg, ihn zu wechseln.
 *
 * Bestehende Token werden gehasht, nicht verworfen: umlaufende Links
 * funktionieren weiter. Der Klartext verschwindet dabei aus der Datenbank.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        $rows = DB::table('organizations')->select('id', 'settings')->get();

        foreach ($rows as $row) {
            $settings = json_decode((string) ($row->settings ?? ''), true);
            if (! is_array($settings)) {
                continue;
            }

            $plain = $settings['serial_passport_token'] ?? null;
            if (! is_string($plain) || $plain === '') {
                continue;
            }

            unset($settings['serial_passport_token']);
            $settings[SerialPassportService::HASH_KEY] = SerialPassportService::fingerprint($plain);
            $settings[SerialPassportService::HINT_KEY] = mb_substr($plain, 0, 6);

            DB::table('organizations')->where('id', $row->id)
                ->update(['settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    /**
     * Kein `down()`: Der Klartext ist aus dem Abdruck nicht zu gewinnen. Wer
     * zurückrollt, stellt über die Oberfläche einen neuen Link aus.
     */
    public function down(): void {}
};
