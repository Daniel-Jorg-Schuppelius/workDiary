/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : toolbar-overflow.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für die Verteilung der Seitenkopf-Aktionen auf
 * Leiste und ⋯-Menü (resources/js/lib/toolbar-overflow.js, MVP-966).
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import {
    headReserve,
    normalizePlacement,
    planOverflow,
} from "../../resources/js/lib/toolbar-overflow.js";

const item = (width, placement = "auto") => ({ width, placement });
const MORE = 32;
const GAP = 8;

test("alles passt: kein Menü", () => {
    const plan = planOverflow([item(100), item(100), item(100)], 316, MORE, GAP);
    assert.deepEqual(plan, { menu: [], compact: false });
});

test("Überlauf verlässt die Leiste von hinten, ⋯ zählt mit", () => {
    // 3 × 100 + 2 × 8 = 316 passt nicht in 300; mit ⋯: 100 + 100 + 32 + 2 × 8 = 248.
    const plan = planOverflow([item(100), item(100), item(100)], 300, MORE, GAP);
    assert.deepEqual(plan, { menu: [2], compact: false });
});

test("feste Aktion bleibt, auch wenn sie hinten steht", () => {
    const plan = planOverflow([item(100), item(100), item(100, "bar")], 250, MORE, GAP);
    assert.deepEqual(plan, { menu: [1], compact: false });
});

test("menu und danger stehen immer im Menü, auch bei viel Platz", () => {
    const plan = planOverflow([item(100), item(80, "menu"), item(60, "danger")], 2000, MORE, GAP);
    assert.deepEqual(plan, { menu: [1, 2], compact: false });
});

test("mehr Platz holt Aktionen zurück", () => {
    const items = [item(100), item(100), item(100), item(100)];
    assert.deepEqual(planOverflow(items, 260, MORE, GAP).menu, [2, 3]);
    assert.deepEqual(planOverflow(items, 400, MORE, GAP).menu, [3]);
    assert.deepEqual(planOverflow(items, 424, MORE, GAP).menu, []);
});

test("nur feste Aktionen, die nicht passen: compact", () => {
    const plan = planOverflow([item(120, "bar"), item(120, "bar")], 200, MORE, GAP);
    assert.deepEqual(plan, { menu: [], compact: true });
});

test("unsichtbare Aktionen bleiben unberührt und kosten keinen Abstand", () => {
    const plan = planOverflow([item(100), item(0), item(0, "danger"), item(100)], 208, MORE, GAP);
    assert.deepEqual(plan, { menu: [], compact: false });
});

test("leere Leiste mit nur Menüeinträgen braucht genau den ⋯-Knopf", () => {
    const plan = planOverflow([item(90, "menu")], MORE, MORE, GAP);
    assert.deepEqual(plan, { menu: [0], compact: false });
});

test("unbekannte Platzierung gilt als auto", () => {
    assert.equal(normalizePlacement("bar"), "bar");
    assert.equal(normalizePlacement("irgendwas"), "auto");
    assert.equal(normalizePlacement(null), "auto");
});

test("Titelblock behält min(14rem, 45 %)", () => {
    assert.equal(headReserve(1600, 16), 224);
    assert.equal(headReserve(400, 16), 180);
});
