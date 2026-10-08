/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : pointer-sort.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Sortieren und Ziehen über Pointer Events — ein Codepfad für Maus, Touch und
 * Stift (HTML5-Drag-and-drop kennt weder Touch noch Stift; Gate
 * PointerSortRuleTest). Oben die Entscheidungslogik ohne DOM, damit sie unter
 * node:test prüfbar ist; darunter die Verdrahtung `pointerSort()`.
 *
 * Zwei Betriebsarten:
 *   "live" – das gezogene Element wandert schon während des Zugs an seine neue
 *            Stelle (Reihenfolge = DOM); ein Abbruch stellt sie wieder her.
 *   "mark" – das Ziel unter dem Zeiger wird markiert, erst `onDrop` handelt
 *            (Request, Fachaktion, Einfügen vor/hinter dem Ziel).
 *
 * Griffe brauchen `touch-action: none` (Klasse `touch-none`), sonst übernimmt
 * der Browser die Geste als Scrollen und bricht den Zug ab. Am Rand des
 * Scrollbereichs scrollt der Zug mit: senkrecht, mit `scrollAxis` auch
 * waagerecht (Matrix).
 */

/**
 * Elemente, aus denen heraus kein Zug beginnt.
 * `[data-no-drag]` erlaubt Aufrufern, weitere Bereiche auszunehmen.
 */
export const SORT_IGNORE_SELECTOR =
    "button, a, input, select, textarea, label, summary, [contenteditable], [data-no-drag]";

/** Ab dieser Bewegung (px) ist eine Zeigergeste ein Zug und kein Klick mehr. */
export const SORT_THRESHOLD = 6;

/** Randzone (px), in der beim Ziehen mitgescrollt wird. */
export const SCROLL_ZONE = 48;

/** Größter Scroll-Schritt (px) je Frame. */
export const SCROLL_MAX_STEP = 18;

/** Klasse am <html>, solange gezogen wird (Zeiger, keine Textmarkierung — app.css). */
export const SORTING_CLASS = "wd-sorting";

/**
 * @typedef {{ x: number, y: number }} Point
 * @typedef {{ left: number, top: number, width: number, height: number }} Rect
 * @typedef {{ left: number, top: number, right: number, bottom: number }} Box
 * @typedef {"x" | "y" | "both"} Axis
 * @typedef {{ closest(selector: string): unknown }} Closable
 * @typedef {{ contains(node: unknown): boolean }} Container
 */

/**
 * Ist aus dem Druck ein Zug geworden?
 *
 * @param {Point} start
 * @param {Point} current
 * @param {number} [threshold]
 * @param {Axis} [axis] "x"/"y" zählt nur die Bewegung längs der Liste
 * @returns {boolean}
 */
export function exceedsThreshold(start, current, threshold = SORT_THRESHOLD, axis = "both") {
    const dx = current.x - start.x;
    const dy = current.y - start.y;
    if (axis === "x") return Math.abs(dx) >= threshold;
    if (axis === "y") return Math.abs(dy) >= threshold;
    return Math.hypot(dx, dy) >= threshold;
}

/**
 * Darf ein Druck auf `target` einen Zug des Elements beginnen? Mit Griff nur
 * am Griff — die Maus darf mit `mouseAnywhere` überall ansetzen, Finger und
 * Stift nie (die Zeile muss scrollbar bleiben). Bedienelemente im Element
 * behalten ihren Klick.
 *
 * @param {Closable} target Element unter dem Zeiger
 * @param {Container} item Das ziehbare Element
 * @param {{ handle?: string | null, ignore?: string | null, mouse?: boolean, mouseAnywhere?: boolean }} [rules]
 * @returns {boolean}
 */
export function mayStartDrag(
    target,
    item,
    { handle = null, ignore = SORT_IGNORE_SELECTOR, mouse = true, mouseAnywhere = false } = {},
) {
    if (handle) {
        const grip = target.closest(handle);
        if (grip != null && item.contains(grip)) return true;
        if (!(mouse && mouseAnywhere)) return false;
    }
    if (!ignore) return true;
    const blocked = target.closest(ignore);
    return blocked == null || blocked === item || !item.contains(blocked);
}

/**
 * Ziel unter dem Zeiger ("mark"): das nächste Element um den Treffer, das auf
 * den Zielselektor passt, in der Wurzel liegt und nicht das gezogene selbst ist.
 *
 * @template T
 * @param {{ closest(selector: string): T | null } | null} hit
 * @param {string} selector
 * @param {Container} root
 * @param {unknown} item
 * @returns {T | null}
 */
