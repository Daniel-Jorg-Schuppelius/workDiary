/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kiosk.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Pure Logik des Kiosk-Modus (MVP-800) — ohne DOM, damit sie unter node:test
 * prüfbar ist. Die Seite selbst verdrahtet resources/js/kiosk.js.
 */

/**
 * Seriennummer aus Web NFC (`NDEFReadingEvent.serialNumber`, z. B.
 * "04:a2:3b:1c") in die Form bringen, die USB-Leser üblicherweise tippen:
 * Hex ohne Trennzeichen, Großbuchstaben ("04A23B1C"). Ausweise, die am
 * Tablet gelesen werden, müssen in dieser Form hinterlegt sein.
 *
 * @param {string|null|undefined} serial
 * @returns {string}
 */
export function normalizeNfcSerial(serial) {
    return String(serial ?? "")
        .replace(/[^0-9a-f]/gi, "")
        .toUpperCase();
}

/**
 * Anzeige-Ton je Ingest-Status: Erfolg, Hinweis oder Fehler.
 *
 * @param {string} status
 * @returns {"success"|"warning"|"error"}
 */
export function statusTone(status) {
    if (/^(clocked_(in|out)|break_(started|ended))$/.test(status)) {
        return "success";
    }
    if (status === "noop" || status === "skipped") {
        return "warning";
    }

    return "error";
}

/**
 * Minuten als vorzeichenbehaftetes „±H:MM" (Gleitzeitsaldo).
 *
 * @param {number} minutes
 * @returns {string}
 */
export function formatBalance(minutes) {
    const sign = minutes < 0 ? "−" : "+";
    const abs = Math.abs(Math.trunc(minutes));

    return `${sign}${Math.floor(abs / 60)}:${String(abs % 60).padStart(2, "0")}`;
}

/**
 * Nutzlast für `api.terminal.ingest`. Die Ereignis-ID macht einen doppelt
 * gelesenen Ausweis (zweimal Enter) idempotent.
 *
 * @param {string} badgeUid
 * @param {"work"|"break"} eventType
 * @param {string} eventId
 * @returns {{badge_uid: string, event: string, event_type: string, event_id: string}}
 */
export function buildPayload(badgeUid, eventType, eventId) {
    return {
        badge_uid: badgeUid.trim(),
        event: "toggle",
        event_type: eventType === "break" ? "break" : "work",
        event_id: eventId,
    };
}

/**
 * Nutzlast für den PIN-Weg (MVP-803): Personalnummer + PIN statt Ausweis.
 *
 * @param {string} personnelNumber
 * @param {string} pin
 * @param {"work"|"break"} eventType
 * @param {string} eventId
 * @returns {{personnel_number: string, pin: string, event: string, event_type: string, event_id: string}}
 */
export function buildPinPayload(personnelNumber, pin, eventType, eventId) {
    return {
        personnel_number: personnelNumber.trim(),
        pin: pin.trim(),
        event: "toggle",
        event_type: eventType === "break" ? "break" : "work",
        event_id: eventId,
    };
}
