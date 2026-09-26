/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : nfc.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { recordText } from "../../resources/js/lib/nfc.js";

const encode = (text) => new TextEncoder().encode(text).buffer;

test("NFC: URL-Datensatz wird als Objekt-Link gelesen (MVP-903)", () => {
    const message = { records: [{ recordType: "mime", data: encode("x") }, { recordType: "url", data: encode(" https://wd.test/assets/abc ") }] };
    assert.equal(recordText(message), "https://wd.test/assets/abc");
});

test("NFC: Textdatensatz mit Kodierung, leere Nachricht ergibt null", () => {
    assert.equal(recordText({ records: [{ recordType: "text", encoding: "utf-8", data: encode("A-100") }] }), "A-100");
    assert.equal(recordText({ records: [{ recordType: "text", data: encode("  ") }] }), null);
    assert.equal(recordText(undefined), null);
});
