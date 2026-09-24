<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JournalContractRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Models\Journal\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-864: Ereignisjournale (`*Event` auf `*_events`) erben von
 * {@see JournalEntry}; geschrieben wird nur über `HasJournal::record()` bzw.
 * `JournalEntry::log()` — kein `::create(` und kein `->journal()->create(` auf
 * Journalklassen in `app/`.
 */
class JournalContractRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Fachobjekte, die nur so heißen — kein Journal */
    private const NOT_A_JOURNAL = [
        'App\Models\Calendar\Event' => 'Kalendertermin.',
        'App\Models\Safety\SafetyEvent' => 'Arbeitsschutz-Ereignis mit eigenem Lebenszyklus (Feature 132).',
        'App\Models\Domain\DomainEvent' => 'Domain-Vorgang (Feature 083), bearbeitbar.',
        'App\Models\AssetCompliance\AssetInspectionEvent' => 'Prüfungsereignis mit Checklistenwerten, bearbeitbar.',
    ];

    public function test_event_models_on_event_tables_extend_the_journal_entry(): void {
        $violations = [];
        foreach ($this->phpFiles('app/Models') as $file) {
            $relative = $this->relativePath($file);
            if (! str_ends_with($relative, 'Event.php')) {
                continue;
            }
            $class = 'App\\' . str_replace('/', '\\', substr($relative, 4, -4));
            if (! class_exists($class) || (new \ReflectionClass($class))->isAbstract() || isset(self::NOT_A_JOURNAL[$class])) {
                continue;
            }
            $model = new $class;
            if (! $model instanceof Model) {
                continue;
            }
            $table = $model->getTable();
            if (! str_ends_with($table, '_events')) {
                continue;
            }
            if (! is_subclass_of($class, JournalEntry::class)) {
                $violations[] = "{$class} ({$table})";
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Journalmodell ohne JournalEntry-Basis (MVP-864) — erben oder mit Grund in NOT_A_JOURNAL:\n" . implode("\n", $violations));
    }

    public function test_journals_are_written_only_through_record_or_log(): void {
        $journals = [];
        foreach ($this->phpFiles('app/Models') as $file) {
            $class = 'App\\' . str_replace('/', '\\', substr($this->relativePath($file), 4, -4));
            if (class_exists($class) && is_subclass_of($class, JournalEntry::class) && ! (new \ReflectionClass($class))->isAbstract()) {
                $journals[] = class_basename($class);
            }
        }
        $this->assertNotEmpty($journals);
        $pattern = '/\b(' . implode('|', array_map('preg_quote', $journals)) . ')::(query\(\)->)?(create|firstOrCreate|forceCreate)\(|->journal\(\)->(create|firstOrCreate|forceCreate)\(/';
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($relative === 'app/Models/Journal/JournalEntry.php') {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all($pattern, $source, $m, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($m[0] as [$hit, $offset]) {
                    $violations[] = $relative . ':' . $this->lineOf($source, $offset) . ' — ' . $hit;
                }
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Journal direkt beschrieben — `\$traeger->record(...)` bzw. `XEvent::log(...)` verwenden (MVP-864):\n" . implode("\n", $violations));
    }
}
