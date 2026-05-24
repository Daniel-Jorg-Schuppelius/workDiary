<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolPdfRendererTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\ProtocolType;
use App\Models\{DiaryEntry, User};
use App\Services\Protocol\{ProtocolPdfRenderer, ProtocolService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProtocolPdfRendererTest extends TestCase {
    use RefreshDatabase;

    public function test_render_creates_pdf_on_disk(): void {
        Storage::fake(ProtocolPdfRenderer::DISK);
        [$creator, $entry] = $this->ctx();
        /** @var ProtocolService $svc */
        $svc = app(ProtocolService::class);
        $p = $svc->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'PDF Test',
        ]);

        /** @var ProtocolPdfRenderer $r */
        $r = app(ProtocolPdfRenderer::class);
        $path = $r->render($p->refresh());

        Storage::disk(ProtocolPdfRenderer::DISK)->assertExists($path);
        $this->assertGreaterThan(100, strlen(Storage::disk(ProtocolPdfRenderer::DISK)->get($path)));
    }

    public function test_render_is_idempotent(): void {
        Storage::fake(ProtocolPdfRenderer::DISK);
        [$creator, $entry] = $this->ctx();
        /** @var ProtocolService $svc */
        $svc = app(ProtocolService::class);
        $p = $svc->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'Idem',
        ]);
        /** @var ProtocolPdfRenderer $r */
        $r = app(ProtocolPdfRenderer::class);

        $path1 = $r->render($p->refresh());
        $contents1 = Storage::disk(ProtocolPdfRenderer::DISK)->get($path1);
        $path2 = $r->render($p->refresh());
        $contents2 = Storage::disk(ProtocolPdfRenderer::DISK)->get($path2);
        $this->assertSame($path1, $path2);
        $this->assertSame($contents1, $contents2);
    }

    /**
     * @return array{0: User, 1: DiaryEntry}
     */
    private function ctx(): array {
        $creator = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($creator)->create();
        return [$creator, $entry];
    }
}
