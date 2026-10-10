<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_14_110400_move_lexoffice_base_url_to_lexware.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\Platform\PluginSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1112 (E24): Lexware hat die API am 27.05.2025 nach `api.lexware.io`
 * umbenannt. Gespeicherte Einstellungen mit der alten Vorgabe ziehen mit; eine
 * bewusst eingetragene andere Adresse bleibt.
 */
return new class extends Migration {
    private const OLD = 'https://api.lexoffice.io/v1';

    private const NEW = 'https://api.lexware.io/v1';

    public function up(): void {
        $this->move(self::OLD, self::NEW);
    }

    public function down(): void {
        $this->move(self::NEW, self::OLD);
    }

    private function move(string $from, string $to): void {
        if (! Schema::hasTable('plugin_settings')) {
            return;
        }

        foreach (PluginSetting::query()->withoutGlobalScopes()->where('plugin_id', 'lexoffice')->get() as $row) {
            $settings = (array) ($row->settings ?? []);
            if (rtrim((string) ($settings['base_url'] ?? ''), '/') !== $from) {
                continue;
            }
            // Verschlüsselte Einstellungen nur über das Modell; ohne Beobachter, fachlich keine neue Änderung.
            $row->forceFill(['settings' => ['base_url' => $to] + $settings])->saveQuietly();
        }
    }
};
