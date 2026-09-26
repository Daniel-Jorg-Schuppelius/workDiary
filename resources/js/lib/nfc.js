/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : nfc.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/** Ersten URL- oder Textdatensatz einer NDEF-Nachricht als Text (MVP-903). */
export function recordText(message) {
    for (const record of message?.records ?? []) {
        if (record.recordType === "url" || record.recordType === "text") {
            const value = new TextDecoder(record.encoding || "utf-8").decode(record.data).trim();
            if (value !== "") return value;
        }
    }
    return null;
}
