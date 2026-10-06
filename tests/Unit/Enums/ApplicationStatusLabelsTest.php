<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationStatusLabelsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Applications\{ApplicationContractNegotiationStatus, ApplicationContractReviewStatus, ApplicationOpportunityStatus, ApplicationRequirementStatus, EmployeeDraftStatus, JobApplicationInterviewStatus, JobApplicationStatus, JobPostingStatus, JobRequisitionStatus};
use App\Enums\Contracts\HasLabel;
use App\Enums\Tenders\TenderNoticeMatchState;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Die Status-Enums der Bewerbungs- und Vergabeakten lesen ihre Texte aus
 * `values.<wert>` bzw. aus JSON-Schlüsseln. Fehlt ein Eintrag, erscheint der
 * Schlüssel oder — in einer Fremdsprache — still der deutsche Text.
 */
final class ApplicationStatusLabelsTest extends TestCase {
    /** @return array<string, array{class-string<\BackedEnum&HasLabel>}> */
    public static function enums(): array {
        return array_map(static fn (string $class): array => [$class], [
            'Vertragsverhandlung' => ApplicationContractNegotiationStatus::class,
            'Review-Punkt' => ApplicationContractReviewStatus::class,
            'Ausschreibung' => ApplicationOpportunityStatus::class,
            'Anforderung' => ApplicationRequirementStatus::class,
            'Mitarbeiter-Entwurf' => EmployeeDraftStatus::class,
            'Bewerbung' => JobApplicationStatus::class,
            'Gespräch' => JobApplicationInterviewStatus::class,
            'Stellenanzeige' => JobPostingStatus::class,
            'Stelle' => JobRequisitionStatus::class,
            'Radar-Treffer' => TenderNoticeMatchState::class,
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
}
