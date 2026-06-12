<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpContentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Enums\User\UserRole;
use App\Services\Help\HelpTopicLoader;
use Tests\TestCase;

/**
 * Feature 039 Inkrement 2: Qualitätssicherung der ausgelieferten
 * Hilfe-Inhalte unter resources/help/{locale}/.
 *
 * Prüft die echten Markdown-Dateien (keine Fixtures):
 *  - alle Dateien parsen fehlerfrei (Front-Matter + Body),
 *  - jedes de-Topic hat ein en-Pendant (und umgekehrt),
 *  - related-Verweise zeigen auf existierende Topics,
 *  - Registry-Einträge (config/help-topics.php) zeigen auf existierende
 *    Topic-Dateien,
 *  - audience-Werte sind gültige Rollen-Slugs (UserRole = Quelle der
 *    Wahrheit für den PermissionsSeeder) oder das Wildcard-Zeichen `*`,
 *  - Anwender-Topics zeigen keine internen Berechtigungsschlüssel.
 */
class HelpContentTest extends TestCase {
    private HelpTopicLoader $loader;

    protected function setUp(): void {
        parent::setUp();
        $this->loader = new HelpTopicLoader(HelpTopicLoader::defaultPath());
    }

    public function test_help_directory_contains_de_and_en_locales(): void {
        $locales = $this->loader->locales();

        $this->assertContains('de', $locales);
        $this->assertContains('en', $locales);
    }

    public function test_all_topic_files_parse_without_errors(): void {
        $checked = 0;

        foreach ($this->loader->locales() as $locale) {
            foreach ($this->loader->topicsForLocale($locale) as $topic) {
                $loaded = $this->loader->load($topic, $locale);

                $this->assertNotNull($loaded, "{$locale}/{$topic}.md konnte nicht geladen werden.");
                $this->assertNotSame(
                    $topic,
                    $loaded['title'],
                    "{$locale}/{$topic}.md hat keinen Titel im Front-Matter (Fallback auf Topic-Code)."
                );
                $this->assertNotSame('', $loaded['body_md'], "{$locale}/{$topic}.md hat keinen Markdown-Body.");
                $this->assertNotSame('', trim($loaded['body_html']), "{$locale}/{$topic}.md ergibt leeres HTML.");
                $this->assertGreaterThanOrEqual(1, $loaded['version'], "{$locale}/{$topic}.md hat keine gültige Version.");
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked, 'Keine Hilfe-Topics gefunden.');
    }

    public function test_every_de_topic_has_an_en_counterpart_and_vice_versa(): void {
        $de = $this->loader->topicsForLocale('de');
        $en = $this->loader->topicsForLocale('en');

        $this->assertSame(
            [],
            array_values(array_diff($de, $en)),
            'Diese de-Topics haben kein en-Pendant.'
        );
        $this->assertSame(
            [],
            array_values(array_diff($en, $de)),
            'Diese en-Topics haben kein de-Pendant.'
        );
    }

    public function test_related_references_point_to_existing_topics(): void {
        $allCodes = $this->loader->allTopicCodes();

        foreach ($this->loader->locales() as $locale) {
            foreach ($this->loader->loadAllForLocale($locale) as $item) {
                foreach ($item['related'] as $related) {
                    $this->assertContains(
                        $related,
                        $allCodes,
                        "{$locale}/{$item['topic']}.md verweist auf unbekanntes related-Topic »{$related}«."
                    );
                    $this->assertNotSame(
                        $item['topic'],
                        $related,
                        "{$locale}/{$item['topic']}.md verweist auf sich selbst."
                    );
                }
            }
        }
    }

    public function test_registry_entries_point_to_existing_topic_files(): void {
        /** @var array<string, mixed> $map */
        $map = (array) config('help-topics.routes');
        $allCodes = $this->loader->allTopicCodes();

        $this->assertNotEmpty($map, 'Die Route→Topic-Registry ist leer.');

        foreach ($map as $pattern => $topic) {
            $this->assertIsString($topic, "Registry-Eintrag »{$pattern}« ist kein Topic-String.");
            $this->assertContains(
                $topic,
                $allCodes,
                "Registry-Eintrag »{$pattern}« zeigt auf unbekanntes Topic »{$topic}«."
            );
        }
    }

    public function test_audience_values_are_valid_role_slugs(): void {
        $validSlugs = array_map(static fn(UserRole $role) => $role->value, UserRole::cases());
        $validSlugs[] = '*';

        foreach ($this->loader->locales() as $locale) {
            foreach ($this->loader->loadAllForLocale($locale) as $item) {
                foreach ($item['audience'] as $audience) {
                    $this->assertContains(
                        $audience,
                        $validSlugs,
                        "{$locale}/{$item['topic']}.md nutzt unbekannten audience-Slug »{$audience}«."
                    );
                }
            }
        }
    }

    public function test_user_facing_topics_do_not_expose_internal_permission_keys(): void {
        foreach ($this->loader->locales() as $locale) {
            foreach ($this->loader->loadAllForLocale($locale) as $item) {
                // Admin-Handbücher dürfen technische Schlüssel und Befehle
                // erklären; normale Prozesshilfe soll UI-Begriffe verwenden.
                if (str_starts_with($item['topic'], 'admin.')) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/`[A-Za-z][A-Za-z0-9_-]*\.[A-Za-z][A-Za-z0-9_.-]*`/',
                    $item['body_md'],
                    "{$locale}/{$item['topic']}.md zeigt einen internen Berechtigungs- oder Statusschlüssel."
                );
            }
        }
    }
}
