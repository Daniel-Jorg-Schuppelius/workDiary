<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataOwnershipWriteRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Enums\Integration\DataDomain;
use App\Plugins\Contracts\{DocumentIntakeSource, WritesDataDomains};
use App\Plugins\PluginManager;
use CommonToolkit\Helper\FileSystem\File;
use ReflectionClass;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-1592: Jeder Schreibweg eines Plugins in einen durchgesetzten
 * Bereich der Datenführerschaft fragt vorher die Führung — über den
 * `DataOwnershipGuard` oder `DataOwnershipResolver::mayWrite()`.
 *
 * - Die Klasse eines Schreibwegs trägt `#[WritesDataDomain(…)]` und prüft.
 * - Ein Schreib-Grundbaustein des Bereichs (Anlage, Buchung) steht nur in
 *   so markierten Klassen oder mit Grund in {@see ALLOW_LIST}.
 * - Ein Plugin, das einen Bereich in `dataDomains()` meldet (Matrix), erreicht
 *   einen markierten Schreibweg dieses Bereichs — direkt oder über einen
 *   Vertrag, den ein markierter Kerndienst verarbeitet ({@see CHANNELS}).
 */
class DataOwnershipWriteRuleTest extends TestCase {
    use ScansSourceTree;

    /** Durchgesetzte Bereiche (Werte von {@see DataDomain}) — seit Paket 11b alle. */
    private const array ENFORCED = ['tasks', 'tickets', 'inventory', 'calendar', 'documents', 'customers'];

    /** Verzeichnisse mit Schreibwegen der Integrationen; der Cloud-Eingang schreibt für die Plugins. */
    private const array SCAN_DIRECTORIES = ['app/Plugins', 'app/Services/CloudIntake'];

    /** Schreib-Grundbausteine je Bereich. */
    private const array PRIMITIVES = [
        'tasks' => '/\bTask::(?:query\(\)->)?create\(/',
        'tickets' => '/ServiceTicketService::class\)->create\(|\bServiceTicket(?:Message)?::(?:query\(\)->)?create\(|\bnew ServiceTicket(?:Message)?\(/',
        'inventory' => '/->correction\(|->ledger->post\(|\bnew StockPosting\(/',
        'calendar' => '/\bnew AppointmentRequest\(|\bAppointmentRequest::(?:query\(\)->)?create\(|\bEvent::(?:query\(\)->)?create\(/',
        'documents' => '/->documents->create\(|DocumentService::class\)->create\(|->addVersionFromContents\(|\bDocument::(?:query\(\)->)?create\(/',
        'customers' => '/\b(?:Customer|Supplier)::(?:query\(\)->)?create\(|ConflictFieldPolicy::RemoteWins/',
    ];

    /** Bereich → Vertrag der Plugins → markierter Kerndienst, der ihn verarbeitet. */
    private const array CHANNELS = [
        'documents' => [DocumentIntakeSource::class => 'CloudIntakeRunner'],
    ];

    /** Dateien mit Grundbaustein ohne eigene Prüfung — Grund je Datei. */
    private const array ALLOW_LIST = [
        // Bestandsprovider: buchen WorkDiarys eigene Bewegungen ins Journal und spiegeln sie ans führende System.
        'app/Plugins/JtlWawi/Services/JtlWawiInventoryProvider.php' => 'Provider-Weg nativer Buchungen',
        'app/Plugins/Billbee/Services/BillbeeInventoryProvider.php' => 'Provider-Weg nativer Buchungen',
        // Nur über den CloudIntakeRunner erreichbar, der vor dem Download die Führung prüft.
        'app/Services/CloudIntake/CloudIntakeRouter.php' => 'Ziel-Übergabe des geprüften Runners',
        // Spiegelkonflikt: der Mensch entscheidet im Eingang „als Version übernehmen“ — wirkt wie „Einmalig übernehmen“.
        'app/Plugins/Support/Mirror/DocumentConflictResolver.php' => 'Entscheidung im Eingang',
    ];

    private const string ATTRIBUTE = '/#\[WritesDataDomain\(DataDomain::(\w+)\)\]/';

