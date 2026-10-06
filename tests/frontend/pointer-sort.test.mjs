/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : pointer-sort.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für das Sortieren per Zeiger
 * (resources/js/lib/pointer-sort.js): zuerst die pure Entscheidungslogik
 * (Zugbeginn, Schwelle, Ziel, davor/dahinter, Einfügeposition, Randscrollen),
 * danach der Ablauf von `pointerSort()` gegen ein Minimal-DOM — das ersetzt
 * keinen Browser, hält aber die Zustandsfolge fest (Klick bleibt Klick,
 * Abbruch stellt wieder her, genau ein Klick wird verschluckt).
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import {
    SORTING_CLASS,
    SORT_IGNORE_SELECTOR,
    dropsBefore,
    edgeScrollStep,
    exceedsThreshold,
    indexAfterMove,
    insertionIndex,
    mayStartDrag,
    pickTarget,
    pointerSort,
    predecessorIndex,
    scrollAxes,
    visibleSpan,
} from "../../resources/js/lib/pointer-sort.js";

// ── Pure Logik ──────────────────────────────────────────────────────────

/** Vier Zeilen à 40 px ab y = 100. */
const rows = [0, 1, 2, 3].map((i) => ({ left: 0, top: 100 + i * 40, width: 200, height: 40 }));

test("Schwelle: erst ab 6 px wird aus dem Druck ein Zug", () => {
    const start = { x: 50, y: 50 };
    assert.equal(exceedsThreshold(start, { x: 53, y: 54 }), false);
    assert.equal(exceedsThreshold(start, { x: 50, y: 56 }), true);
    // 4 px + 5 px diagonal = 6,4 px
    assert.equal(exceedsThreshold(start, { x: 54, y: 55 }), true);
});

test("Schwelle längs einer Achse ignoriert die Querbewegung", () => {
    const start = { x: 50, y: 50 };
    assert.equal(exceedsThreshold(start, { x: 90, y: 52 }, 6, "y"), false);
    assert.equal(exceedsThreshold(start, { x: 50, y: 44 }, 6, "y"), true);
    assert.equal(exceedsThreshold(start, { x: 57, y: 50 }, 6, "x"), true);
});

/** Element-Fake: `closest` liefert je Selektor das hinterlegte Element. */
const hitOn = (map) => ({ closest: (selector) => map[selector] ?? null });
const inside = { inItem: true };
const outside = { inItem: false };
const item = { contains: (node) => node?.inItem === true };

test("Ohne Griff zieht das ganze Element, nur nicht aus Bedienelementen", () => {
    assert.equal(mayStartDrag(hitOn({}), item), true);
    assert.equal(mayStartDrag(hitOn({ [SORT_IGNORE_SELECTOR]: inside }), item), false);
});

test("Bedienelemente außerhalb des Elements und das Element selbst sperren nicht", () => {
    assert.equal(mayStartDrag(hitOn({ [SORT_IGNORE_SELECTOR]: outside }), item), true);
    assert.equal(mayStartDrag(hitOn({ [SORT_IGNORE_SELECTOR]: item }), item), true);
});

test("Mit Griff beginnt der Zug nur am Griff", () => {
    const rules = { handle: "[grip]" };
    assert.equal(mayStartDrag(hitOn({ "[grip]": inside }), item, rules), true);
    assert.equal(mayStartDrag(hitOn({}), item, rules), false);
    // Griff einer anderen Zeile
    assert.equal(mayStartDrag(hitOn({ "[grip]": outside }), item, rules), false);
});

test("Ein Griff, der selbst ein Knopf ist, bleibt Griff", () => {
    const hit = hitOn({ "[grip]": inside, [SORT_IGNORE_SELECTOR]: inside });
    assert.equal(mayStartDrag(hit, item, { handle: "[grip]" }), true);
});

test("mouseAnywhere: Maus überall, Finger und Stift nur am Griff", () => {
    const rules = { handle: "[grip]", mouseAnywhere: true };
    assert.equal(mayStartDrag(hitOn({}), item, { ...rules, mouse: true }), true);
    assert.equal(mayStartDrag(hitOn({}), item, { ...rules, mouse: false }), false);
    assert.equal(mayStartDrag(hitOn({ "[grip]": inside }), item, { ...rules, mouse: false }), true);
    // Auch die Maus zieht nicht aus einem Auswahlfeld heraus.
    const select = hitOn({ [SORT_IGNORE_SELECTOR]: inside });
    assert.equal(mayStartDrag(select, item, { ...rules, mouse: true }), false);
});

test("Eigene Ausnahmeliste ersetzt die Standardliste", () => {
    const link = hitOn({ [SORT_IGNORE_SELECTOR]: inside });
    assert.equal(mayStartDrag(link, item, { ignore: "[menu]" }), true);
    assert.equal(mayStartDrag(hitOn({ "[menu]": inside }), item, { ignore: "[menu]" }), false);
    assert.equal(mayStartDrag(link, item, { ignore: null }), true);
});

