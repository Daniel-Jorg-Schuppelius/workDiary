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

    /**
     * Feature 039: jede aktive App-Sprache besitzt den vollständigen
     * Topic-Satz (fr/it/es als Stubs) — sonst fällt der Nutzer still auf
     * die de-Hilfe zurück.
     */
    public function test_every_enabled_locale_has_the_full_topic_set(): void {
        $de = $this->loader->topicsForLocale('de');
        $this->assertNotSame([], $de);

        foreach (\App\Support\Locales::enabledCodes() as $locale) {
            $topics = $this->loader->topicsForLocale($locale);
            $this->assertSame(
                [],
                array_values(array_diff($de, $topics)),
                "Locale {$locale}: fehlende Hilfe-Topics gegenüber de."
            );
            $this->assertSame(
                [],
                array_values(array_diff($topics, $de)),
                "Locale {$locale}: überzählige Hilfe-Topics ohne de-Pendant."
            );
        }
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

    /**
     * Modul-Gating (MVP-753): `modules` ist über alle Locales eines Topics
     * identisch (sonst sähe ein fr-Nutzer ein Topic, das de verbirgt) und
     * nennt nur echte Modul-Codes aus config/plans.php.
     */
    public function test_modules_front_matter_is_locale_consistent_and_valid(): void {
        /** @var list<string> $validModules */
        $validModules = array_values(array_unique(array_map(
            'strval',
            array_values((array) config('plans.routes', [])),
        )));
        $this->assertNotEmpty($validModules);

        $locales = $this->loader->locales();
        $problems = [];

        foreach ($this->loader->topicsForLocale('de') as $topic) {
            $reference = null;
            foreach ($locales as $locale) {
                $loaded = $this->loader->load($topic, $locale);
                if ($loaded === null) {
                    continue;
                }
                $modules = $loaded['modules'];
                sort($modules);
                foreach ($modules as $code) {
                    if (! in_array($code, $validModules, true)) {
                        $problems[] = "{$locale}/{$topic}.md nennt unbekanntes Modul: {$code}";
                    }
                }
                if ($reference === null) {
                    $reference = $modules;
                } elseif ($modules !== $reference) {
                    $problems[] = "{$locale}/{$topic}.md weicht in modules von de ab.";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * Bild-Gates (MVP-754): Artikel-Bilder sind lokale `media/`-Assets mit
     * Alt-Text und existierender Basisdatei; der Media-Ordner enthält nur
     * erlaubte, referenzierte Dateien unterhalb des Größenlimits (kein SVG,
     * keine externen Quellen — CSP bleibt 'self', kein Tracking).
     */
    public function test_article_images_are_local_with_alt_text_and_valid_files(): void {
        $mediaRoot = (string) config('help-center.media_path');
        $problems = [];
        $referenced = [];

        foreach ($this->loader->locales() as $locale) {
            foreach ($this->loader->topicsForLocale($locale) as $topic) {
                $loaded = $this->loader->load($topic, $locale);
                if ($loaded === null) {
                    continue;
                }
                preg_match_all('/!\[([^\]]*)\]\(([^)\s]+)/', $loaded['body_md'], $images, PREG_SET_ORDER);
                foreach ($images as $image) {
                    [, $alt, $src] = $image;
                    if (trim($alt) === '') {
                        $problems[] = "{$locale}/{$topic}.md: Bild ohne Alt-Text ({$src})";
                    }
                    if (! str_starts_with($src, 'media/')) {
                        $problems[] = "{$locale}/{$topic}.md: Bildquelle außerhalb von media/ ({$src})";
                        continue;
                    }
                    $relative = substr($src, strlen('media/'));
                    $referenced[$relative] = true;
                    if (! is_file($mediaRoot . DIRECTORY_SEPARATOR . $relative)) {
                        $problems[] = "{$locale}/{$topic}.md: Bilddatei fehlt ({$src})";
                    }
                }
            }
        }

        if (is_dir($mediaRoot)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($mediaRoot, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->getFilename() === '.gitkeep') {
                    continue;
                }
                $relative = ltrim(str_replace($mediaRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                if (! in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp'], true)) {
                    $problems[] = "media/{$relative}: unerlaubte Dateiendung (kein SVG, nur png/jpg/jpeg/webp)";
                }
                if ($file->getSize() > 300 * 1024) {
                    $problems[] = sprintf('media/%s: %d KB überschreitet das 300-KB-Limit', $relative, (int) ($file->getSize() / 1024));
                }
                // Locale-Variante `name.{locale}.{ext}` zählt als referenziert,
                // wenn ihre Basisdatei referenziert ist.
                $base = preg_replace('/\.(de|en|fr|it|es)\.([a-z]+)$/', '.$2', $relative);
                if (! isset($referenced[$relative]) && ! isset($referenced[$base])) {
                    $problems[] = "media/{$relative}: verwaiste Datei (kein Artikel referenziert sie)";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }
}