    public function test_marked_write_paths_ask_for_ownership(): void {
        $violations = [];
        foreach ($this->markedFiles() as $relative => $marked) {
            $source = $this->stripComments((string) File::read($this->repoRoot() . '/' . $relative));
            $asks = (str_contains($source, 'DataOwnershipGuard') && str_contains($source, '->allows(')) || str_contains($source, '->mayWrite(');
            if (! $asks) {
                $violations[] = "{$relative}: markiert für " . implode(', ', $marked['domains']) . ', fragt aber weder Guard noch mayWrite()';
            }
        }

        $this->assertSame([], $violations, "Schreibwege ohne Führerschaftsprüfung (MVP-1592):\n" . implode("\n", $violations));
    }

    public function test_write_primitives_live_in_marked_write_paths(): void {
        $marked = $this->markedFiles();
        $violations = [];
        foreach (self::SCAN_DIRECTORIES as $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                $relative = $this->relativePath($file);
                if (str_contains($relative, '/Database/Migrations/') || isset(self::ALLOW_LIST[$relative])) {
                    continue;
                }
                $source = $this->stripComments((string) File::read($file));
                foreach (self::PRIMITIVES as $domain => $pattern) {
                    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
                        continue;
                    }
                    if (! in_array($domain, $marked[$relative]['domains'] ?? [], true)) {
                        $violations[] = sprintf('%s:%d — schreibt „%s“ ohne #[WritesDataDomain(DataDomain::%s)]', $relative, $this->lineOf($source, (int) $match[0][1]), $domain, $this->caseName($domain));
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Schreib-Grundbausteine außerhalb geprüfter Schreibwege (MVP-1592):\n" . implode("\n", $violations));
    }

    public function test_declared_writers_reach_a_marked_write_path(): void {
        $classesByDomain = [];
        foreach ($this->markedFiles() as $marked) {
            foreach ($marked['domains'] as $domain) {
                $classesByDomain[$domain][] = $marked['class'];
            }
        }

        $violations = [];
        foreach (app(PluginManager::class)->all() as $plugin) {
            if (! $plugin instanceof WritesDataDomains) {
                continue;
            }
            $directory = $this->relativePath(dirname((string) (new ReflectionClass($plugin))->getFileName()));
            $sources = implode("\n", array_map(static fn (string $file): string => (string) File::read($file), $this->phpFiles($directory)));
            foreach ($plugin->dataDomains() as $domain) {
                $reaches = false;
                foreach (self::CHANNELS[$domain->value] ?? [] as $contract => $consumer) {
                    if ($plugin instanceof $contract && in_array($consumer, $classesByDomain[$domain->value] ?? [], true)) {
                        $reaches = true;
                    }
                }
                foreach ($classesByDomain[$domain->value] ?? [] as $class) {
                    if (preg_match('/\b' . preg_quote($class, '/') . '\b/', $sources) === 1) {
                        $reaches = true;

                        break;
                    }
                }
                if (! $reaches) {
                    $violations[] = "{$plugin->id()}: meldet „{$domain->value}“, erreicht aber keinen markierten Schreibweg";
                }
            }
        }

        $this->assertSame([], $violations, "Plugins in der Matrix ohne geprüften Schreibweg (MVP-1592):\n" . implode("\n", $violations));
    }

    /** Jeder Bereich der Matrix ist durchgesetzt und hat Grundbausteine — ein neuer Bereich auch. */
    public function test_every_domain_is_enforced_with_primitives(): void {
        $domains = array_map(static fn (DataDomain $d): string => $d->value, DataDomain::cases());
        $this->assertEqualsCanonicalizing($domains, self::ENFORCED);
        $this->assertSame([], array_values(array_diff(self::ENFORCED, array_keys(self::PRIMITIVES))));
    }

    /** @return array<string, array{class: string, domains: list<string>}> Pfad → Klasse und markierte Bereiche */
    private function markedFiles(): array {
        $out = [];
        foreach ($this->phpFiles('app') as $file) {
            $source = (string) File::read($file);
            if (preg_match_all(self::ATTRIBUTE, $source, $matches) < 1 || preg_match('/^(?:final |abstract )*class (\w+)/m', $source, $class) !== 1) {
                continue;
            }
            $domains = [];
            foreach ($matches[1] as $case) {
                $domains[] = constant(DataDomain::class . '::' . $case)->value;
            }
            $out[$this->relativePath($file)] = ['class' => $class[1], 'domains' => array_values(array_unique($domains))];
        }

        return $out;
    }

    private function caseName(string $value): string {
        return DataDomain::from($value)->name;
    }
}
