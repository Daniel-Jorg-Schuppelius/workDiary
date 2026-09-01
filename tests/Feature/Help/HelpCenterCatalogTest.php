<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpCenterCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Services\Help\{HelpCenterCatalog, HelpTopicLoader};
use Tests\TestCase;

/**
 * Zuordnungs-Gate der Hilfecenter-Themenbereiche (Feature 039, MVP-752):
 * Jedes reale Topic (Leitsprache de) gehört über die Muster in
 * config/help-center.php zu genau einem Bereich; was im Auffangbereich
 * landet, braucht einen Eintrag in `fallback_allowed` — sonst rot.
 */
class HelpCenterCatalogTest extends TestCase {
    public function test_every_real_topic_is_assigned_to_a_section(): void {
        $loader = new HelpTopicLoader(HelpTopicLoader::defaultPath());
        $catalog = app(HelpCenterCatalog::class);

        $topics = $loader->topicsForLocale('de');
        $this->assertNotEmpty($topics, 'Leitsprachen-Topics (de) fehlen.');

        $unassigned = $catalog->unassigned($topics);

        $this->assertSame([], $unassigned, sprintf(
            "Topics ohne Themenbereich (config/help-center.php um Muster ergänzen oder bewusst in fallback_allowed aufnehmen):\n- %s",
            implode("\n- ", $unassigned),
        ));
    }

    public function test_fallback_allowlist_names_only_real_fallback_topics(): void {
        $loader = new HelpTopicLoader(HelpTopicLoader::defaultPath());
        $catalog = app(HelpCenterCatalog::class);

        $topics = $loader->topicsForLocale('de');

        /** @var list<string> $allowed */
        $allowed = (array) config('help-center.fallback_allowed', []);
        foreach ($allowed as $code) {
            $this->assertContains($code, $topics, sprintf('fallback_allowed nennt ein nicht existentes Topic: %s', $code));
            $this->assertSame(
                HelpCenterCatalog::FALLBACK_KEY,
                $catalog->sectionKeyFor($code),
                sprintf('fallback_allowed-Eintrag %s wird inzwischen von einem Bereichsmuster erfasst — Eintrag entfernen.', $code),
            );
        }
    }

    public function test_section_language_catalogs_are_complete_in_all_locales(): void {
        $catalog = app(HelpCenterCatalog::class);
        $keys = array_map(static fn(array $section): string => $section['key'], $catalog->sections());

        foreach (['de', 'en', 'fr', 'it', 'es'] as $locale) {
            /** @var array<string, array{title?:string, description?:string}> $sections */
            $sections = (array) trans('help.sections', [], $locale);
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $sections, sprintf('help.sections.%s fehlt in lang/%s/help.php', $key, $locale));
                $this->assertNotSame('', trim((string) ($sections[$key]['title'] ?? '')), sprintf('Leerer Titel: %s (%s)', $key, $locale));
                $this->assertNotSame('', trim((string) ($sections[$key]['description'] ?? '')), sprintf('Leere Beschreibung: %s (%s)', $key, $locale));
            }
        }
    }
}