test("Ziel ist das nächste passende Element in der Wurzel, nie das gezogene", () => {
    const dragged = { name: "dragged" };
    const other = { name: "other", inRoot: true };
    const foreign = { name: "foreign", inRoot: false };
    const root = { contains: (node) => node?.inRoot === true };

    assert.equal(pickTarget(hitOn({ "[row]": other }), "[row]", root, dragged), other);
    assert.equal(pickTarget(hitOn({ "[row]": dragged }), "[row]", root, dragged), null);
    assert.equal(pickTarget(hitOn({ "[row]": foreign }), "[row]", root, dragged), null);
    assert.equal(pickTarget(hitOn({}), "[row]", root, dragged), null);
    // Zeiger außerhalb des Fensters: elementFromPoint liefert null.
    assert.equal(pickTarget(null, "[row]", root, dragged), null);
});

test("Über der Zielmitte wird davor eingefügt, darunter dahinter", () => {
    assert.equal(dropsBefore(rows[1], { x: 10, y: 150 }), true);
    assert.equal(dropsBefore(rows[1], { x: 10, y: 160 }), false);
    assert.equal(dropsBefore(rows[1], { x: 10, y: 175 }), false);
    assert.equal(dropsBefore({ left: 100, top: 0, width: 80, height: 20 }, { x: 139, y: 5 }, "x"), true);
    assert.equal(dropsBefore({ left: 100, top: 0, width: 80, height: 20 }, { x: 141, y: 5 }, "x"), false);
});

test("Einfügeposition: vor dem ersten Element, dessen Mitte hinter dem Zeiger liegt", () => {
    assert.equal(insertionIndex(rows, { x: 0, y: 20 }), 0);
    assert.equal(insertionIndex(rows, { x: 0, y: 119 }), 0);
    assert.equal(insertionIndex(rows, { x: 0, y: 121 }), 1);
    assert.equal(insertionIndex(rows, { x: 0, y: 205 }), 3);
    assert.equal(insertionIndex(rows, { x: 0, y: 999 }), 4);
    assert.equal(insertionIndex([], { x: 0, y: 0 }), 0);
});

test("Index nach dem Zug: vor/hinter dem Ziel, die eigene Lücke eingerechnet", () => {
    // nach oben
    assert.equal(indexAfterMove(3, 1, true), 1);
    assert.equal(indexAfterMove(3, 1, false), 2);
    // nach unten
    assert.equal(indexAfterMove(0, 2, true), 1);
    assert.equal(indexAfterMove(0, 2, false), 2);
    // direkt neben sich selbst ändert nichts
    assert.equal(indexAfterMove(2, 3, true), 2);
    assert.equal(indexAfterMove(2, 1, false), 2);
});

test("Vorgänger an der neuen Stelle, als Index der alten Liste", () => {
    // [a b c d]: d an die Spitze → kein Vorgänger
    assert.equal(predecessorIndex(3, 0), -1);
    // d auf Platz 1 → hinter a
    assert.equal(predecessorIndex(3, 1), 0);
    // a auf Platz 2 → hinter c
    assert.equal(predecessorIndex(0, 2), 2);
    // a ans Ende → hinter d
    assert.equal(predecessorIndex(0, 3), 3);
    // unverändert → der bisherige Vorgänger
    assert.equal(predecessorIndex(2, 2), 1);
});

test("Zugrichtung wie im Backlog: nach oben vor das Ziel, nach unten dahinter", () => {
    const predecessorOf = (from, over) =>
        predecessorIndex(from, indexAfterMove(from, over, over < from));
    assert.equal(predecessorOf(3, 1), 0);
    assert.equal(predecessorOf(3, 0), -1);
    assert.equal(predecessorOf(0, 2), 2);
    assert.equal(predecessorOf(1, 3), 3);
});

test("Randscrollen: in der Mitte nichts, am Rand proportional, außerhalb voll", () => {
    // sichtbarer Bereich 100…500, Zone 48 px, höchstens 18 px je Frame
    assert.equal(edgeScrollStep(300, 100, 500), 0);
    assert.equal(edgeScrollStep(148, 100, 500), 0);
    assert.equal(edgeScrollStep(124, 100, 500), -9);
    assert.equal(edgeScrollStep(100, 100, 500), -18);
    assert.equal(edgeScrollStep(20, 100, 500), -18);
    assert.equal(edgeScrollStep(476, 100, 500), 9);
    assert.equal(edgeScrollStep(900, 100, 500), 18);
});

test("Randscrollen: niedriger Bereich teilt sich in zwei halbe Zonen", () => {
    assert.equal(edgeScrollStep(120, 100, 140), 0);
    assert.equal(edgeScrollStep(100, 100, 140), -18);
    assert.equal(edgeScrollStep(140, 100, 140), 18);
    assert.equal(edgeScrollStep(100, 100, 100), 0);
});

test("Randscrollen-Achsen: eine Achse oder beide", () => {
    assert.deepEqual(scrollAxes("y"), ["y"]);
    assert.deepEqual(scrollAxes("x"), ["x"]);
    assert.deepEqual(scrollAxes("both"), ["x", "y"]);
});

