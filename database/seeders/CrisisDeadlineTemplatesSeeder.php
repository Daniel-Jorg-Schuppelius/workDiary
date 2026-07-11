<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisDeadlineTemplatesSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Crisis\CrisisDeadlineTemplate;
use Illuminate\Database\Seeder;

/**
 * Globale Meldefristen-Defaults (Feature 070, D9): DSGVO Art. 33/34,
 * NIS2 (§ 32 BSIG n. F.) Frühwarnung 24 h → Meldung 72 h →
 * Abschlussbericht 1 Monat, KRITIS-Dachgesetz unverzüglich.
 * organization_id NULL = Default; Organisationen überschreiben je
 * Kategorie mit eigenen Zeilen (Datenpflege statt Release).
 */
class CrisisDeadlineTemplatesSeeder extends Seeder {
    public function run(): void {
        $defaults = [
            ['category' => 'privacy', 'label' => 'DSGVO Art. 33: Meldung an die Aufsichtsbehörde', 'offset_hours' => 72, 'source' => 'Art. 33 DSGVO'],
            ['category' => 'privacy', 'label' => 'DSGVO Art. 34: Information der Betroffenen bei hohem Risiko', 'offset_hours' => null, 'source' => 'Art. 34 DSGVO (unverzüglich)'],
            ['category' => 'security', 'label' => 'NIS2 Frühwarnung an das BSI', 'offset_hours' => 24, 'source' => '§ 32 BSIG n. F. (NIS2UmsuCG)'],
            ['category' => 'security', 'label' => 'NIS2 Meldung an das BSI', 'offset_hours' => 72, 'source' => '§ 32 BSIG n. F. (NIS2UmsuCG)'],
            ['category' => 'security', 'label' => 'NIS2 Abschlussbericht', 'offset_hours' => 720, 'source' => '§ 32 BSIG n. F. (1 Monat)'],
            ['category' => 'it_outage', 'label' => 'NIS2 Frühwarnung an das BSI (erheblicher Sicherheitsvorfall)', 'offset_hours' => 24, 'source' => '§ 32 BSIG n. F. (NIS2UmsuCG)'],
            ['category' => 'infrastructure', 'label' => 'KRITIS-Dachgesetz: Störfallmeldung (BBK/BSI-Meldestelle)', 'offset_hours' => null, 'source' => 'KRITIS-DachG (unverzüglich)'],
        ];

        foreach ($defaults as $row) {
            CrisisDeadlineTemplate::query()->firstOrCreate(
                ['organization_id' => null, 'category' => $row['category'], 'label' => $row['label']],
                ['offset_hours' => $row['offset_hours'], 'source' => $row['source'], 'active' => true],
            );
        }
    }
}
