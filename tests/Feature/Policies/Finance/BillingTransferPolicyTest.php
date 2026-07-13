<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Finance;

use App\Enums\Finance\TransferChannel;
use App\Enums\User\Permission as P;
use App\Models\Finance\BillingTransfer;
use App\Policies\Finance\BillingTransferPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Übergabenachweise (Feature 045): alle schreibenden Aktionen sind
 * KANAL-spezifisch — wer Zeit übergeben darf (finance.transfer.time),
 * darf damit nicht automatisch Material übergeben (finance.transfer.material)
 * und umgekehrt (getrennte Verantwortlichkeiten).
 */
final class BillingTransferPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private BillingTransferPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new BillingTransferPolicy;
    }

    private function transfer(TransferChannel $channel): BillingTransfer {
        $transfer = new BillingTransfer;
        $transfer->channel = $channel;

        return $transfer;
    }

    public function test_channel_permissions_are_strictly_separated(): void {
        $timeClerk = $this->actorIn($this->organization, [P::FinanceTransferTime]);
        $timeTransfer = $this->transfer(TransferChannel::Time);
        $materialTransfer = $this->transfer(TransferChannel::Material);

        $this->assertTrue($this->policy->create($timeClerk, TransferChannel::Time));
        $this->assertTrue($this->policy->confirm($timeClerk, $timeTransfer));
        $this->assertTrue($this->policy->markTransferred($timeClerk, $timeTransfer));
        $this->assertTrue($this->policy->markFailed($timeClerk, $timeTransfer));
        $this->assertTrue($this->policy->void($timeClerk, $timeTransfer));

        // Kanal-Trennung: Zeit-Recht öffnet den Material-Kanal NICHT.
        $this->assertFalse($this->policy->create($timeClerk, TransferChannel::Material));
        $this->assertFalse($this->policy->confirm($timeClerk, $materialTransfer));
        $this->assertFalse($this->policy->void($timeClerk, $materialTransfer));
    }

    public function test_read_only_finance_user_cannot_write(): void {
        $viewer = $this->actorIn($this->organization, [P::FinanceViewAny]);
        $transfer = $this->transfer(TransferChannel::Time);

        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertTrue($this->policy->view($viewer, $transfer));
        $this->assertFalse($this->policy->create($viewer, TransferChannel::Time));
        $this->assertFalse($this->policy->confirm($viewer, $transfer));
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->actorIn($this->organization)));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
