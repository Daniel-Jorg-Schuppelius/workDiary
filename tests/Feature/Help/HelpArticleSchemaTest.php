<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpArticleSchemaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Services\Help\HelpTopicLoader;
use Tests\TestCase;

/**
 * Artikelschema-Gate (Feature 039, MVP-756): Topics mit Front-Matter
 * `schema: process` tragen die sechs Prozess-Abschnitte
 * (config/help-center.php `article_schema`) als h2 in genau dieser
 * relativen Reihenfolge — in ALLEN fünf Sprachen, mit den lokalisierten
 * Überschriften aus lang/{locale}/help.php. Zusätzliche eigene h2 sind
 * erlaubt, die Schema-Reihenfolge bleibt verbindlich.
 */
class HelpArticleSchemaTest extends TestCase {
    private const LOCALES = ['de', 'en', 'fr', 'it', 'es'];

    public function test_process_articles_carry_the_schema_sections_in_order(): void {
        $loader = new HelpTopicLoader(HelpTopicLoader::defaultPath());
        /** @var list<string> $schemaKeys */
        $schemaKeys = (array) config('help-center.article_schema', []);
        $this->assertNotEmpty($schemaKeys);

        $problems = [];
        $processTopics = [];

        foreach ($loader->topicsForLocale('de') as $topic) {
            $de = $loader->load($topic, 'de');
            if ($de === null || $de['schema'] !== 'process') {
                continue;
            }
            $processTopics[] = $topic;

            foreach (self::LOCALES as $locale) {
                $loaded = $loader->load($topic, $locale);
                if ($loaded === null) {
                    $problems[] = "{$locale}/{$topic}.md fehlt.";
                    continue;
                }
                if ($loaded['schema'] !== 'process') {
                    $problems[] = "{$locale}/{$topic}.md: `schema: process` fehlt (Locale-Parität).";
                }

                /** @var list<string> $expected */
                $expected = array_map(
                    static fn(string $key): string => (string) trans('help.schema.' . $key, [], $locale),
                    $schemaKeys,
                );
                $h2 = array_values(array_map(
                    static fn(array $heading): string => $heading['text'],
                    array_filter($loaded['headings'], static fn(array $heading): bool => $heading['level'] === 2),
                ));

                // Relative Reihenfolge: die Schema-Überschriften müssen als
                // Teilfolge der h2-Reihenfolge vorkommen.
                $cursor = 0;
                foreach ($expected as $section) {
                    $found = false;
                    for (; $cursor < count($h2); $cursor++) {
                        if ($h2[$cursor] === $section) {
                            $found = true;
                            $cursor++;
                            break;
                        }
                    }
                    if (! $found) {
                        $problems[] = "{$locale}/{$topic}.md: Schema-Abschnitt „{$section}“ fehlt oder steht außer der Reihe.";
                    }
                }
            }
        }

        // Der Pilot (MVP-756) verlangt mindestens 12 Prozess-Artikel.
        $this->assertGreaterThanOrEqual(12, count($processTopics), sprintf(
            'Nur %d Topics tragen `schema: process` (mindestens 12 Pilotartikel erwartet): %s',
            count($processTopics),
            implode(', ', $processTopics),
        ));

        $this->assertSame([], $problems, implode("\n", $problems));
    }
}
