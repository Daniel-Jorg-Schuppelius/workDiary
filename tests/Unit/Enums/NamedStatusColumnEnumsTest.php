<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedStatusColumnEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Applications\JobApplicationUploadScanStatus;
use App\Enums\Contracts\HasLabel;
use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Integration\{ExternalArticleSyncStatus, MarketplaceInboxStatus};
use App\Enums\Inventory\StockState;
use App\Enums\Learning\LearningTimeApprovalStatus;
use App\Enums\Manufacturing\DeliveryStockStatus;
use App\Enums\Passenger\RideSettlementStatus;
use App\Enums\Print\PrintQcStatus;
use App\Enums\ServiceTicket\TicketMessageDeliveryStatus;
use App\Plugins\JtlWawi\Enums\JtlRegistrationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Die Enums der benannten Statusspalten (k3-10) tragen genau die Werte, die
 * vorher Konstanten, Migrationen und Schreibstellen nannten, und lesen ihre
 * Texte aus den Schlüsseln, die vorher die Views trugen.
 */
final class NamedStatusColumnEnumsTest extends TestCase {
    public function test_cases_keep_the_stored_values(): void {
        $values = static fn (array $cases): array => array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);

        $this->assertSame(['pending', 'clean', 'rejected'], $values(JobApplicationUploadScanStatus::cases()));
        $this->assertSame(['draft', 'open', 'paid', 'cancelled'], $values(AccountingVoucherState::cases()));
        $this->assertSame(['pending', 'linked', 'synced'], $values(ExternalArticleSyncStatus::cases()));
        $this->assertSame(['open', 'linked'], $values(MarketplaceInboxStatus::cases()));
        $this->assertSame(['pending', 'approved', 'rejected'], $values(LearningTimeApprovalStatus::cases()));
        $this->assertSame(['delivered', 'cancelled'], $values(DeliveryStockStatus::cases()));
        $this->assertSame(['open', 'settled', 'waived'], $values(RideSettlementStatus::cases()));
        $this->assertSame(['passed', 'rework', 'blocked'], $values(PrintQcStatus::cases()));
        $this->assertSame(['queued', 'sent', 'failed'], $values(TicketMessageDeliveryStatus::cases()));
        $this->assertSame(['pending', 'rejected', 'accepted'], $values(JtlRegistrationStatus::cases()));
        $this->assertSame(['quality', 'blocked', 'damaged'], $values(StockState::quarantine()));
    }

    /** @return array<string, array{class-string<\BackedEnum&HasLabel>}> */
    public static function translatedEnums(): array {
        return [
            'QK-Ergebnis' => [PrintQcStatus::class],
            'Lernzeit-Freigabe' => [LearningTimeApprovalStatus::class],
        ];
    }

    /** @param class-string<\BackedEnum&HasLabel> $class */
    #[DataProvider('translatedEnums')]
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
                $this->assertDoesNotMatchRegularExpression('/^(values|print)\./', $label, "{$class}::{$case->name} ohne Text in {$locale}");
                if ($locale !== 'de') {
                    $this->assertNotSame($german[$case->name], $label, "{$class}::{$case->name} fällt in {$locale} auf den deutschen Text zurück");
                }
            }
        }
    }

    public function test_labels_keep_their_german_texts(): void {
        app()->setLocale('de');
        $labels = static fn (string $class): array => array_map(static fn (HasLabel $case): string => $case->label(), $class::cases());

        $this->assertSame(['Freigegeben', 'Nacharbeit', 'Gesperrt'], $labels(PrintQcStatus::class));
        $this->assertSame(['Ausstehend', 'Genehmigt', 'Abgelehnt'], $labels(LearningTimeApprovalStatus::class));
    }

    /** Alle drei Stände haben einen Text; „linked“ fehlte bis 2026-10-06 in allen Sprachen und erschien roh. */
    public function test_article_sync_status_is_labelled_in_every_language(): void {
        foreach (['de', 'en', 'es', 'fr', 'it'] as $locale) {
            app()->setLocale($locale);
            $this->assertSame(__('values.pending'), ExternalArticleSyncStatus::Pending->label());
            $this->assertSame(__('values.synced'), ExternalArticleSyncStatus::Synced->label());
            $this->assertNotSame('values.synced', ExternalArticleSyncStatus::Synced->label());
            $this->assertSame(__('values.linked'), ExternalArticleSyncStatus::Linked->label());
            $this->assertNotSame('linked', ExternalArticleSyncStatus::Linked->label(), $locale);
        }
    }
}