test("Sichtbarer Abschnitt: der Scrollbereich aufs Fenster beschnitten, die Seite ganz", () => {
    const viewport = { width: 800, height: 600 };
    // Ragt oben und rechts aus dem Fenster.
    const box = { left: 100, top: -50, right: 1000, bottom: 300 };
    assert.deepEqual(visibleSpan(box, viewport), { start: 0, end: 300 });
    assert.deepEqual(visibleSpan(box, viewport, "x"), { start: 100, end: 800 });
    assert.deepEqual(visibleSpan(null, viewport, "y"), { start: 0, end: 600 });
    assert.deepEqual(visibleSpan(null, viewport, "x"), { start: 0, end: 800 });
});

// ── Ablauf gegen ein Minimal-DOM ────────────────────────────────────────

/** Gerade genug DOM für pointerSort(): Baum, Klassen, Selektoren `tag` und `[attr]`. */
class FakeElement {
    constructor(tagName, attrs = []) {
        this.tagName = tagName;
        this.attrs = new Set(attrs);
        this.children = [];
        this.parentNode = null;
        this.listeners = [];
        this.scrollTop = 0;
        this.scrollHeight = 0;
        this.clientHeight = 0;
        this.scrollLeft = 0;
        this.scrollWidth = 0;
        this.clientWidth = 0;
        const classes = new Set();
        this.classes = classes;
        this.classList = {
            add: (...names) => names.forEach((name) => classes.add(name)),
            remove: (...names) => names.forEach((name) => classes.delete(name)),
        };
    }
    get ownerDocument() {
        let node = this.parentNode;
        while (node instanceof FakeElement) node = node.parentNode;
        return node;
    }
    get parentElement() {
        return this.parentNode instanceof FakeElement ? this.parentNode : null;
    }
    sibling(offset) {
        const siblings = this.parentNode.children;
        return siblings[siblings.indexOf(this) + offset] ?? null;
    }
    get nextSibling() {
        return this.sibling(1);
    }
    get nextElementSibling() {
        return this.sibling(1);
    }
    get previousElementSibling() {
        return this.sibling(-1);
    }
    matches(selector) {
        return selector.split(",").some((part) => {
            const simple = part.trim();
            return simple.startsWith("[") ? this.attrs.has(simple.slice(1, -1)) : this.tagName === simple;
        });
    }
    closest(selector) {
        for (let node = this; node instanceof FakeElement; node = node.parentNode) {
            if (node.matches(selector)) return node;
        }
        return null;
    }
    contains(other) {
        for (let node = other; node; node = node.parentNode) {
            if (node === this) return true;
        }
        return false;
    }
    querySelectorAll(selector) {
        return this.children.flatMap((child) => [
            ...(child.matches(selector) ? [child] : []),
            ...child.querySelectorAll(selector),
        ]);
    }
    remove() {
        const siblings = this.parentNode?.children;
        if (siblings) siblings.splice(siblings.indexOf(this), 1);
        // Wie im Browser: Umhängen nimmt dem Element den Fokus.
        const doc = this.ownerDocument;
        if (doc && this.contains(doc.activeElement)) doc.activeElement = null;
        this.parentNode = null;
    }
    insertBefore(node, ref) {
        const next = ref === node ? node.nextSibling : ref;
        node.remove();
        const index = next ? this.children.indexOf(next) : this.children.length;
        this.children.splice(index, 0, node);
        node.parentNode = this;
    }
    append(...nodes) {
        nodes.forEach((node) => this.insertBefore(node, null));
    }
    before(node) {
        this.parentNode.insertBefore(node, this);
    }
    after(node) {
        this.parentNode.insertBefore(node, this.nextSibling);
    }
    getBoundingClientRect() {
        return this.box ? this.box() : this.parentNode.getBoundingClientRect();
    }
    scrollBy({ top = 0, left = 0 }) {
        this.scrollTop += top;
        this.scrollLeft += left;
    }
    focus() {
        this.ownerDocument.activeElement = this;
    }
    setPointerCapture(pointerId) {
        this.ownerDocument.captured = { node: this, pointerId };
    }
    releasePointerCapture() {
        this.ownerDocument.captured = null;
    }
    addEventListener(type, handler, capture = false) {
        this.listeners.push({ type, handler, capture });
    }
    removeEventListener(type, handler, capture = false) {
        this.listeners = this.listeners.filter(
            (l) => !(l.type === type && l.handler === handler && l.capture === capture),
        );
    }
}

