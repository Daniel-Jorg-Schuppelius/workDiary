/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Dauer "h:mm" in Minuten; null bei anderer Form oder Minuten außerhalb 0–59.
 * Genutzt vom Zeitformular (app.js) und der Erfassungsleiste (entry-bar.js).
 */
export function toMinutes(val) {
    const parts = String(val || "").split(":");
    if (parts.length !== 2) return null;
    const h = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    if (isNaN(h) || isNaN(m) || m < 0 || m > 59) return null;
    return h * 60 + m;
}
