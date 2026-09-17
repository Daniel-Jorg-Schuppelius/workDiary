/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kiosk.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für die pure Logik des Kiosk-Modus
 * (resources/js/lib/kiosk.js, MVP-800).
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { buildPayload, buildPinPayload, formatBalance, normalizeNfcSerial, statusTone } from "../../resources/js/lib/kiosk.js";

test("Web-NFC-Seriennummer wird zu Hex ohne Trennzeichen in Großbuchstaben", () => {
    assert.equal(normalizeNfcSerial("04:a2:3b:1c"), "04A23B1C");
    assert.equal(normalizeNfcSerial("04-A2 3b"), "04A23B");
    assert.equal(normalizeNfcSerial(undefined), "");
});

test("Status wird dem Anzeige-Ton zugeordnet", () => {
    for (const status of ["clocked_in", "clocked_out", "break_started", "break_ended"]) {
        assert.equal(statusTone(status), "success");
    }
    assert.equal(statusTone("noop"), "warning");
    assert.equal(statusTone("skipped"), "warning");
    assert.equal(statusTone("unknown_badge"), "error");
    assert.equal(statusTone("network"), "error");
});

test("Gleitzeitsaldo wird vorzeichenbehaftet als H:MM formatiert", () => {
    assert.equal(formatBalance(125), "+2:05");
    assert.equal(formatBalance(-45), "−0:45");
    assert.equal(formatBalance(0), "+0:00");
});

test("Nutzlast für den Ingest trägt Ereignis-ID und nur bekannte Ereignistypen", () => {
    assert.deepEqual(buildPayload("  AB12 ", "break", "id-1"), {
        badge_uid: "AB12",
        event: "toggle",
        event_type: "break",
        event_id: "id-1",
    });
    assert.equal(buildPayload("AB12", /** @type {any} */ ("errand"), "id-2").event_type, "work");
});

test("PIN-Nutzlast trägt Personalnummer und PIN statt Ausweis", () => {
    assert.deepEqual(buildPinPayload(" 4711 ", "2468", "work", "id-3"), {
        personnel_number: "4711",
        pin: "2468",
        event: "toggle",
        event_type: "work",
        event_id: "id-3",
    });
});
