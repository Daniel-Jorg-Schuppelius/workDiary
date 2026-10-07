/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : password.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Ohne verwechselbare Zeichen (0/O, 1/l/I): das Passwort wird weitergegeben.
export const PASSWORD_CLASSES = [
    "ABCDEFGHJKLMNPQRSTUVWXYZ",
    "abcdefghijkmnopqrstuvwxyz",
    "23456789",
    "!#$%&*+-=?@_",
];

/**
 * Gleichverteilter Index in [0, max) aus einer Zufallsquelle für Uint32Array
 * (Verwerfen statt Modulo, sonst wären vordere Zeichen häufiger).
 *
 * @param {number} max
 * @param {(buffer: Uint32Array) => Uint32Array} fill
 */
function randomIndex(max, fill) {
    const limit = Math.floor(0x100000000 / max) * max;
    const buffer = new Uint32Array(1);
    for (;;) {
        fill(buffer);
        if (buffer[0] < limit) return buffer[0] % max;
    }
}

/**
 * Passwort, das Password::defaults() erfüllt: jede Zeichenklasse mindestens
 * einmal, Rest aus allen Klassen, danach gemischt.
 *
 * @param {number} [length]
 * @param {(buffer: Uint32Array) => Uint32Array} [fill]
 * @returns {string}
 */
export function suggestPassword(
    length = 16,
    fill = (buffer) => globalThis.crypto.getRandomValues(buffer),
) {
    const size = Math.max(length, PASSWORD_CLASSES.length);
    const all = PASSWORD_CLASSES.join("");
    const chars = PASSWORD_CLASSES.map((set) => set[randomIndex(set.length, fill)]);
    while (chars.length < size) {
        chars.push(all[randomIndex(all.length, fill)]);
    }
    for (let i = chars.length - 1; i > 0; i--) {
        const j = randomIndex(i + 1, fill);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join("");
}
