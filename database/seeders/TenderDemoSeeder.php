<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderDemoSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Enums\Applications\TenderProcedureType;
use App\Models\Applications\{ApplicationOpportunity, TenderCompetitorBid};
use App\Models\{Organization, User};
use App\Models\Tenders\{TenderFilterProfile, TenderNotice, TenderNoticeMatch};
use Illuminate\Database\Seeder;

/**
 * Demo-Daten des Vergabewegs (Feature 108, MVP-635): Radar → Treffer →
 * Vergabevorgang → Unterlagen → Submissionsergebnis.
 *
 * Der Weg soll in Demos **an einem Stück** vorführbar sein: Ein Suchprofil
 * findet eine Bekanntmachung, daraus entsteht ein Vergabevorgang mit Fristen
 * und Unterlagen-Checkliste, und ein zweiter, bereits verlorener Vorgang zeigt
 * das Submissionsergebnis mit Preisabstand — die Rückmeldung, aus der sich die
 * nächste Kalkulation speist.
 *
 * Bewusst **ohne** GAEB-Dateien: Das Leistungsverzeichnis bringt der
 * {@see GaebDemoSeeder} mit, und ein Vorgang ohne LV ist der Regelfall in der
 * Angebotsphase.
 */
class TenderDemoSeeder extends Seeder {
    public function run(?Organization $organization = null): void {
        $organization ??= Organization::query()->orderBy('id')->first();
        if ($organization === null) {
            return;
        }

        app()->instance('currentOrganization', $organization);
        $actor = User::query()->where('organization_id', $organization->id)->orderBy('id')->first();

        $profile = TenderFilterProfile::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Hochbau in NRW'],
            [
                'active' => true,
                // Präfixe: 45 trifft alle Bauleistungen, DEA ganz
                // Nordrhein-Westfalen.
                'cpv_codes' => ['45'],
                'nuts_codes' => ['DEA'],
                'keywords' => ['Rohbau', 'Neubau'],
                'excluded_keywords' => ['Abbruch'],
                'min_value' => '50000',
                'created_by' => $actor?->id,
            ],
        );

        $notice = TenderNotice::query()->firstOrCreate(
            ['notice_id' => 'demo-notice-0001', 'version' => '1'],
            [
                'ocid' => 'ocds-demo-0001',
                'title' => 'Neubau Kindertagesstätte — Rohbauarbeiten',
                'summary' => 'Rohbau in Massivbauweise, rund 1.200 m² BGF, Ausführung ab Frühjahr.',
                'buyer_name' => 'Stadt Bonn, Amt für Gebäudemanagement',
                'procedure_method' => 'open',
                'cpv_codes' => ['45210000', '45262500'],
                'nuts_code' => 'DEA22',
                'estimated_value' => '860000.00',
                'currency' => 'EUR',
                'published_on' => now()->subDays(6)->toDateString(),
                'submission_deadline' => now()->addDays(18),
                'url' => 'https://oeffentlichevergabe.de/',
            ],
        );

        TenderNoticeMatch::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'tender_notice_id' => $notice->id],
            ['tender_filter_profile_id' => $profile->id, 'state' => TenderNoticeMatch::STATE_NEW],
        );

        // Laufender Vorgang: Fristen offen, Unterlagen teils erledigt.
        $running = ApplicationOpportunity::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'title' => 'Sanierung Turnhalle — Dach und Fassade'],
            [
                'kind' => 'tender',
                'status' => 'in_progress',
                'go_decision' => 'go',
                'awarding_body' => 'Kreis Ahrweiler',
                'procedure_no' => 'VOB-2026-0117',
                'procedure_type' => TenderProcedureType::PublicInvitation,
                'above_threshold' => false,
                'cpv_codes' => ['45261000'],
                'nuts_code' => 'DEB13',
                'estimated_value' => '410000.00',
                'submission_deadline' => now()->addDays(9)->toDateString(),
                'binding_until' => now()->addMonths(2)->toDateString(),
                'question_deadline' => now()->addDays(3)->toDateString(),
                'responsible_user_id' => $actor?->id,
                'created_by' => $actor?->id,
            ],
        );

        if ($running->requirements()->count() === 0) {
            foreach ([
                ['Referenzliste vergleichbarer Vorhaben', 'document', 'done'],
                ['Eigenerklärung zur Eignung', 'proof', 'done'],
                ['Nachweis Berufshaftpflicht', 'proof', 'open'],
                ['Rückfrage: Bauzeitenplan', 'question', 'open'],
            ] as $index => [$label, $kind, $status]) {
                $running->requirements()->create([
                    'organization_id' => $organization->id,
                    'label' => $label,
                    'kind' => $kind,
                    'required' => $kind !== 'question',
                    'status' => $status,
                    'position' => $index + 1,
                ]);
            }
        }

        // Abgeschlossener Vorgang mit Submissionsergebnis: der Lerneffekt.
        $lost = ApplicationOpportunity::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'title' => 'Erweiterung Feuerwehrgerätehaus'],
            [
                'kind' => 'tender',
                'status' => 'lost',
                'go_decision' => 'go',
                'awarding_body' => 'Gemeinde Wachtberg',
                'procedure_no' => 'VOB-2026-0088',
                'procedure_type' => TenderProcedureType::PublicInvitation,
                'above_threshold' => false,
                'estimated_value' => '268400.00',
                'submission_deadline' => now()->subDays(35)->toDateString(),
                'loss_reason' => 'Preis zu hoch',
                'responsible_user_id' => $actor?->id,
                'created_by' => $actor?->id,
            ],
        );

        if ($lost->competitorBids()->count() === 0) {
            foreach ([
                ['Bauunternehmung Meyer GmbH', '241800.00', 1, false, true],
                ['Eigenes Angebot', '268400.00', 2, true, false],
                ['Hochbau Rheinland KG', '279950.00', 3, false, false],
            ] as [$bidder, $amount, $rank, $isOwn, $isWinner]) {
                TenderCompetitorBid::query()->create([
                    'organization_id' => $organization->id,
                    'application_opportunity_id' => $lost->id,
                    'bidder_name' => $bidder,
                    'amount' => $amount,
                    'rank' => $rank,
                    'is_own' => $isOwn,
                    'is_winner' => $isWinner,
                    'recorded_on' => now()->subDays(33)->toDateString(),
                    'source' => 'opening',
                    'created_by' => $actor?->id,
                ]);
            }
        }
    }
}
