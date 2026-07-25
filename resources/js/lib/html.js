/**
 * Zentrale HTML-Escaping-Grenze für alle Frontend-Module.
 *
 * Hintergrund: Vor dieser Datei existierten fünf verschiedene Escaper, drei
 * davon mit Lücken (fehlendes `'` bzw. `&`, eine löschte Zeichen statt sie zu
 * escapen). `SafeHtml` macht daraus eine erzwungene Grenze: `setHtml()` nimmt
 * ausschließlich Werte, die durch einen der Escaper hier gelaufen sind.
 *
 * Die Grenze wirkt doppelt — der Typechecker meldet Verstöße beim Build, die
 * Klasse zusätzlich zur Laufzeit. Zweiteres ist wichtig, solange nicht alle
 * Module typgeprüft sind.
 *
 * Escaping ist kontextabhängig. HTML-Escaping schützt NICHT im CSS- oder
 * URL-Kontext; dafür gibt es `escCssValue` und `safeUrl`.
 */

/**
 * Wert, der nachweislich für die Einsetzung in HTML sicher ist.
 * Nur über die Escaper dieses Moduls erzeugbar.
 */
export class SafeHtml {
    /** @param {string} value */
    constructor(value) {
        /** @type {string} @readonly */
        this.value = value;
    }

    /** @returns {string} */
    toString() {
        return this.value;
    }
}

/** @type {Record<string, string>} */
const ENTITIES = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
};

/**
 * Escaped für Text- UND Attributkontext (doppelt oder einfach gequotet).
 * Bewusst ein Zeichensatz für beide Kontexte: die frühere Trennung in escHtml/
 * escAttr hatte in beiden Richtungen Lücken.
 *
 * @param {unknown} value
 * @returns {SafeHtml}
 */
export function escHtml(value) {
    return new SafeHtml(String(value ?? "").replace(/[&<>"']/g, (c) => ENTITIES[c]));
}

/**
 * Markiert ein statisches, im Quelltext stehendes Fragment als sicher.
 * Niemals für Daten verwenden — dafür ist `escHtml` zuständig.
 *
 * @param {string} value
 * @returns {SafeHtml}
 */
export function rawHtml(value) {
    return new SafeHtml(value);
}

/**
 * Serverseitig gerendertes HTML (Blade), das bewusst als Markup eingesetzt
 * wird. Blade escaped Nutzerdaten über `{{ }}`, daher ist die Quelle
 * vertrauenswürdig — im Gegensatz zu Daten aus einer JSON-Antwort.
 *
 * Einzige legitime Umgehung des Escapings. Absichtlich benannt und damit
 * greppbar: jeder Aufruf ist ein Audit-Punkt und gehört begründet.
 *
 * @param {unknown} value HTML-Fragment aus einer serverseitigen Antwort.
 * @returns {SafeHtml}
 */
export function trustedServerHtml(value) {
    return new SafeHtml(String(value ?? ""));
}

// Erlaubt: #rgb/#rrggbb(aa), rgb()/rgba()/hsl()/hsla(), CSS-Farbnamen und
// Längen. Allowlist statt Escaping, weil im CSS-Kontext bereits ein Semikolon
// oder url() ausreicht, um fremde Deklarationen einzuschleusen.
const CSS_VALUE_ALLOWED =
    /^(#[0-9a-f]{3,8}|(rgb|hsl)a?\([0-9%.,\s/]+\)|[a-z]+|-?[0-9.]+(px|rem|em|%|vh|vw|ch)?)$/i;

/**
 * Prüft einen Wert für den CSS-Kontext (z. B. `style="background:${…}"`).
 * Nicht zulässige Werte ergeben einen leeren String, damit die Deklaration
 * wirkungslos bleibt statt teilweise zu greifen.
 *
 * @param {unknown} value
 * @returns {SafeHtml}
 */
export function escCssValue(value) {
    const raw = String(value ?? "").trim();
    return CSS_VALUE_ALLOWED.test(raw) ? escHtml(raw) : new SafeHtml("");
}

const URL_SAFE_PROTOCOLS = ["http:", "https:", "mailto:", "tel:"];

/**
 * Prüft eine URL für den `href`/`src`-Kontext. HTML-Escaping allein verhindert
 * `javascript:`-URLs nicht — hier entscheidet eine Protokoll-Allowlist.
 * Relative Pfade (`/…`, `./…`, `#…`, `?…`) sind zulässig.
 *
 * @param {unknown} value
 * @returns {SafeHtml} Sichere URL, sonst `#`.
 */
export function safeUrl(value) {
    const raw = String(value ?? "").trim();
    if (raw === "") return new SafeHtml("#");

    // Relative Referenzen können kein fremdes Schema tragen.
    if (/^[/?#]/.test(raw) || raw.startsWith("./") || raw.startsWith("../")) {
        return escHtml(raw);
    }

    try {
        // base füllt schemalose Eingaben auf; absolute URLs überschreiben sie.
        const parsed = new URL(raw, window.location.origin);
        return URL_SAFE_PROTOCOLS.includes(parsed.protocol)
            ? escHtml(parsed.href)
            : new SafeHtml("#");
    } catch (_e) {
        return new SafeHtml("#");
    }
}

/**
 * Tagged Template, das interpolierte Werte automatisch escaped. `SafeHtml`
 * wird unverändert übernommen, sodass Fragmente sich schachteln lassen, ohne
 * doppelt escaped zu werden. Arrays werden verbunden — `${items.map(…)}`
 * funktioniert ohne `.join("")`.
 *
 * @param {TemplateStringsArray} strings
 * @param {...unknown} values
 * @returns {SafeHtml}
 *
 * @example
 * setHtml(el, html`<span title="${user.name}">${user.name}</span>`);
 */
export function html(strings, ...values) {
    let out = strings[0];
    for (let i = 0; i < values.length; i++) {
        out += interpolate(values[i]) + strings[i + 1];
    }
    return new SafeHtml(out);
}

/**
 * @param {unknown} value
 * @returns {string}
 */
function interpolate(value) {
    if (value instanceof SafeHtml) return value.value;
    if (Array.isArray(value)) return value.map(interpolate).join("");
    // null/undefined/false ergeben nichts — erlaubt `${cond && html`…`}`.
    if (value == null || value === false) return "";
    return escHtml(value).value;
}

/**
 * Setzt HTML auf ein Element. Einzige zugelassene innerHTML-Schreibstelle.
 *
 * @param {Element | null | undefined} el
 * @param {SafeHtml} safe
 * @returns {void}
 * @throws {TypeError} wenn `safe` kein SafeHtml ist (Laufzeit-Absicherung für
 *   noch nicht typgeprüfte Module).
 */
export function setHtml(el, safe) {
    if (!(safe instanceof SafeHtml)) {
        throw new TypeError(
            "setHtml() erwartet SafeHtml — Wert über escHtml()/html()/trustedServerHtml() führen.",
        );
    }
    if (!el) return;
    el.innerHTML = safe.value;
}

/**
 * Leert ein Element.
 *
 * @param {Element | null | undefined} el
 * @returns {void}
 */
export function clearHtml(el) {
    if (el) el.textContent = "";
}