class FakeDocument {
    constructor() {
        this.listeners = [];
        this.ownerDocument = null;
        this.activeElement = null;
        this.captured = null;
        this.documentElement = new FakeElement("html");
        this.documentElement.parentNode = this;
        this.scrollingElement = this.documentElement;
        /** Treffer-Kandidaten für elementFromPoint, der erste passende gewinnt. */
        this.hittable = () => [];
        const frames = [];
        const timers = [];
        this.defaultView = {
            listeners: [],
            parentNode: null,
            addEventListener(type, handler, capture = false) {
                this.listeners.push({ type, handler, capture });
            },
            innerHeight: 600,
            innerWidth: 800,
            requestAnimationFrame: (callback) => frames.push(callback),
            cancelAnimationFrame: () => frames.splice(0),
            setTimeout: (callback) => timers.push(callback),
            getComputedStyle: (el) => ({
                overflowY: el.overflowY ?? "visible",
                overflowX: el.overflowX ?? "visible",
            }),
            /** Einen Frame bzw. alle fälligen Timer ausführen. */
            frame: () => frames.splice(0).forEach((callback) => callback()),
            flush: () => timers.splice(0).forEach((callback) => callback()),
        };
        // Ereignisse laufen wie im Browser bis zum Fenster.
        this.parentNode = this.defaultView;
    }
    contains(other) {
        return FakeElement.prototype.contains.call(this, other);
    }
    elementFromPoint(x, y) {
        return (
            this.hittable().find((el) => {
                const box = el.getBoundingClientRect();
                return x >= box.left && x < box.left + box.width && y >= box.top && y < box.top + box.height;
            }) ?? null
        );
    }
    addEventListener(type, handler, capture = false) {
        FakeElement.prototype.addEventListener.call(this, type, handler, capture);
    }
    removeEventListener(type, handler, capture = false) {
        FakeElement.prototype.removeEventListener.call(this, type, handler, capture);
    }
}

// pointerSort() prüft Ereignisziele mit `instanceof Element`.
globalThis.Element = FakeElement;

/** Ereignis durch Capture- und Bubble-Phase schicken. */
function fire(target, type, props = {}) {
    const event = {
        type,
        target,
        defaultPrevented: false,
        stopped: false,
        preventDefault() {
            this.defaultPrevented = true;
        },
        stopPropagation() {
            this.stopped = true;
        },
        ...props,
    };
    const path = [];
    for (let node = target; node; node = node.parentNode) path.push(node);
    const phases = [
        [[...path].reverse(), true],
        [path, false],
    ];
    for (const [nodes, capture] of phases) {
        for (const node of nodes) {
            node.listeners
                .filter((l) => l.type === type && l.capture === capture)
                .forEach((l) => l.handler(event));
            if (event.stopped) return event;
        }
    }
    return event;
}

const pointer = (target, type, y, props = {}) =>
    fire(target, type, { pointerId: 1, pointerType: "mouse", button: 0, clientX: 20, clientY: y, ...props });

/**
 * Liste mit vier Zeilen à 40 px ab y = 100; jede Zeile trägt Griff, Text und
 * einen Knopf. Die Rechtecke folgen der DOM-Reihenfolge und dem Scrollstand.
 */
function makeList() {
    const doc = new FakeDocument();
    const list = new FakeElement("ul", ["data-list"]);
    list.box = () => ({ left: 0, top: 100, width: 200, height: 160, bottom: 260 });
    doc.documentElement.append(list);

    const items = ["a", "b", "c", "d"].map((name) => {
        const row = new FakeElement("li", ["data-row"]);
        row.name = name;
        row.handle = new FakeElement("span", ["data-handle"]);
        row.text = new FakeElement("span");
        row.button = new FakeElement("button");
        row.append(row.handle, row.text, row.button);
        row.box = () => {
            const top = 100 - list.scrollTop + list.children.indexOf(row) * 40;
            return { left: 0, top, width: 200, height: 40 };
        };
        list.append(row);
        return row;
    });
    doc.hittable = () => list.children;

    const drops = [];
    const order = () => list.children.map((row) => row.name).join("");
    return { doc, view: doc.defaultView, list, items, drops, order, html: doc.documentElement };
}

const MARK = {
    item: "[data-row]",
    draggingClass: ["dragging"],
    targetClass: ["over"],
};
const LIVE = { item: "[data-row]", handle: "[data-handle]", mode: "live", draggingClass: ["dragging"] };

test("Klick bleibt Klick, solange die Schwelle nicht überschritten ist", () => {
    const { doc, list, items, drops } = makeList();
    pointerSort(list, { ...MARK, onDrop: (drop) => drops.push(drop) });

    pointer(items[1].text, "pointerdown", 160);
    pointer(items[1].text, "pointermove", 163);
    assert.equal(items[1].classes.has("dragging"), false);
    pointer(items[1].text, "pointerup", 163);

    assert.equal(fire(items[1].text, "click").defaultPrevented, false);
    assert.deepEqual(drops, []);
    // Ohne laufenden Druck hört am Dokument niemand mehr mit.
    assert.deepEqual(doc.listeners, []);
});

test("mark: Ziel wird markiert, Loslassen meldet Ziel, Seite und Indizes", () => {
    const { doc, list, items, drops, html, order } = makeList();
    const [a, , c, d] = items;
    pointerSort(list, { ...MARK, onDrop: (drop) => drops.push(drop) });

    pointer(a.text, "pointerdown", 120);
    const move = pointer(a.text, "pointermove", 230);
    assert.equal(move.defaultPrevented, true);
    assert.equal(a.classes.has("dragging"), true);
    assert.equal(html.classes.has(SORTING_CLASS), true);
    assert.equal(doc.captured.node, a);
    assert.equal(d.classes.has("over"), true);

    pointer(a.text, "pointermove", 190);
    assert.equal(d.classes.has("over"), false);
    assert.equal(c.classes.has("over"), true);

    // untere Hälfte von c → dahinter
    pointer(a.text, "pointerup", 215);
    assert.equal(drops.length, 1);
    assert.equal(drops[0].item, a);
    assert.equal(drops[0].list, list);
    assert.equal(drops[0].target, c);
    assert.deepEqual([drops[0].before, drops[0].from, drops[0].to], [false, 0, 2]);

    // "mark" hängt selbst nichts um und räumt alle Marken ab.
    assert.equal(order(), "abcd");
    assert.equal(a.classes.size + c.classes.size + html.classes.size, 0);
    assert.equal(doc.captured, null);
    assert.deepEqual(doc.listeners, []);
});

