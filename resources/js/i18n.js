/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : i18n.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Client-side i18n helper. Reads from `window.__translations`, which is
 * populated by the JsTranslationProvider via a script tag in the main
 * layout. Falls back to the key itself when no translation is registered
 * so missing keys remain visible during development.
 *
 * Placeholders use Laravel's `:name` syntax and are replaced from the
 * optional `replace` object.
 */
function translate(key, replace) {
    const dict = (typeof window !== "undefined" && window.__translations) || {};
    let value = Object.prototype.hasOwnProperty.call(dict, key)
        ? dict[key]
        : key;

    if (replace && typeof value === "string") {
        for (const placeholder of Object.keys(replace)) {
            value = value.replace(
                new RegExp(`:${placeholder}\\b`, "g"),
                String(replace[placeholder]),
            );
        }
    }

    return value;
}

if (typeof window !== "undefined" && typeof window.__ !== "function") {
    window.__ = translate;
}

export default translate;
export { translate as __ };
