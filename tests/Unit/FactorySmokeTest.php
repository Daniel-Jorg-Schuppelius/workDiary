<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FactorySmokeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Chat\{Channel, Message};
use App\Models\Contract\Contract;
use App\Models\Finance\{AccountingVoucher, BillingTransferEvent, BillingTransferItem, BillingTransferPosition, DatevBookingEvent, DatevBookingSource, PaymentReconciliationEvent, PaymentRun, PaymentRunItem, SepaMandate};
use App\Models\Investments\InvestmentCase;
use App\Models\Migration\{AccountingMigrationItem, AccountingMigrationRun};
use App\Models\{Quote, QuoteItem};
use App\Models\Survey\{Survey, SurveyInvitation, SurveyQuestion};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D13: Jede neue Factory erzeugt mit ihren Defaults einen
 * persistierbaren Datensatz. Die Factories sind Angebot für NEUE Tests —
 * Bestandstests bleiben unangetastet. organization_id kommt (Haus-Stil) aus
 * dem currentOrganization-Binding; die Hash-Ketten-Events leiten sie aus
 * ihrem Aggregat ab.
 */
class FactorySmokeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return list<class-string<Model>> */
    public static function newFactoryModels(): array {
        return [
            Quote::class,
            QuoteItem::class,
            Contract::class,
            Channel::class,
            Message::class,
            InvestmentCase::class,
            Survey::class,
            SurveyQuestion::class,
            SurveyInvitation::class,
            AccountingMigrationRun::class,
            AccountingMigrationItem::class,
            AccountingVoucher::class,
            BillingTransferEvent::class,
            BillingTransferItem::class,
            BillingTransferPosition::class,
            DatevBookingEvent::class,
            DatevBookingSource::class,
            PaymentReconciliationEvent::class,
            PaymentRun::class,
            PaymentRunItem::class,
            SepaMandate::class,
        ];
    }

    public function test_every_new_factory_creates_a_valid_record(): void {
        foreach (self::newFactoryModels() as $modelClass) {
            $model = $modelClass::factory()->create();

            $this->assertTrue($model->exists, $modelClass . ' wurde nicht persistiert.');
            $this->assertNotNull($model->getKey(), $modelClass . ' hat keinen Primärschlüssel.');
        }
    }

    public function test_org_scoped_factories_inherit_the_bound_organization(): void {
        $quote = Quote::factory()->create();
        $this->assertSame($this->organization->id, (int) $quote->organization_id);
        $this->assertSame($this->organization->id, (int) $quote->customer?->organization_id);

        $item = QuoteItem::factory()->create();
        $this->assertSame($this->organization->id, (int) $item->organization_id);
    }

    public function test_hash_chain_event_factories_produce_chained_rows(): void {
        $event = BillingTransferEvent::factory()->create();

        $this->assertNotNull($event->hash, 'HashChained muss den Kettenhash im creating-Event setzen.');
        $this->assertSame($this->organization->id, (int) $event->organization_id, 'Org kommt aus dem Transfer-Aggregat.');
    }
}