export function pickTarget(hit, selector, root, item) {
    const target = hit ? hit.closest(selector) : null;
    return target != null && target !== item && root.contains(target) ? target : null;
}

/**
 * Liegt der Zeiger vor der Mitte des Ziels? Dann wird davor eingefügt, sonst
 * dahinter — sonst springt das Element beim Ablegen am Rand eine Stelle zu weit.
 *
 * @param {Rect} rect
 * @param {Point} point
 * @param {"x" | "y"} [axis]
 * @returns {boolean}
 */
export function dropsBefore(rect, point, axis = "y") {
    return axis === "x"
        ? point.x < rect.left + rect.width / 2
        : point.y < rect.top + rect.height / 2;
}

/**
 * Einfügeposition ("live"): Index des ersten ANDEREN Elements, dessen Mitte
 * hinter dem Zeiger liegt; `rects.length` heißt ans Ende. Mittelpunktvergleich
 * statt Treffertest, weil das gezogene Element unter dem Zeiger klebt.
 *
 * @param {Rect[]} rects Rechtecke der übrigen Elemente in Listenfolge
 * @param {Point} point
 * @param {"x" | "y"} [axis]
 * @returns {number}
 */
export function insertionIndex(rects, point, axis = "y") {
    const index = rects.findIndex((rect) => dropsBefore(rect, point, axis));
    return index === -1 ? rects.length : index;
}

/**
 * Index des gezogenen Elements nach dem Zug, wenn es vor bzw. hinter `target`
 * landet. Alle Angaben sind Indizes der Liste vor dem Zug.
 *
 * @param {number} from
 * @param {number} target
 * @param {boolean} before
 * @returns {number}
 */
export function indexAfterMove(from, target, before) {
    const slot = before ? target : target + 1;
    return slot > from ? slot - 1 : slot;
}

/**
 * Vorgänger an der neuen Stelle als Index der Liste VOR dem Zug; -1 = Spitze.
 *
 * @param {number} from
 * @param {number} to Index nach dem Zug
 * @returns {number}
 */
export function predecessorIndex(from, to) {
    return to > from ? to : to - 1;
}

/**
 * Scroll-Schritt am Rand des sichtbaren Bereichs: je tiefer der Zeiger in der
 * Randzone steht (oder darüber hinaus), desto schneller.
 *
 * @param {number} pos Zeigerlage längs der Scrollachse
 * @param {number} start Anfang des sichtbaren Bereichs
 * @param {number} end Ende des sichtbaren Bereichs
 * @param {number} [zone]
 * @param {number} [maxStep]
 * @returns {number} negativ = zurück, positiv = vor, 0 = nicht scrollen
 */
export function edgeScrollStep(pos, start, end, zone = SCROLL_ZONE, maxStep = SCROLL_MAX_STEP) {
    // Niedrige Bereiche: die Zonen dürfen sich nicht überlappen.
    const reach = Math.min(zone, (end - start) / 2);
    if (reach <= 0) return 0;

    let depth = 0;
    if (pos < start + reach) depth = pos - (start + reach);
    else if (pos > end - reach) depth = pos - (end - reach);

    // `|| 0` normalisiert -0.
    return Math.round((Math.max(-reach, Math.min(reach, depth)) / reach) * maxStep) || 0;
}

/**
 * Achsen, an deren Rand mitgescrollt wird.
 *
 * @param {Axis} axis
 * @returns {Array<"x" | "y">}
 */
export function scrollAxes(axis) {
    return axis === "both" ? ["x", "y"] : [axis];
}

/**
 * Sichtbarer Abschnitt eines Scrollbereichs längs einer Achse: sein Rechteck,
 * aufs Fenster beschnitten — nur dort erreicht der Zeiger einen Rand.
 *
 * @param {Box | null} box Rechteck des Scrollbereichs; null = die Seite selbst
 * @param {{ width: number, height: number }} viewport
 * @param {"x" | "y"} [axis]
 * @returns {{ start: number, end: number }}
 */
export function visibleSpan(box, viewport, axis = "y") {
    const extent = axis === "x" ? viewport.width : viewport.height;
    if (!box) return { start: 0, end: extent };
    const [start, end] = axis === "x" ? [box.left, box.right] : [box.top, box.bottom];
    return { start: Math.max(0, start), end: Math.min(extent, end) };
}