test("mark: das gezogene Element ist kein Ziel, Loslassen daneben meldet nichts", () => {
    const { list, items, drops } = makeList();
    pointerSort(list, { ...MARK, onDrop: (drop) => drops.push(drop) });

    pointer(items[1].text, "pointerdown", 150);
    pointer(items[1].text, "pointermove", 170);
    assert.equal(items[1].classes.has("over"), false);
    pointer(items[1].text, "pointerup", 170);

    pointer(items[1].text, "pointerdown", 150);
    pointer(items[1].text, "pointermove", 230);
    assert.equal(items[3].classes.has("over"), true);
    pointer(items[1].text, "pointerup", 900);
    assert.equal(items[3].classes.has("over"), false);

    assert.deepEqual(drops, []);
});

test("mark mit side=direction: nach oben vor das Ziel, nach unten dahinter", () => {
    const { list, items, drops } = makeList();
    pointerSort(list, { ...MARK, side: "direction", onDrop: (drop) => drops.push(drop) });

    // d auf die UNTERE Hälfte von b — trotzdem davor
    pointer(items[3].text, "pointerdown", 240);
    pointer(items[3].text, "pointermove", 175);
    pointer(items[3].text, "pointerup", 175);
    assert.deepEqual([drops[0].before, drops[0].from, drops[0].to], [true, 3, 1]);

    // a auf die OBERE Hälfte von c — trotzdem dahinter
    pointer(items[0].text, "pointerdown", 120);
    pointer(items[0].text, "pointermove", 185);
    pointer(items[0].text, "pointerup", 185);
    assert.deepEqual([drops[1].before, drops[1].from, drops[1].to], [false, 0, 2]);
});

test("mark mit eigenem Ziel: Ziel ist kein Listenplatz, auch der eigene Behälter zählt", () => {
    const { doc, list, items, drops } = makeList();
    const board = new FakeElement("div");
    const other = new FakeElement("section", ["data-column"]);
    other.box = () => ({ left: 300, top: 100, width: 200, height: 160 });
    doc.documentElement.append(board);
    board.append(list, other);
    list.attrs.add("data-column");
    doc.hittable = () => [...list.children, list, other];

    pointerSort(board, {
        ...MARK,
        target: "[data-column]",
        axis: "both",
        onDrop: (drop) => drops.push(drop),
    });

    pointer(items[0].text, "pointerdown", 120);
    // Zug quer: die Karte liegt weiter unter dem Zeiger, Ziel ist ihre Spalte.
    pointer(items[0].text, "pointermove", 120, { clientX: 30 });
    assert.equal(list.classes.has("over"), true);
    pointer(items[0].text, "pointermove", 120, { clientX: 350 });
    assert.equal(list.classes.has("over"), false);
    assert.equal(other.classes.has("over"), true);
    pointer(items[0].text, "pointerup", 120, { clientX: 350 });

    assert.equal(drops[0].target, other);
    assert.equal(drops[0].to, -1);
});

test("Nach dem Zug wird genau ein Klick verschluckt — auch vor Dokument-Listenern", () => {
    const { doc, view, list, items } = makeList();
    let opened = 0;
    // Der Eintrags-Dialog der App hört am Dokument in der Capture-Phase.
    doc.addEventListener("click", () => opened++, true);
    pointerSort(list, MARK);

    pointer(items[0].text, "pointerdown", 120);
    pointer(items[0].text, "pointermove", 190);
    pointer(items[0].text, "pointerup", 190);

    assert.equal(fire(items[0].text, "click").defaultPrevented, true);
    assert.equal(fire(items[0].text, "click").defaultPrevented, false);
    assert.equal(opened, 1);

    // Bleibt der Klick aus, darf die Sperre nicht auf den nächsten fallen.
    pointer(items[0].text, "pointerdown", 120);
    pointer(items[0].text, "pointermove", 190);
    pointer(items[0].text, "pointerup", 190);
    view.flush();
    assert.equal(fire(items[0].text, "click").defaultPrevented, false);
});

test("live: die Zeile wandert während des Zugs, Loslassen meldet alt und neu", () => {
    const { list, items, drops, order } = makeList();
    pointerSort(list, { ...LIVE, onDrop: (drop) => drops.push(drop) });

    pointer(items[0].handle, "pointerdown", 120);
    pointer(items[0].handle, "pointermove", 165);
    assert.equal(order(), "bacd");
    pointer(items[0].handle, "pointermove", 205);
    assert.equal(order(), "bcad");
    pointer(items[0].handle, "pointermove", 400);
    assert.equal(order(), "bcda");
    pointer(items[0].handle, "pointermove", 130);
    assert.equal(order(), "bacd");
    pointer(items[0].handle, "pointerup", 130);

    assert.deepEqual([drops[0].from, drops[0].to, drops[0].target], [0, 1, null]);
    assert.equal(items[0].classes.size, 0);
});

