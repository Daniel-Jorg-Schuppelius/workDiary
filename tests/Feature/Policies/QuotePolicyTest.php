<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuotePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{Quote, User};
use App\Policies\QuotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Angebote (Feature 066, MVP-170): Abrechnungsrecht wie Rechnungen; Änderungen
 * nur am Entwurf (nach Versand wird versioniert), Versand erst nach interner
 * Freigabe, Umwandlung nur aus angenommenen Angeboten.
 */
final class QuotePolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private QuotePolicy $policy;

    private User $accountant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->policy = new QuotePolicy;
        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
    }

    private function quote(string $status): Quote {
        $quote = new Quote;
        $quote->status = $status;

        return $quote;
    }

    public function test_draft_lifecycle_for_accountant(): void {
        $draft = $this->quote('draft');

        $this->assertTrue($this->policy->viewAny($this->accountant));
        $this->assertTrue($this->policy->create($this->accountant));
        $this->assertTrue($this->policy->update($this->accountant, $draft));
        $this->assertTrue($this->policy->delete($this->accountant, $draft));
        $this->assertTrue($this->policy->approve($this->accountant, $draft));
        $this->assertFalse($this->policy->send($this->accountant, $draft), 'Versand erst nach Freigabe.');
        $this->assertFalse($this->policy->convert($this->accountant, $draft));
    }

    public function test_approved_quote_may_be_sent_but_not_edited(): void {
        $approved = $this->quote('approved');

        $this->assertTrue($this->policy->send($this->accountant, $approved));
        $this->assertFalse($this->policy->update($this->accountant, $approved), 'Nach Freigabe wird versioniert statt geändert.');
        $this->assertFalse($this->policy->delete($this->accountant, $approved));
        $this->assertFalse($this->policy->approve($this->accountant, $approved));
    }

    public function test_convert_only_accepted_quotes(): void {
        $this->assertTrue($this->policy->convert($this->accountant, $this->quote('accepted')));
        $this->assertTrue($this->policy->convert($this->accountant, $this->quote('partially_accepted')));
        $this->assertFalse($this->policy->convert($this->accountant, $this->quote('sent')));
        $this->assertFalse($this->policy->convert($this->accountant, $this->quote('rejected')));
    }

    public function test_regular_or_orgless_user_has_no_access(): void {
        $user = $this->actorIn($this->organization);
        $draft = $this->quote('draft');

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->update($user, $draft));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