/**
 * @typedef {object} SortDrop
 * @property {HTMLElement} item Gezogenes Element
 * @property {HTMLElement} list Sein Behälter
 * @property {HTMLElement | null} target "mark": Ziel unter dem Zeiger
 * @property {boolean} before "mark": vor dem Ziel einfügen, sonst dahinter
 * @property {number} from Index vor dem Zug
 * @property {number} to Index nach dem Zug; -1, wenn das Ziel kein Listenplatz ist
 *
 * @typedef {object} PointerSortOptions
 * @property {string} item Selektor der ziehbaren Elemente
 * @property {string | null} [list] Selektor des Behälters je Element — nötig bei Delegation über `document`
 * @property {string | null} [handle] Selektor des Griffs; ohne Griff zieht das ganze Element
 * @property {boolean} [mouseAnywhere] Die Maus darf trotz Griff überall am Element ansetzen
 * @property {string | null} [ignore] Kein Zug aus diesen Elementen heraus
 * @property {"live" | "mark"} [mode]
 * @property {string} [target] "mark": Selektor der Ziele, sonst die Elemente selbst
 * @property {"midpoint" | "direction"} [side] "mark": davor/dahinter nach Zielmitte oder nach Zugrichtung
 * @property {Axis} [axis] Achse der Liste; "both" = Schwelle in jede Richtung
 * @property {Axis} [scrollAxis] Randscrollen senkrecht (Standard), waagerecht oder in beide Richtungen
 * @property {number} [threshold]
 * @property {string[]} [draggingClass] Klassen am gezogenen Element
 * @property {string[]} [targetClass] "mark": Klassen am Ziel unter dem Zeiger
 * @property {((item: HTMLElement) => boolean) | null} [canDrag]
 * @property {((drop: SortDrop) => void) | null} [onDrop] "mark": nur mit Ziel; nie nach einem Abbruch
 *
 * @typedef {object} Press
 * @property {HTMLElement} item
 * @property {HTMLElement} list
 * @property {number} pointerId
 * @property {Point} start
 * @property {Point} last
 * @property {boolean} dragging
 * @property {boolean} aborted Mit Escape abgebrochen, der Zeiger ist noch unten
 * @property {number} from
 * @property {HTMLElement | null} target
 * @property {{ parent: Node, next: Node | null } | null} origin
 * @property {HTMLElement | null} focus
 * @property {Array<{ axis: "x" | "y", el: Element }>} scrollers
 * @property {number} frame
 */

/** Je Scrollachse: Maße, Überlauf-Eigenschaft, Scrollstand und Schlüssel für `scrollBy`. */
const SCROLL_SIDES = /** @type {const} */ ({
    x: { size: "scrollWidth", room: "clientWidth", overflow: "overflowX", offset: "scrollLeft", side: "left" },
    y: { size: "scrollHeight", room: "clientHeight", overflow: "overflowY", offset: "scrollTop", side: "top" },
});

/**
 * Nächster längs der Achse scrollender Vorfahr (das Element eingeschlossen), sonst die Seite.
 *
 * @param {Node} node
 * @param {Window} view
 * @param {"x" | "y"} axis
 * @returns {Element | null}
 */
function scrollerOf(node, view, axis) {
    const { size, room, overflow } = SCROLL_SIDES[axis];
    for (let el = /** @type {Node | null} */ (node); el instanceof Element; el = el.parentElement) {
        if (el[size] > el[room] && /auto|scroll/.test(view.getComputedStyle(el)[overflow])) {
            return el;
        }
    }
    return (node.ownerDocument || /** @type {Document} */ (node)).scrollingElement;
}

/**
 * Verdrahtet eine sortierbare Liste bzw. ziehbare Elemente unterhalb von `root`.
 *
 * @param {HTMLElement | Document} root Wurzel; `document` für nachgeladene Inhalte
 * @param {PointerSortOptions} options
 */