test("live: Escape stellt die Reihenfolge wieder her und meldet nichts", () => {
    const { doc, list, items, drops, html, order } = makeList();
    pointerSort(list, { ...LIVE, onDrop: (drop) => drops.push(drop) });

    pointer(items[0].handle, "pointerdown", 120);
    pointer(items[0].handle, "pointermove", 205);
    assert.equal(order(), "bcad");

    const escape = fire(doc.documentElement, "keydown", { key: "Escape" });
    // Der Dialog um die Liste darf an derselben Taste nicht schließen.
    assert.equal(escape.defaultPrevented, true);
    assert.equal(order(), "abcd");
    assert.equal(items[0].classes.size + html.classes.size, 0);

    // Der Zeiger ist noch unten: weitere Bewegung zieht nicht erneut …
    pointer(items[0].handle, "pointermove", 240);
    assert.equal(order(), "abcd");
    // … und der Klick beim Loslassen löst nichts aus.
    pointer(items[0].handle, "pointerup", 240);
    assert.equal(fire(items[0].handle, "click").defaultPrevented, true);
    assert.deepEqual(drops, []);
    assert.deepEqual(doc.listeners, []);
});

test("Escape ohne laufenden Zug bleibt unberührt", () => {
    const { doc, list, items } = makeList();
    pointerSort(list, LIVE);

    pointer(items[0].handle, "pointerdown", 120);
    assert.equal(fire(doc.documentElement, "keydown", { key: "Escape" }).defaultPrevented, false);
    pointer(items[0].handle, "pointerup", 120);
});

test("live: pointercancel stellt die Reihenfolge wieder her", () => {
    const { doc, list, items, drops, order } = makeList();
    pointerSort(list, { ...LIVE, onDrop: (drop) => drops.push(drop) });

    pointer(items[3].handle, "pointerdown", 240);
    pointer(items[3].handle, "pointermove", 110);
    assert.equal(order(), "dabc");
    pointer(items[3].handle, "pointercancel", 110);

    assert.equal(order(), "abcd");
    assert.equal(items[3].classes.size, 0);
    assert.deepEqual(drops, []);
    assert.deepEqual(doc.listeners, []);
});

test("live: der Fokus am Griff übersteht das Umhängen", () => {
    const { doc, list, items } = makeList();
    pointerSort(list, LIVE);
    items[0].handle.focus();

    pointer(items[0].handle, "pointerdown", 120);
    pointer(items[0].handle, "pointermove", 205);
    assert.equal(doc.activeElement, items[0].handle);
    pointer(items[0].handle, "pointerup", 205);
    assert.equal(doc.activeElement, items[0].handle);
});

test("Zug nur am Griff; mit mouseAnywhere zieht die Maus überall, der Finger nicht", () => {
    const strict = makeList();
    pointerSort(strict.list, LIVE);
    pointer(strict.items[0].text, "pointerdown", 120);
    pointer(strict.items[0].text, "pointermove", 205);
    pointer(strict.items[0].text, "pointerup", 205);
    assert.equal(strict.order(), "abcd");

    const loose = makeList();
    pointerSort(loose.list, { ...LIVE, mouseAnywhere: true });
    const touch = { pointerType: "touch", pointerId: 7 };
    pointer(loose.items[0].text, "pointerdown", 120, touch);
    pointer(loose.items[0].text, "pointermove", 205, touch);
    pointer(loose.items[0].text, "pointerup", 205, touch);
    assert.equal(loose.order(), "abcd");

    pointer(loose.items[0].handle, "pointerdown", 120, touch);
    pointer(loose.items[0].handle, "pointermove", 165, touch);
    pointer(loose.items[0].handle, "pointerup", 165, touch);
    assert.equal(loose.order(), "bacd");

    // a steht jetzt auf Platz 2 (y = 140…180); die Maus zieht am Text zurück.
    pointer(loose.items[0].text, "pointerdown", 160);
    pointer(loose.items[0].text, "pointermove", 110);
    pointer(loose.items[0].text, "pointerup", 110);
    assert.equal(loose.order(), "abcd");
});

test("Kein Zug aus Knöpfen, mit der rechten Maustaste oder bei canDrag = false", () => {
    const { list, items, order } = makeList();
    pointerSort(list, {
        ...LIVE,
        mouseAnywhere: true,
        canDrag: (row) => row.name !== "d",
    });

    pointer(items[0].button, "pointerdown", 120);
    pointer(items[0].button, "pointermove", 205);
    pointer(items[0].button, "pointerup", 205);

    pointer(items[0].handle, "pointerdown", 120, { button: 2 });
    pointer(items[0].handle, "pointermove", 205, { button: 2 });
    pointer(items[0].handle, "pointerup", 205, { button: 2 });

    pointer(items[3].handle, "pointerdown", 240);
    pointer(items[3].handle, "pointermove", 110);
    pointer(items[3].handle, "pointerup", 110);

    assert.equal(order(), "abcd");
});

