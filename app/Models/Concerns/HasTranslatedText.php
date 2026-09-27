<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasTranslatedText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Locales;

/**
 * Anzeige eines übersetzbaren Stammdatentexts (MVP-841, verallgemeinert in
 * MVP-912): Übersetzung der aktiven Sprache, sonst der Fallback-Sprache der
 * App, sonst der Quellwert (Deutsch). Der Quellwert bleibt das bearbeitbare
 * Feld; Übersetzungen liegen als JSON `{locale: text}` daneben.
 */
trait HasTranslatedText {
    /**
     * Formulareingabe `{locale: text}` bereinigen: nur aktivierte Sprachen
     * außer Deutsch (Quellwert), leere Felder entfallen; nichts übrig = null.
     *
     * @return array<string, string>|null
     */
    public static function cleanTranslations(mixed $input): ?array {
        $clean = [];
        foreach (is_array($input) ? $input : [] as $locale => $text) {
            $locale = strtolower(trim((string) $locale));
            $text = trim((string) $text);
            if ($locale !== 'de' && $text !== '' && in_array($locale, Locales::enabledCodes(), true)) {
                $clean[$locale] = mb_substr($text, 0, 180);
            }
        }
        ksort($clean);

        return $clean === [] ? null : $clean;
    }

    /** @param array<string, mixed>|null $translations */
    protected function translatedText(?array $translations, string $source, ?string $locale = null): string {
        $locale ??= app()->getLocale();
        $i18n = $translations ?? [];
        $short = strtolower(substr($locale, 0, 2));
        foreach (array_unique([$locale, $short]) as $key) {
            $value = trim((string) ($i18n[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        if ($short !== 'de') {
            $fallback = strtolower(substr((string) config('app.fallback_locale', 'en'), 0, 2));
            $value = trim((string) ($i18n[$fallback] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $source;
    }
}
