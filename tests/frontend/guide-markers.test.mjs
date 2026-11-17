/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : guide-markers.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) der Markierungsgeometrie einer Bildanleitung
 * (resources/js/lib/guide-markers.js, Feature 191, MVP-1668).
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { arrowHead, clamp01, markerFromGesture, nextNumber, rectFromPoints } from "../../resources/js/lib/guide-markers.js";

test("clamp01 hält Koordinaten im Bild", () => {
    assert.equal(clamp01(-0.2), 0);
    assert.equal(clamp01(1.4), 1);
    assert.equal(clamp01(0.123456), 0.1235);
    assert.equal(clamp01(Number.NaN), 0);
});

test("rectFromPoints ist richtungsunabhängig und verwirft Klicks", () => {
    assert.deepEqual(rectFromPoints(0.6, 0.5, 0.2, 0.1), { x: 0.2, y: 0.1, w: 0.4, h: 0.4 });
    assert.equal(rectFromPoints(0.5, 0.5, 0.501, 0.6), null);
});

test("nextNumber zählt über der höchsten Nummer weiter", () => {
    assert.equal(nextNumber([]), "1");
    assert.equal(nextNumber([{ kind: "number", x: 0, y: 0, label: "2" }, { kind: "text", x: 0, y: 0, label: "9" }]), "3");
});

test("markerFromGesture baut je Werkzeug die Markierung", () => {
    const start = { x: 0.1, y: 0.1 };
    const end = { x: 0.4, y: 0.3 };
    assert.deepEqual(markerFromGesture("frame", start, end, "", []), { kind: "frame", x: 0.1, y: 0.1, w: 0.3, h: 0.2 });
    assert.deepEqual(markerFromGesture("arrow", start, end, "", []), { kind: "arrow", x: 0.1, y: 0.1, x2: 0.4, y2: 0.3 });
    assert.deepEqual(markerFromGesture("number", start, end, "", []), { kind: "number", x: 0.4, y: 0.3, label: "1" });
    assert.equal(markerFromGesture("text", start, end, "  ", []), null);
    assert.deepEqual(markerFromGesture("text", start, end, "Hier klicken", []), { kind: "text", x: 0.4, y: 0.3, label: "Hier klicken" });
    assert.equal(markerFromGesture("arrow", start, start, "", []), null);
});

test("arrowHead legt die Spitze auf den Endpunkt", () => {
    const head = arrowHead(0, 0, 100, 0, 10);
    assert.equal(head[0], 100);
    assert.equal(head[1], 0);
    assert.ok(head[2] < 100 && head[4] < 100);
});