test("Ein zweiter Finger stört den laufenden Zug nicht", () => {
    const { list, items, order } = makeList();
    pointerSort(list, LIVE);
    const first = { pointerType: "touch", pointerId: 3 };
    const second = { pointerType: "touch", pointerId: 4 };

    pointer(items[0].handle, "pointerdown", 120, first);
    pointer(items[0].handle, "pointermove", 165, first);
    pointer(items[2].handle, "pointerdown", 200, second);
    pointer(items[2].handle, "pointermove", 110, second);
    assert.equal(order(), "bacd");
    pointer(items[0].handle, "pointermove", 205, first);
    assert.equal(order(), "bcad");
    pointer(items[0].handle, "pointerup", 205, first);
});

test("Delegation über document: Liste je Element über den list-Selektor", () => {
    const { doc, items, drops, order } = makeList();
    pointerSort(doc, { ...LIVE, list: "[data-list]", onDrop: (drop) => drops.push(drop) });

    pointer(items[1].handle, "pointerdown", 160);
    pointer(items[1].handle, "pointermove", 110);
    pointer(items[1].handle, "pointerup", 110);

    assert.equal(order(), "bacd");
    assert.deepEqual([drops[0].from, drops[0].to], [1, 0]);
});

test("Am Rand des Scrollbereichs scrollt die Liste mit und sortiert weiter", () => {
    const { view, list, items, order } = makeList();
    list.overflowY = "auto";
    list.scrollHeight = 400;
    list.clientHeight = 160;
    pointerSort(list, LIVE);

    // Sichtbar 100…260; bei y = 255 steht der Zeiger 43 px tief in der Randzone.
    pointer(items[0].handle, "pointerdown", 120);
    pointer(items[0].handle, "pointermove", 255);
    assert.equal(order(), "bcda");
    view.frame();
    assert.equal(list.scrollTop, 16);
    view.frame();
    assert.equal(list.scrollTop, 32);

    // In der Mitte bleibt der Scrollstand stehen.
    pointer(items[0].handle, "pointermove", 180);
    view.frame();
    assert.equal(list.scrollTop, 32);

    pointer(items[0].handle, "pointerup", 180);
    view.frame();
    assert.equal(list.scrollTop, 32);
});

test("Ohne eigenen Scrollbereich scrollt die Seite", () => {
    const { view, list, items, html } = makeList();
    pointerSort(list, LIVE);

    pointer(items[0].handle, "pointerdown", 120);
    pointer(items[0].handle, "pointermove", 590);
    view.frame();
    assert.equal(list.scrollTop, 0);
    assert.equal(html.scrollTop, 14);
    pointer(items[0].handle, "pointerup", 590);
});

// ── Zug auf ein Ziel in einer Matrix (Dienstplan, Schnellbuchung) ───────

/**
 * Eine Zeile mit sechs Zellen à 100 px in einem 300 px breiten, waagerecht
 * scrollenden Rahmen (x = 100…400); in der ersten Zelle liegt ein Abzeichen
 * mit Griff. Verdrahtet wird wie im Dienstplan über das Dokument.
 */
function makeMatrix() {
    const doc = new FakeDocument();
    const frame = new FakeElement("div");
    frame.overflowX = "auto";
    frame.scrollWidth = 600;
    frame.clientWidth = 300;
    frame.box = () => ({ left: 100, top: 100, right: 400, bottom: 160 });
    doc.documentElement.append(frame);

    const cells = [0, 1, 2, 3, 4, 5].map((index) => {
        const cell = new FakeElement("div", ["data-cell"]);
        cell.box = () => ({ left: 100 - frame.scrollLeft + index * 100, top: 100, width: 100, height: 60 });
        frame.append(cell);
        return cell;
    });

    const badge = new FakeElement("div", ["data-badge"]);
    badge.grip = new FakeElement("span", ["data-grip"]);
    badge.text = new FakeElement("span");
    badge.append(badge.grip, badge.text);
    badge.box = () => ({ left: 105 - frame.scrollLeft, top: 105, width: 90, height: 20 });
    cells[0].append(badge);

    // Wie im Browser: was der Rahmen abschneidet, ist nicht zu treffen.
    doc.hittable = () => [badge, ...cells];
    const hitTest = doc.elementFromPoint.bind(doc);
    doc.elementFromPoint = (x, y) => (x >= 100 && x < 400 ? hitTest(x, y) : null);

    const drops = [];
    const options = {
        item: "[data-badge]",
        list: "[data-cell]",
        handle: "[data-grip]",
        mouseAnywhere: true,
        target: "[data-cell]",
        axis: "both",
        draggingClass: ["dragging"],
        targetClass: ["over"],
        onDrop: (drop) => drops.push(drop),
    };
    return { doc, view: doc.defaultView, frame, cells, badge, drops, options, html: doc.documentElement };
}

