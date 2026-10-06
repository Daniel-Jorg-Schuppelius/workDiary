<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalSustainabilityPrivacyStatusLabelsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Contracts\HasLabel;
use App\Enums\Domain\DomainEventStatus;
use App\Enums\Privacy\{ComplianceFindingStatus, MeasureStatus};
use App\Enums\Rental\{RentalCaseAssetStatus, RentalConditionItemState, RentalReservationStatus};
use App\Enums\Sustainability\{SustainabilityAssessmentStatus, SustainabilityMeasureStatus};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Die Status-Enums von Verleih und Nachhaltigkeit lesen ihre Texte aus
 * `values.<wert>`, der Lückenbefund aus den JSON-Texten seiner Ansicht. Fehlt
 * ein Eintrag, erscheint der Schlüssel oder — in einer Fremdsprache — still
 * der deutsche Text.
 */
final class RentalSustainabilityPrivacyStatusLabelsTest extends TestCase {
    /** @return array<string, array{class-string<\BackedEnum&HasLabel>}> */
    public static function enums(): array {
        return array_map(static fn (string $class): array => [$class], [
            'Leihobjekt' => RentalCaseAssetStatus::class,
            'Belegungsfenster' => RentalReservationStatus::class,
            'ESG-Bewertung' => SustainabilityAssessmentStatus::class,
            'Verbesserungsmaßnahme' => SustainabilityMeasureStatus::class,
            'Lückenbefund' => ComplianceFindingStatus::class,
        ]);
    }

    /** @param class-string<\BackedEnum&HasLabel> $class */
    #[DataProvider('enums')]
    public function test_every_case_is_translated_in_all_five_locales(string $class): void {
        app()->setLocale('de');
        $german = [];
        foreach ($class::cases() as $case) {
            $german[$case->name] = $case->label();
        }

        foreach (['de', 'en', 'es', 'fr', 'it'] as $locale) {
            app()->setLocale($locale);
            foreach ($class::cases() as $case) {
                $label = $case->label();
                $this->assertNotSame('', $label);
                $this->assertStringStartsNotWith('values.', $label, "{$class}::{$case->name} ohne Text in {$locale}");
                if ($locale !== 'de') {
                    $this->assertNotSame($german[$case->name], $label, "{$class}::{$case->name} fällt in {$locale} auf den deutschen Text zurück");
                }
            }
        }
    }

    /** Der Lückenbefund behält die Texte seiner Ansicht. */
    public function test_compliance_finding_keeps_its_german_texts(): void {
        app()->setLocale('de');

        $this->assertSame(
            ['Fehlt', 'Läuft ab', 'Erforderlich', 'In Prüfung', 'Abweichung akzeptiert', 'Vorhanden', 'Nicht anwendbar'],
            array_map(static fn (ComplianceFindingStatus $status): string => $status->label(), ComplianceFindingStatus::cases()),
        );
    }

    /** Die Auswahl im Formular, die Validierung und die Abfragen lesen dieselbe Menge. */
    public function test_cases_and_named_subsets_keep_their_stored_values(): void {
        $values = static fn (array $cases): array => array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);

        $this->assertSame(['stored', 'acknowledged', 'failed'], $values(DomainEventStatus::cases()));
        $this->assertSame(['planned', 'handed_over', 'returned', 'swapped'], RentalCaseAssetStatus::values());
        $this->assertSame(['planned', 'handed_over'], $values(RentalCaseAssetStatus::open()));
        $this->assertSame(['active', 'completed', 'cancelled'], RentalReservationStatus::values());
        $this->assertSame(['ok', 'worn', 'damaged', 'missing'], $values(RentalConditionItemState::cases()));
        $this->assertSame(['draft', 'final'], SustainabilityAssessmentStatus::values());
        $this->assertSame(['proposed', 'approved', 'in_progress', 'done', 'discarded'], SustainabilityMeasureStatus::values());
        $this->assertSame(['proposed', 'approved', 'in_progress'], $values(SustainabilityMeasureStatus::open()));
        $this->assertSame(['missing', 'expiring', 'required', 'in_review', 'deviation_accepted', 'present', 'not_applicable'], ComplianceFindingStatus::values());
        $this->assertSame(['present', 'in_review', 'not_applicable', 'deviation_accepted', 'missing'], $values(ComplianceFindingStatus::manual()));
        $this->assertSame(['missing', 'expiring'], $values(ComplianceFindingStatus::detected()));
        $this->assertSame(['open', 'done'], $values(MeasureStatus::cases()));
    }

    public function test_only_open_positions_keep_a_rental_case_open(): void {
        foreach (RentalCaseAssetStatus::cases() as $status) {
            $this->assertSame(in_array($status, [RentalCaseAssetStatus::Planned, RentalCaseAssetStatus::HandedOver], true), $status->isOpen(), $status->value);
        }
    }
}
