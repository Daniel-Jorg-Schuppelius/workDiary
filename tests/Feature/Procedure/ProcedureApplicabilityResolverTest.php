<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureApplicabilityResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Models\DiaryEntry;
use App\Models\EntryType;
use App\Models\Organization;
use App\Models\ProcedureTemplate;
use App\Models\User;
use App\Services\Procedure\ProcedureApplicabilityResolver;
use App\Services\Procedure\ProcedureTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedureApplicabilityResolverTest extends TestCase {
    use RefreshDatabase;

    private ProcedureTemplateService $service;
    private ProcedureApplicabilityResolver $resolver;

    protected function setUp(): void {
        parent::setUp();
        $this->service = app(ProcedureTemplateService::class);
        $this->resolver = app(ProcedureApplicabilityResolver::class);
    }

    public function test_suggests_templates_matching_diary_entry_type(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $serviceType = EntryType::factory()->create([
            'organization_id' => $org->id,
            'slug' => EntryType::SLUG_SERVICE,
        ]);

        $matching = $this->publishedTemplate($org, $user, 'IT', ['diary_entry_type' => [EntryType::SLUG_SERVICE]]);
        $this->publishedTemplate($org, $user, 'CARE', ['diary_entry_type' => [EntryType::SLUG_CARE_VISIT]]);

        $entry = DiaryEntry::factory()->for($user)->create(['entry_type_id' => $serviceType->id]);

        $suggestions = $this->resolver->suggestFor($entry);

        $this->assertCount(1, $suggestions);
        $this->assertSame($matching->id, $suggestions->first()->id);
    }

    public function test_universal_templates_are_always_suggested(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $universal = $this->publishedTemplate($org, $user, 'UNI', null);

        $entry = DiaryEntry::factory()->for($user)->create();

        $suggestions = $this->resolver->suggestFor($entry);

        $this->assertTrue($suggestions->contains(fn($t) => $t->id === $universal->id));
    }

    public function test_unpublished_templates_are_skipped(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $this->service->create($org, $user, ['code' => 'DRAFT', 'name' => 'Draft only']);

        $entry = DiaryEntry::factory()->for($user)->create();

        $suggestions = $this->resolver->suggestFor($entry);

        $this->assertCount(0, $suggestions);
    }

    /**
     * @param  array<string, mixed>|null  $applicability
     */
    private function publishedTemplate(Organization $org, User $user, string $code, ?array $applicability): ProcedureTemplate {
        $template = $this->service->create($org, $user, ['code' => $code, 'name' => $code]);
        $version = $template->versions->first();
        if ($applicability !== null) {
            $version->forceFill(['applicability' => $applicability])->save();
        }
        $this->service->publish($version, $user);
        return $template->fresh();
    }

    /** @return array{0: Organization, 1: User} */
    private function makeOrgAndUser(): array {
        $user = User::factory()->geschaeftsfuehrung()->create();
        return [$user->organization, $user];
    }
}
