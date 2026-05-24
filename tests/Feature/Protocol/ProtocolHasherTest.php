<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolHasherTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolItemType, ProtocolType};
use App\Models\{DiaryEntry, User};
use App\Services\Protocol\{ProtocolHasher, ProtocolService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtocolHasherTest extends TestCase {
    use RefreshDatabase;

    public function test_same_content_produces_same_hash(): void {
        [$creator, $entry] = $this->context();
        /** @var ProtocolService $svc */
        $svc = app(ProtocolService::class);
        /** @var ProtocolHasher $hasher */
        $hasher = app(ProtocolHasher::class);

        $p = $svc->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'X',
        ]);
        $svc->addItem($p, $creator, [
            'item_type' => ProtocolItemType::Boolean->value,
            'label' => 'A',
            'sort_order' => 1,
            'required' => true,
        ]);

        $a = $hasher->canonicalize($p->refresh());
        $b = $hasher->canonicalize($p->refresh());
        $this->assertSame($a, $b);
        $this->assertSame(hash('sha256', $a), hash('sha256', $b));
    }

    public function test_changing_item_changes_hash(): void {
        [$creator, $entry] = $this->context();
        /** @var ProtocolService $svc */
        $svc = app(ProtocolService::class);
        /** @var ProtocolHasher $hasher */
        $hasher = app(ProtocolHasher::class);

        $p = $svc->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'X',
        ]);
        $item = $svc->addItem($p, $creator, [
            'item_type' => ProtocolItemType::Boolean->value,
            'label' => 'A',
            'sort_order' => 1,
            'required' => true,
        ]);

        $before = hash('sha256', $hasher->canonicalize($p->refresh()));

        $item->update(['label' => 'A geändert']);

        $after = hash('sha256', $hasher->canonicalize($p->refresh()));
        $this->assertNotSame($before, $after);
    }

    /**
     * @return array{0: User, 1: DiaryEntry}
     */
    private function context(): array {
        $creator = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($creator)->create();
        return [$creator, $entry];
    }
}