test("scrollAxis both: am Rand scrollt die Matrix waagerecht mit, die Zielmarke folgt", () => {
    const { doc, view, frame, cells, badge, drops, options, html } = makeMatrix();
    pointerSort(doc, { ...options, scrollAxis: "both" });

    pointer(badge.text, "pointerdown", 115, { clientX: 150 });
    // Sichtbar x = 100…400; bei 390 steht der Zeiger 38 px tief in der Randzone.
    pointer(badge.text, "pointermove", 115, { clientX: 390 });
    assert.equal(cells[2].classes.has("over"), true);

    view.frame();
    assert.equal(frame.scrollLeft, 14);
    assert.equal(html.scrollTop, 0);
    // Die Zellen sind unter dem stehenden Zeiger weitergewandert.
    assert.equal(cells[2].classes.has("over"), false);
    assert.equal(cells[3].classes.has("over"), true);
    view.frame();
    assert.equal(frame.scrollLeft, 28);

    // Am linken Rand geht es zurück, in der Mitte bleibt der Stand stehen.
    pointer(badge.text, "pointermove", 115, { clientX: 100 });
    view.frame();
    assert.equal(frame.scrollLeft, 10);
    pointer(badge.text, "pointermove", 115, { clientX: 250 });
    view.frame();
    assert.equal(frame.scrollLeft, 10);

    // Jede Achse hat ihren Scrollbereich: senkrecht scrollt hier die Seite.
    pointer(badge.text, "pointermove", 590, { clientX: 250 });
    view.frame();
    assert.deepEqual([frame.scrollLeft, html.scrollTop], [10, 14]);

    pointer(badge.text, "pointerup", 115, { clientX: 250 });
    assert.equal(drops.length, 1);
    assert.equal(drops[0].item, badge);
    assert.equal(drops[0].target, cells[1]);
    assert.equal(drops[0].to, -1);

    view.frame();
    assert.deepEqual([frame.scrollLeft, html.scrollTop], [10, 14]);
    assert.deepEqual(doc.listeners.filter((l) => l.type !== "pointerdown" && l.type !== "selectstart"), []);
});

test("Ohne scrollAxis bleibt es beim senkrechten Randscrollen", () => {
    const { doc, view, frame, badge, options, html } = makeMatrix();
    pointerSort(doc, options);

    pointer(badge.text, "pointerdown", 115, { clientX: 150 });
    pointer(badge.text, "pointermove", 590, { clientX: 390 });
    view.frame();
    assert.deepEqual([frame.scrollLeft, html.scrollTop], [0, 14]);
    pointer(badge.text, "pointerup", 590, { clientX: 390 });
});

test("Griff mit eigenem Ziel: Finger und Stift ziehen nur am Griff, die Maus überall", () => {
    const { doc, cells, badge, drops, options } = makeMatrix();
    pointerSort(doc, options);
    const touch = { pointerType: "touch", pointerId: 5 };
    const pen = { pointerType: "pen", pointerId: 6 };

    // Finger auf dem Abzeichen: kein Zug (die Matrix bleibt wischbar), der Tipp bleibt ein Klick.
    pointer(badge.text, "pointerdown", 115, { ...touch, clientX: 150 });
    pointer(badge.text, "pointermove", 115, { ...touch, clientX: 250 });
    assert.equal(badge.classes.has("dragging"), false);
    pointer(badge.text, "pointerup", 115, { ...touch, clientX: 250 });
    assert.equal(fire(badge.text, "click").defaultPrevented, false);

    pointer(badge.grip, "pointerdown", 115, { ...touch, clientX: 110 });
    pointer(badge.grip, "pointermove", 115, { ...touch, clientX: 250 });
    assert.equal(badge.classes.has("dragging"), true);
    assert.equal(cells[1].classes.has("over"), true);
    pointer(badge.grip, "pointerup", 115, { ...touch, clientX: 250 });
    assert.equal(badge.classes.size + cells[1].classes.size, 0);

    pointer(badge.grip, "pointerdown", 115, { ...pen, clientX: 110 });
    pointer(badge.grip, "pointermove", 115, { ...pen, clientX: 350 });
    pointer(badge.grip, "pointerup", 115, { ...pen, clientX: 350 });

    pointer(badge.text, "pointerdown", 115, { clientX: 150 });
    pointer(badge.text, "pointermove", 115, { clientX: 250 });
    pointer(badge.text, "pointerup", 115, { clientX: 250 });
    // Der Klick nach dem Zug darf den Dialog des Abzeichens nicht öffnen.
    assert.equal(fire(badge.text, "click").defaultPrevented, true);

    assert.deepEqual(drops.map((drop) => drop.target), [cells[1], cells[2], cells[1]]);
    assert.deepEqual(drops.map((drop) => drop.to), [-1, -1, -1]);
});

test("Ablegen in der eigenen Zelle meldet sie als Ziel — der Aufrufer entscheidet", () => {
    const { doc, cells, badge, drops, options } = makeMatrix();
    pointerSort(doc, options);

    pointer(badge.text, "pointerdown", 115, { clientX: 150 });
    pointer(badge.text, "pointermove", 140, { clientX: 150 });
    assert.equal(cells[0].classes.has("over"), true);
    pointer(badge.text, "pointerup", 140, { clientX: 150 });

    assert.equal(drops[0].target, cells[0]);
    assert.equal(drops[0].item.closest("[data-cell]"), drops[0].target);
});
