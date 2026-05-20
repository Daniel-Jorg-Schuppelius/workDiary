<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JsTranslationProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\I18n;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Arr;

/**
 * Exposes a flat key => translated string map for the current locale so it
 * can be serialised to JSON and read by client-side code through the
 * `window.__()` helper.
 */
class JsTranslationProvider {
    /**
     * Lang files under lang/{locale}/ whose contents are exposed to JS.
     *
     * @var list<string>
     */
    private const EXPOSED_GROUPS = ['js'];

    public function __construct(private readonly Translator $translator) {
    }

    /**
     * Returns a flat ['group.subkey' => 'translation'] map for the given
     * locale (or the active app locale by default).
     *
     * @return array<string, string>
     */
    public function all(?string $locale = null): array {
        $locale ??= $this->translator->getLocale();
        $flat = [];

        foreach (self::EXPOSED_GROUPS as $group) {
            /** @var mixed $raw */
            $raw = $this->translator->get($group, [], $locale);
            if (! is_array($raw)) {
                continue;
            }
            foreach (Arr::dot($raw) as $key => $value) {
                if (is_string($value)) {
                    $flat[$group . '.' . $key] = $value;
                }
            }
        }

        return $flat;
    }
}
