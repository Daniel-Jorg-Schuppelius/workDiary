<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesHelpdeskFinanceStatusLabelsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Claims\ClaimAssessmentStatus;
use App\Enums\Contract\ContractObligationStatus;
use App\Enums\Contracts\HasLabel;
use App\Enums\Document\DocumentDispatchStatus;
use App\Enums\Finance\TaxRuleStatus;
use App\Enums\Isms\IsmsAuditProgramStatus;
use App\Enums\Platform\OnboardingStepState;
use App\Enums\Sales\QuoteStatus;
use App\Enums\ServiceTicket\ChangeStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Die Status-Enums der Welle 8 (k3-10) lesen ihre Texte aus den Schlüsseln,
 * die vorher Controller und Views trugen. Fehlt ein Eintrag, erscheint der
 * Schlüssel oder — in einer Fremdsprache — still der deutsche Text.
 */
final class SalesHelpdeskFinanceStatusLabelsTest extends TestCase {
    /** @return array<string, array{class-string<\BackedEnum&HasLabel>}> */
    public static function enums(): array {
        return array_map(static fn (string $class): array => [$class], [
            'Angebot' => QuoteStatus::class,
            'Change' => ChangeStatus::class,
            'Vertragstermin' => ContractObligationStatus::class,
            'Zustellversuch' => DocumentDispatchStatus::class,
            'Auditprogramm' => IsmsAuditProgramStatus::class,
            'Einrichtungsschritt' => OnboardingStepState::class,
            'Steuerregel' => TaxRuleStatus::class,
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
                $this->assertDoesNotMatchRegularExpression('/^(values|onboarding)\./', $label, "{$class}::{$case->name} ohne Text in {$locale}");
                if ($locale !== 'de') {
                    $this->assertNotSame($german[$case->name], $label, "{$class}::{$case->name} fällt in {$locale} auf den deutschen Text zurück");
                }
            }
        }
    }

    /** Die Texte, die vorher in Controller und Views standen. */
    public function test_labels_keep_their_german_texts(): void {
        app()->setLocale('de');
        $labels = static fn (string $class): array => array_map(static fn (HasLabel $case): string => $case->label(), $class::cases());

        $this->assertSame(['Entwurf', 'Wartet auf Freigabe', 'Genehmigt', 'In Umsetzung', 'Abgeschlossen', 'Abgebrochen'], $labels(ChangeStatus::class));
        $this->assertSame(['offen', 'erledigt', 'versäumt'], $labels(ContractObligationStatus::class));
        $this->assertSame(['aktiv', 'abgeschlossen', 'abgebrochen'], $labels(IsmsAuditProgramStatus::class));
        $this->assertSame([__('onboarding.page.badge_open'), __('onboarding.page.badge_done'), __('onboarding.page.badge_skipped')], $labels(OnboardingStepState::class));
        foreach ([QuoteStatus::class, DocumentDispatchStatus::class, TaxRuleStatus::class] as $class) {
            foreach ($class::cases() as $case) {
                $this->assertSame(__('values.' . $case->value), $case->label());
            }
        }
    }

    public function test_tones_match_the_former_view_tables(): void {
        $tones = static fn (string $class): array => array_map(static fn (\BackedEnum $case): string => $case->tone(), $class::cases());

        $this->assertSame(['info', 'success', 'error'], $tones(ContractObligationStatus::class));
        $this->assertSame(['ghost', 'success', 'error'], $tones(DocumentDispatchStatus::class));
        $this->assertSame(['success', 'info', 'neutral'], $tones(IsmsAuditProgramStatus::class));
        $this->assertSame(['warning', 'success', 'ghost'], $tones(OnboardingStepState::class));
    }

    /** Validierung, Auswahl, Import und Abfragen lesen dieselben Mengen wie vor dem Cast. */
    public function test_cases_and_named_subsets_keep_their_stored_values(): void {
        $values = static fn (array $cases): array => array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);

        $this->assertSame(['draft', 'approved', 'sent', 'accepted', 'partially_accepted', 'rejected', 'expired'], QuoteStatus::values());
        $this->assertSame(['approved', 'sent'], $values(QuoteStatus::pending()));
        $this->assertSame(['draft', 'approved', 'sent'], $values(QuoteStatus::open()));
        $this->assertSame(['accepted', 'partially_accepted'], $values(QuoteStatus::won()));
        $this->assertSame(['draft', 'sent', 'accepted', 'rejected', 'expired'], $values(QuoteStatus::importable()));
        $this->assertSame(['draft', 'pending_approval', 'approved', 'implementing', 'done', 'cancelled'], ChangeStatus::values());
        $this->assertSame(['active', 'superseded'], $values(ClaimAssessmentStatus::cases()));
        $this->assertSame(['open', 'done', 'missed'], $values(ContractObligationStatus::cases()));
        $this->assertSame(['queued', 'sent', 'failed'], $values(DocumentDispatchStatus::cases()));
        $this->assertSame(['active', 'completed', 'cancelled'], IsmsAuditProgramStatus::values());
        $this->assertSame(['open', 'done', 'skipped'], $values(OnboardingStepState::cases()));
        $this->assertSame(['active', 'draft', 'retired'], $values(TaxRuleStatus::cases()));
    }

    public function test_quote_and_change_predicates_cover_the_former_literal_lists(): void {
        foreach (QuoteStatus::cases() as $status) {
            $this->assertSame(in_array($status->value, ['approved', 'sent'], true), $status->isPending(), $status->value);
            $this->assertSame(in_array($status->value, ['accepted', 'partially_accepted'], true), $status->isWon(), $status->value);
            $this->assertSame(in_array($status->value, ['accepted', 'partially_accepted', 'rejected'], true), $status->isDecided(), $status->value);
            $this->assertSame(in_array($status->value, ['sent', 'rejected', 'expired'], true), $status->isVersionable(), $status->value);
        }
        foreach (ChangeStatus::cases() as $status) {
            $this->assertSame(in_array($status->value, ['draft', 'pending_approval', 'approved', 'implementing'], true), $status->isOpen(), $status->value);
        }
    }
}
