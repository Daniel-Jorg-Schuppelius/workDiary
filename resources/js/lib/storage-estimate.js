/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : storage-estimate.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Erwarteter Speicherbedarf eines Video-Uploads (Feature 150, MVP-1600):
 * Original plus umgerechnete Fassungen. Der Faktor kommt vom Server
 * (VideoTranscodingService), hier wird nur gerechnet und beschriftet.
 */

/**
 * @param {number} originalBytes
 * @param {number} renditionFactor Anteil der Fassungen am Original (z. B. 1.5)
 * @returns {number} Original plus Fassungen in Bytes, 0 bei unbrauchbarer Eingabe
 */
export function estimateTotalBytes(originalBytes, renditionFactor) {
    if (!Number.isFinite(originalBytes) || originalBytes <= 0) return 0;
    const factor = Number.isFinite(renditionFactor) && renditionFactor > 0 ? renditionFactor : 0;
    return Math.round(originalBytes * (1 + factor));
}

/**
 * @param {number} bytes
 * @param {string} [locale]
 * @returns {string}
 */
export function describeBytes(bytes, locale = "de") {
    const units = ["B", "KB", "MB", "GB", "TB"];
    let value = Math.max(0, bytes);
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    const digits = unit === 0 ? 0 : 1;
    return new Intl.NumberFormat(locale, { maximumFractionDigits: digits }).format(value) + " " + units[unit];
}
