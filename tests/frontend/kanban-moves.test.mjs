/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanban-moves.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für die fachliche Zug-Entscheidung des Kanban-Boards
 * (resources/js/lib/kanban-moves.js): Bestandszüge bleiben, Züge in, aus und
 * zwischen den Prüfspalten (Feature 198) verweisen verständlich auf den
 * Auftrag, statt als „nicht vorgesehen“ abgewiesen zu werden.
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { REVIEW_STATUSES, planMove } from "../../resources/js/lib/kanban-moves.js";

const REVIEW = { error: "js.kanban.review_via_order", tone: "info" };
const actions = {
    accept: "/diary/1/lifecycle/accept",
    complete: "/diary/1/lifecycle/complete",
    submitReview: "/diary/1/lifecycle/submitReview",
};

test("Bestandszüge bleiben unverändert", () => {
    assert.deepEqual(planMove("2", "4", actions), { spec: { action: "accept" }, url: actions.accept });
    assert.deepEqual(planMove("1", "-1", actions), {
        spec: { action: "complete", dialog: "kanban-complete-dialog" },
        url: actions.complete,
    });
    assert.deepEqual(planMove("1", "-1", {}), { error: "js.kanban.not_allowed", tone: "warning" });
    assert.deepEqual(planMove("2", "7", actions), { error: "js.kanban.invalid_move", tone: "warning" });
    assert.deepEqual(planMove("-1", "6", actions), { error: "js.kanban.handover_via_order", tone: "info" });
    assert.equal(planMove("1", "1", actions), null);
    assert.equal(planMove("", "1", actions), null);
});

test("Züge in, aus und zwischen den Prüfspalten verweisen auf den Auftrag", () => {
    assert.deepEqual(REVIEW_STATUSES, ["9", "10", "11"]);
    for (const to of REVIEW_STATUSES) {
        assert.deepEqual(planMove("1", to, actions, true), REVIEW, `In Bearbeitung → ${to}`);
    }
    for (const [from, to] of [["9", "10"], ["9", "11"], ["10", "9"], ["11", "-1"], ["9", "1"], ["9", "8"]]) {
        assert.deepEqual(planMove(from, to, actions, true), REVIEW, `${from} → ${to}`);
    }
});

test("mit Prüfpflicht führt „Erledigt“ über die Vorlage im Auftrag", () => {
    assert.deepEqual(planMove("1", "-1", actions, true), REVIEW);
    // Die übrigen Züge der Karte bleiben, wie sie sind.
    assert.deepEqual(planMove("2", "4", actions, true), { spec: { action: "accept" }, url: actions.accept });
});
