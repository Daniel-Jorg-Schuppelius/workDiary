/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dialog-drag.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für die pure Logik verschiebbarer Dialoge
 * (resources/js/lib/dialog-drag.js): Drag-Start-Sperre auf Bedienelementen,
 * Viewport-Begrenzung, Ableitung der Verschiebung aus der Zeigerbewegung.
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import {
    DRAG_IGNORE_SELECTOR,
    baseRect,
    clampOffset,
    dragOffset,
    isDragBlocked,
    translateValue,
} from "../../resources/js/lib/dialog-drag.js";

/** Minimaler Element-Fake: `closest` liefert das Element, dessen Selektor passt. */
const el = (matches, inHeader = true) => ({
    closest: (selector) => (selector === DRAG_IGNORE_SELECTOR && matches ? { inHeader } : null),
});
const header = { contains: (node) => node?.inHeader === true };

const viewport = { width: 1280, height: 800 };
// Zentrierte Standard-Box (640 × 480) im 1280 × 800-Viewport.
const box = { left: 320, top: 160, width: 640, height: 480 };

test("Drag startet auf freier Header-Fläche, nicht auf Bedienelementen", () => {
    assert.equal(isDragBlocked(el(false), header), false);
    assert.equal(isDragBlocked(el(true), header), true);
});

test("Bedienelemente außerhalb des Headers sperren den Drag nicht", () => {
    assert.equal(isDragBlocked(el(true, false), header), false);
});

test("Verschiebung innerhalb des Viewports bleibt unverändert", () => {
    assert.deepEqual(clampOffset({ x: 100, y: -50 }, box, viewport), { x: 100, y: -50 });
    assert.deepEqual(clampOffset({ x: 0, y: 0 }, box, viewport), { x: 0, y: 0 });
});

test("Header darf nicht über den oberen Viewport-Rand hinaus", () => {
    assert.deepEqual(clampOffset({ x: 0, y: -500 }, box, viewport), { x: 0, y: -160 });
});

test("Unten und seitlich bleiben mindestens 48 px sichtbar", () => {
    // nach rechts: left + x ≤ 1280 − 48 → x ≤ 912
    assert.equal(clampOffset({ x: 5000, y: 0 }, box, viewport).x, 912);
    // nach links: left + x + width ≥ 48 → x ≥ 48 − 640 − 320 = −912
    assert.equal(clampOffset({ x: -5000, y: 0 }, box, viewport).x, -912);
    // nach unten: top + y ≤ 800 − 48 → y ≤ 592
    assert.equal(clampOffset({ x: 0, y: 5000 }, box, viewport).y, 592);
});

test("Box größer als Viewport: obere Kante bleibt erreichbar", () => {
    const tall = { left: 0, top: 0, width: 400, height: 2000 };
    const small = { width: 400, height: 300 };
    assert.deepEqual(clampOffset({ x: 0, y: 100 }, tall, small), { x: 0, y: 100 });
    assert.deepEqual(clampOffset({ x: 0, y: -100 }, tall, small), { x: 0, y: 0 });
});

test("Verschiebung wird gerundet", () => {
    assert.deepEqual(clampOffset({ x: 10.4, y: -3.6 }, box, viewport), { x: 10, y: -4 });
});

test("dragOffset addiert Basis-Offset und Zeiger-Delta", () => {
    const offset = dragOffset({
        start: { x: 500, y: 200 },
        current: { x: 560, y: 180 },
        base: { x: 100, y: 40 },
        box,
        viewport,
    });
    assert.deepEqual(offset, { x: 160, y: 20 });
});

test("dragOffset begrenzt auch mit Basis-Offset auf den Viewport", () => {
    const offset = dragOffset({
        start: { x: 500, y: 200 },
        current: { x: 500, y: -900 },
        base: { x: 0, y: 100 },
        box,
        viewport,
    });
    assert.deepEqual(offset, { x: 0, y: -160 });
});

test("baseRect zieht die angewendete Verschiebung wieder ab", () => {
    const measured = { left: 420, top: 120, width: 640, height: 480 };
    assert.deepEqual(baseRect(measured, { x: 100, y: -40 }), box);
});

test("translateValue formatiert als CSS-Längenpaar", () => {
    assert.equal(translateValue({ x: 12, y: -7 }), "12px -7px");
});