export function pointerSort(
    root,
    {
        item: itemSelector,
        list: listSelector = null,
        handle = null,
        mouseAnywhere = false,
        ignore = SORT_IGNORE_SELECTOR,
        mode = "mark",
        target: targetSelector = itemSelector,
        side = "midpoint",
        axis = "y",
        scrollAxis = "y",
        threshold = SORT_THRESHOLD,
        draggingClass = [],
        targetClass = [],
        canDrag = null,
        onDrop = null,
    },
) {
    const doc = /** @type {Document} */ (root.ownerDocument || root);
    const view = /** @type {Window} */ (doc.defaultView);
    const live = mode === "live";
    const flow = axis === "x" ? "x" : "y";

    /** @type {Press | null} */
    let press = null;
    // Ein Zug endet auf dem Element — der folgende Klick würde es sonst auslösen.
    let suppressClick = false;

    /** @param {ParentNode} list */
    const itemsOf = (list) =>
        /** @type {HTMLElement[]} */ (Array.from(list.querySelectorAll(itemSelector)));

    /** @param {PointerEvent} event */
    const pointOf = (event) => ({ x: event.clientX, y: event.clientY });

    // Bewegungen über Elementgrenzen hinweg kommen weiter beim gezogenen Element an.
    const capture = () => {
        if (!press) return;
        try {
            press.item.setPointerCapture(press.pointerId);
        } catch (_e) {
            /* ältere Engines ohne Pointer-Capture */
        }
    };

    /**
     * Umhängen im DOM löst Capture und Fokus des Elements — beides gleich
     * wieder setzen, sonst endet der Tastaturweg am Griff nach jedem Zug.
     *
     * @param {() => void} move
     */
    const place = (move) => {
        move();
        capture();
        press?.focus?.focus({ preventScroll: true });
    };

    /** Reihenfolge ("live") bzw. Zielmarke ("mark") an die Zeigerlage anpassen. */
    const update = () => {
        if (!press) return;
        const { item, list, last } = press;

        if (live) {
            const others = itemsOf(list).filter((other) => other !== item);
            const rects = others.map((other) => other.getBoundingClientRect());
            const next = others[insertionIndex(rects, last, flow)];
            const tail = others[others.length - 1];
            if (next) {
                if (next.previousElementSibling !== item) place(() => next.before(item));
            } else if (tail && tail.nextElementSibling !== item) {
                place(() => tail.after(item));
            }
            return;
        }

        const hit = doc.elementFromPoint(last.x, last.y);
        const target = /** @type {HTMLElement | null} */ (
            pickTarget(hit, targetSelector, root, item)
        );
        if (target === press.target) return;
        press.target?.classList.remove(...targetClass);
        target?.classList.add(...targetClass);
        press.target = target;
    };

    // Der Zeiger steht am Rand still — also je Frame prüfen, nicht je Bewegung.
    const scrollTick = () => {
        if (!press?.dragging) return;
        const { scrollers, last } = press;
        const viewport = { width: view.innerWidth, height: view.innerHeight };
        let moved = false;
        for (const { axis: along, el } of scrollers) {
            const { offset, side } = SCROLL_SIDES[along];
            const { start, end } = visibleSpan(
                el === doc.scrollingElement ? null : el.getBoundingClientRect(),
                viewport,
                along,
            );
            const step = edgeScrollStep(last[along], start, end);
            if (step === 0) continue;
            const before = el[offset];
            // "instant": die Seite scrollt per CSS weich, das käme je Frame nicht vom Fleck.
            el.scrollBy({ [side]: step, behavior: "instant" });
            if (el[offset] !== before) moved = true;
        }
        if (moved) update();
        press.frame = view.requestAnimationFrame(scrollTick);
    };

    /** @param {KeyboardEvent} event */
    const onKeydown = (event) => {
        if (event.key !== "Escape" || !press?.dragging) return;
        // Sonst schließt dieselbe Taste den Dialog, in dem gerade sortiert wird.
        event.preventDefault();
        event.stopPropagation();
        abort();
        // Der Zeiger ist noch unten: bis zum Loslassen merken, damit dessen
        // Klick nichts auslöst.
        press.aborted = true;
    };

    const begin = () => {
        if (!press) return;
        const { item, list } = press;
        press.dragging = true;
        press.from = itemsOf(list).indexOf(item);
        // Während des Zugs hängt das Element im DOM.
        press.origin = { parent: /** @type {Node} */ (item.parentNode), next: item.nextSibling };
        press.focus = item.contains(doc.activeElement)
            ? /** @type {HTMLElement} */ (doc.activeElement)
            : null;
        press.scrollers = scrollAxes(scrollAxis).flatMap((along) => {
            const el = scrollerOf(list, view, along);
            return el ? [{ axis: along, el }] : [];
        });
        item.classList.add(...draggingClass);
        doc.documentElement.classList.add(SORTING_CLASS);
        capture();
        doc.addEventListener("keydown", onKeydown, true);
        press.frame = view.requestAnimationFrame(scrollTick);
    };

    /** Sichtbaren Zustand eines Zugs abräumen. */
    const release = () => {
        if (!press) return;
        const { item, target, pointerId, frame } = press;
        item.classList.remove(...draggingClass);
        target?.classList.remove(...targetClass);
        doc.documentElement.classList.remove(SORTING_CLASS);
        doc.removeEventListener("keydown", onKeydown, true);
        view.cancelAnimationFrame(frame);
        try {
            item.releasePointerCapture(pointerId);
        } catch (_e) {
            /* Capture war schon gelöst */
        }
    };

    /** Zug verwerfen: nichts melden, "live" die alte Reihenfolge wiederherstellen. */
    const abort = () => {
        if (!press) return;
        if (live && press.origin) {
            const { item, origin } = press;
            place(() => origin.parent.insertBefore(item, origin.next));
        }
        release();
        press.dragging = false;
        press.target = null;
    };

    const forget = () => {
        press = null;
        doc.removeEventListener("pointermove", onMove);
        doc.removeEventListener("pointerup", onUp);
        doc.removeEventListener("pointercancel", onCancel);
    };

    /** @param {Press} done */
    const drop = (done) => {
        const { item, list, target, from, last } = done;
        const siblings = itemsOf(list);

        if (live) {
            onDrop?.({ item, list, target: null, before: false, from, to: siblings.indexOf(item) });
            return;
        }
        if (!target) return;

        const over = siblings.indexOf(target);
        const before =
            over !== -1 &&
            (side === "direction"
                ? over < from
                : dropsBefore(target.getBoundingClientRect(), last, flow));
        const to = over === -1 ? -1 : indexAfterMove(from, over, before);
        onDrop?.({ item, list, target, before, from, to });
    };

    /** @param {PointerEvent} event */
    function onMove(event) {
        if (!press || event.pointerId !== press.pointerId || press.aborted) return;
        press.last = pointOf(event);
        if (!press.dragging) {
            if (!exceedsThreshold(press.start, press.last, threshold, axis)) return;
            begin();
        }
        event.preventDefault();
        update();
    }

    /** @param {PointerEvent} event */
    function onUp(event) {
        if (!press || event.pointerId !== press.pointerId) return;
        const done = press;
        if (done.dragging) {
            done.last = pointOf(event);
            update();
            release();
        }
        forget();
        if (!done.dragging && !done.aborted) return;

        // Genau den einen Klick nach dem Zug verschlucken. Endet der Zug über
        // einem anderen Element, bleibt er manchmal aus — der Timer verhindert,
        // dass die Sperre dann auf den NÄCHSTEN Klick fällt.
        suppressClick = true;
        view.setTimeout(() => {
            suppressClick = false;
        }, 0);
        if (done.dragging) drop(done);
    }

    /** @param {PointerEvent} event */
    function onCancel(event) {
        if (!press || event.pointerId !== press.pointerId) return;
        if (press.dragging) abort();
        forget();
    }

    // root ist HTMLElement | Document; die Vereinigung kennt nur den allgemeinen Listener-Typ.
    root.addEventListener("pointerdown", /** @type {EventListener} */ ((/** @type {PointerEvent} */ event) => {
        if (event.pointerType === "mouse" && event.button !== 0) return;
        if (press) {
            // Zweiter Finger während des Zugs; alles andere ist ein verwaister
            // Druck, dessen pointerup nie ankam.
            if (press.dragging && event.pointerId !== press.pointerId) return;
            if (press.dragging) abort();
            forget();
        }

        const hit = event.target instanceof Element ? event.target : null;
        const item = /** @type {HTMLElement | null} */ (hit?.closest(itemSelector));
        if (!hit || !item || !root.contains(item)) return;
        const list = /** @type {HTMLElement | null} */ (
            listSelector ? item.closest(listSelector) : root
        );
        if (!list || (canDrag && !canDrag(item))) return;

        const mouse = event.pointerType === "mouse";
        if (!mayStartDrag(hit, item, { handle, ignore, mouse, mouseAnywhere })) return;

        const start = pointOf(event);
        press = {
            item,
            list,
            pointerId: event.pointerId,
            start,
            last: start,
            dragging: false,
            aborted: false,
            from: -1,
            target: null,
            origin: null,
            focus: null,
            scrollers: [],
            frame: 0,
        };
        // Am Dokument statt an der Wurzel: Bewegung und Loslassen dürfen die
        // Wurzel verlassen, bevor das Capture greift.
        doc.addEventListener("pointermove", onMove);
        doc.addEventListener("pointerup", onUp);
        doc.addEventListener("pointercancel", onCancel);
    }));

    // Ohne das markiert die Maus Text, bevor die Schwelle erreicht ist.
    root.addEventListener("selectstart", (event) => {
        if (press) event.preventDefault();
    });

    // Capture-Phase am Fenster: der Klick darf weder Link oder Knopf noch die
    // Dokument-Listener der App (Eintrags-Dialog) erreichen.
    view.addEventListener(
        "click",
        (event) => {
            if (!suppressClick) return;
            suppressClick = false;
            event.preventDefault();
            event.stopPropagation();
        },
        true,
    );
}
