/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : iban.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sofortprüfung am Feld; verbindlich prüft App\Rules\Iban (zusätzlich Länderlänge).

/** @param {string} value */
export function normalizeIban(value) {
    return String(value ?? "").replace(/\s+/g, "").toUpperCase();
}

/**
 * Format und Prüfziffer (mod 97, ISO 13616). Leere Eingabe gilt als gültig —
 * Pflicht regelt das required-Attribut.
 *
 * @param {string} value
 * @returns {boolean}
 */
export function isValidIban(value) {
    const iban = normalizeIban(value);
    if (iban === "") return true;
    if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/.test(iban)) return false;

    const rearranged = iban.slice(4) + iban.slice(0, 4);
    let remainder = 0;
    for (const char of rearranged) {
        const digits = /[0-9]/.test(char) ? char : String(char.charCodeAt(0) - 55);
        for (const digit of digits) {
            remainder = (remainder * 10 + Number(digit)) % 97;
        }
    }
    return remainder === 1;
}
