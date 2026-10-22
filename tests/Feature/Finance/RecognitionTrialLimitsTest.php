<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecognitionTrialLimitsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Services\Invoicing\Recognition\ToolkitWordBoxSource;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Support\Facades\{Cache, Route};
use PDFToolkit\Entities\WordBox;
use Tests\TestCase;

/** Sicherheitsaudit 2026-10-21, pub-3: Probelauf gedrosselt, Wortboxen je Dateiinhalt zwischengespeichert. */
final class RecognitionTrialLimitsTest extends TestCase {
    public function test_word_boxes_come_from_the_cache_for_the_same_file_content(): void {
        $path = tempnam(sys_get_temp_dir(), 'wb');
        file_put_contents($path, 'kein PDF — nur der Inhalt zählt für den Schlüssel');
        Cache::put('word-boxes:1:' . File::hash($path, HashAlgorithm::SHA256), [
            (new WordBox(1, 10.0, 20.0, 30.0, 8.0, 'Rechnung'))->toArray(),
        ], now()->addMinute());

        try {
            $boxes = (new ToolkitWordBoxSource)->boxesFor($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(1, $boxes);
        $this->assertInstanceOf(WordBox::class, $boxes[0]);
        $this->assertSame('Rechnung', $boxes[0]->text);
    }

    public function test_both_trial_routes_are_throttled(): void {
        foreach (['finance.incoming-invoices.templates.trial', 'finance.incoming-invoices.template.trial'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('throttle:recognition-trial', $route->gatherMiddleware(), $name);
        }
    }
}
