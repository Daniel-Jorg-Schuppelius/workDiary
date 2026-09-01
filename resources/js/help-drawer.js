// In-App-Hilfe (MVP-051 + Feature 039 Inkrement 1): bindet sich an
// [data-help-trigger][data-help-topic], den Seitenkontext (body[data-help-context])
// und lädt Topics über die JSON-Endpunkte /help/topics/{topic}.
//
// Desktop (>=1024px): nicht-modale rechte Sidebar ohne Backdrop; der Inhalt
// bekommt über body.help-sidebar-open rechts Platz (CSS im Layout) und bleibt
// voll bedienbar. Mobil: Drawer mit Backdrop wie bisher.
//
// Offen/zu wird in localStorage ("help.open" = "1") gemerkt — bewusst NUR der
// Zustand, kein Topic und keine fachlichen/personenbezogenen Daten. Nach einem
// Seitenwechsel (volle Page-Loads, kein SPA) öffnet die Sidebar dann mit dem
// NEUEN Seitenkontext, nie mit veraltetem Inhalt.

import { html, setHtml, clearHtml, trustedServerHtml } from "./lib/html.js";
import { getJson, postJson } from "./lib/http.js";

const DRAWER_SELECTOR = "[data-help-drawer]";
const BACKDROP_SELECTOR = "[data-help-backdrop]";
const FALLBACK_TEMPLATE_SELECTOR = "template[data-help-fallback]";
const OPEN_STORAGE_KEY = "help.open";
const FOOTER_COLLAPSED_STORAGE_KEY = "help.footer.collapsed";
const NEWS_PAUSED_STORAGE_KEY = "help.news.paused";
const DESKTOP_QUERY = "(min-width: 1024px)";
const REDUCED_MOTION_QUERY = "(prefers-reduced-motion: reduce)";
// Unterhalb dieser Viewport-Höhe klappt der Footer (Feedback/Aktionen) ohne
// gespeicherte Präferenz standardmäßig ein — sonst bleibt für den Hilfetext
// auf niedrigen Bildschirmen kaum Platz.
const SHORT_VIEWPORT_HEIGHT = 760;

let currentTopic = null;
let currentLocale = null;
let feedbackSent = false;
let lastTrigger = null;
let newsRotationTimer = null;

function isDesktop() {
    return window.matchMedia(DESKTOP_QUERY).matches;
}

function getDrawerText(key, fallback) {
    const drawer = document.querySelector(DRAWER_SELECTOR);
    return (drawer && drawer.getAttribute(key)) || fallback;
}

function rememberOpenState(open) {
    // Nur der Offen/Zu-Zustand — keine Topics, keine Inhalte (Datenschutz).
    try {
        if (open) {
            window.localStorage.setItem(OPEN_STORAGE_KEY, "1");
        } else {
            window.localStorage.removeItem(OPEN_STORAGE_KEY);
        }
    } catch (error) {
        // localStorage kann fehlen (Private Mode) — Hilfe funktioniert trotzdem.
    }
}

function wasOpen() {
    try {
        return window.localStorage.getItem(OPEN_STORAGE_KEY) === "1";
    } catch (error) {
        return false;
    }
}

// Footer (Feedback/Aktionen) einklappen, um auf niedrigen Bildschirmen mehr
// Platz für den Hilfetext zu schaffen. Zustand wird gemerkt — nur "collapsed",
// keine fachlichen Daten (Datenschutz, analog zum Offen/Zu-Zustand).
function rememberFooterCollapsed(collapsed) {
    try {
        if (collapsed) {
            window.localStorage.setItem(FOOTER_COLLAPSED_STORAGE_KEY, "1");
        } else {
            window.localStorage.setItem(FOOTER_COLLAPSED_STORAGE_KEY, "0");
        }
    } catch (error) {
        // localStorage kann fehlen (Private Mode) — Toggle funktioniert trotzdem.
    }
}

// Gespeicherte Präferenz oder — ohne Präferenz — Auto-Einklappen bei niedriger
// Viewport-Höhe.
function footerShouldStartCollapsed() {
    try {
        const stored = window.localStorage.getItem(
            FOOTER_COLLAPSED_STORAGE_KEY,
        );
        if (stored === "1") return true;
        if (stored === "0") return false;
    } catch (error) {
        // Kein localStorage → auf Höhen-Heuristik zurückfallen.
    }
    return window.innerHeight < SHORT_VIEWPORT_HEIGHT;
}

function applyFooterCollapsed(collapsed) {
    const content = document.querySelector("[data-help-footer-content]");
    const toggle = document.querySelector("[data-help-footer-toggle]");
    const chevron = document.querySelector("[data-help-footer-chevron]");
    if (content) content.classList.toggle("hidden", collapsed);
    if (toggle)
        toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
    // Chevron: eingeklappt zeigt nach unten (aufklappbar), aufgeklappt nach oben.
    if (chevron) chevron.classList.toggle("rotate-180", !collapsed);
}

function toggleFooter() {
    const content = document.querySelector("[data-help-footer-content]");
    if (!content) return;
    const collapsed = !content.classList.contains("hidden");
    applyFooterCollapsed(collapsed);
    rememberFooterCollapsed(collapsed);
}

function storedNewsPausedPreference() {
    try {
        const stored = window.localStorage.getItem(NEWS_PAUSED_STORAGE_KEY);
        if (stored === "1") return true;
        if (stored === "0") return false;
    } catch (error) {
        // Ohne localStorage gilt nur die Reduced-Motion-Präferenz.
    }
    return null;
}

function rememberNewsPaused(paused) {
    try {
        window.localStorage.setItem(
            NEWS_PAUSED_STORAGE_KEY,
            paused ? "1" : "0",
        );
    } catch (error) {
        // Die Feed-Rail funktioniert auch ohne persistente Präferenz.
    }
}

function bindNewsRail() {
    const rail = document.querySelector("[data-help-news]");
    if (!rail) return;

    const items = Array.from(
        rail.querySelectorAll("[data-help-news-item]"),
    ).filter((item) => item instanceof HTMLElement);
    const toggle = rail.querySelector("[data-help-news-toggle]");
    const toggleIcon = rail.querySelector("[data-help-news-toggle-icon]");
    if (items.length < 2 || !toggle) return;

    const configuredInterval = Number.parseInt(
        rail.getAttribute("data-news-rotation-ms") || "15000",
        10,
    );
    const interval = Number.isFinite(configuredInterval)
        ? Math.max(5000, configuredInterval)
        : 15000;
    const storedPreference = storedNewsPausedPreference();
    let paused =
        storedPreference === null
            ? window.matchMedia(REDUCED_MOTION_QUERY).matches
            : storedPreference;
    let temporarilyPaused = false;
    let currentIndex = 0;

    const updateToggle = () => {
        const label = rail.getAttribute(
            paused ? "data-label-resume" : "data-label-pause",
        );
        toggle.setAttribute("aria-pressed", paused ? "true" : "false");
        if (label) {
            toggle.setAttribute("aria-label", label);
            toggle.setAttribute("title", label);
        }
        if (toggleIcon)
            toggleIcon.textContent = paused ? "play_arrow" : "pause";
    };

    const showItem = (index) => {
        items.forEach((item, itemIndex) => {
            const active = itemIndex === index;
            item.classList.toggle("opacity-100", active);
            item.classList.toggle("opacity-0", !active);
            item.classList.toggle("pointer-events-none", !active);
            if (active) {
                item.removeAttribute("aria-hidden");
                item.removeAttribute("tabindex");
            } else {
                item.setAttribute("aria-hidden", "true");
                item.setAttribute("tabindex", "-1");
            }
        });
    };

    const stopTimer = () => {
        if (newsRotationTimer !== null) {
            window.clearInterval(newsRotationTimer);
            newsRotationTimer = null;
        }
    };

    const startTimer = () => {
        stopTimer();
        if (paused || temporarilyPaused || document.hidden) return;
        newsRotationTimer = window.setInterval(() => {
            if (document.body.classList.contains("help-sidebar-open")) return;
            currentIndex = (currentIndex + 1) % items.length;
            showItem(currentIndex);
        }, interval);
    };

    toggle.addEventListener("click", () => {
        paused = !paused;
        rememberNewsPaused(paused);
        updateToggle();
        startTimer();
    });

    rail.addEventListener("mouseenter", () => {
        temporarilyPaused = true;
        stopTimer();
    });
    rail.addEventListener("mouseleave", () => {
        temporarilyPaused = false;
        startTimer();
    });
    rail.addEventListener("focusin", () => {
        temporarilyPaused = true;
        stopTimer();
    });
    rail.addEventListener("focusout", (event) => {
        if (!(event instanceof FocusEvent)) return;
        if (
            event.relatedTarget instanceof Node &&
            rail.contains(event.relatedTarget)
        )
            return;
        temporarilyPaused = false;
        startTimer();
    });
    document.addEventListener("visibilitychange", startTimer);

    showItem(currentIndex);
    updateToggle();
    startTimer();
}

// Drawer/Backdrop bleiben immer im DOM (kein display-Toggling), getogglet
// werden nur Klassen — die Animation macht CSS (layout.css): mobil Slide via
// .translate-x-full, ab lg Breiten-Transition Rail↔Sidebar via
// body.help-sidebar-open (dort ist .translate-x-full nur Zustandsmarker).
function setDrawerHidden(el, hidden) {
    if (!el) return;
    el.classList.toggle("translate-x-full", hidden);
}

function setBackdropHidden(el, hidden) {
    if (!el) return;
    el.classList.toggle("help-backdrop-hidden", hidden);
}

function isOpen() {
    const drawer = document.querySelector(DRAWER_SELECTOR);
    return !!drawer && !drawer.classList.contains("translate-x-full");
}

function openDrawer(options = {}) {
    const { focus = true } = options;
    const drawer = /** @type {HTMLElement} */ (
        document.querySelector(DRAWER_SELECTOR)
    );
    const backdrop = document.querySelector(BACKDROP_SELECTOR);
    if (!drawer) return;

    setDrawerHidden(drawer, false);
    // Backdrop nur mobil — Desktop-Sidebar ist nicht-modal (zusätzlich per
    // CSS lg:hidden! abgesichert).
    setBackdropHidden(backdrop, isDesktop());
    document.body.classList.add("help-sidebar-open");
    rememberOpenState(true);

    if (focus) {
        // A11y: Fokus in die Sidebar (Container mit tabindex="-1").
        drawer.focus({ preventScroll: true });
    }
}

function closeDrawer(options = {}) {
    const { restoreFocus = true } = options;
    const drawer = document.querySelector(DRAWER_SELECTOR);
    const backdrop = document.querySelector(BACKDROP_SELECTOR);
    const hadFocusInside = !!drawer && drawer.contains(document.activeElement);

    setDrawerHidden(drawer, true);
    setBackdropHidden(backdrop, true);
    document.body.classList.remove("help-sidebar-open");
    rememberOpenState(false);

    // A11y: Fokus zurück zum Auslöser.
    if (
        restoreFocus &&
        hadFocusInside &&
        lastTrigger &&
        document.contains(lastTrigger) &&
        typeof lastTrigger.focus === "function"
    ) {
        lastTrigger.focus({ preventScroll: true });
    }
}

function setTitle(text) {
    const titleEl = document.querySelector("[data-help-title]");
    if (titleEl) titleEl.textContent = text;
}

function renderTopicError(message) {
    // Definierter Fallback (kein Endlos-Spinner): Hinweis + Hilfe-Suche.
    renderFallback(message);
}

// Fallback-Panel: "Für diese Seite gibt es noch keine Hilfe" + Suchfeld.
// Inhalt kommt aus dem serverseitig übersetzten <template data-help-fallback>.
function renderFallback(message = null) {
    const bodyEl = document.querySelector("[data-help-body]");
    const footerEl = document.querySelector("[data-help-footer]");
    const template = /** @type {HTMLTemplateElement} */ (
        document.querySelector(FALLBACK_TEMPLATE_SELECTOR)
    );

    currentTopic = null;
    currentLocale = null;
    if (footerEl) footerEl.classList.add("hidden");

    if (template) {
        setTitle(template.getAttribute("data-fallback-title") || "");
        if (bodyEl) {
            clearHtml(bodyEl);
            bodyEl.appendChild(template.content.cloneNode(true));
            if (message) {
                const messageEl = bodyEl.querySelector(
                    "[data-help-fallback-message]",
                );
                if (messageEl) messageEl.textContent = message;
            }
        }
    } else {
        setTitle("—");
        if (bodyEl && message) {
            clearHtml(bodyEl);
            const p = document.createElement("p");
            p.className = "text-muted";
            p.textContent = message;
            bodyEl.appendChild(p);
        }
    }
}

async function runFallbackSearch(form) {
    const input = form.querySelector('input[name="q"]');
    const resultsEl = document.querySelector("[data-help-search-results]");
    const template = document.querySelector(FALLBACK_TEMPLATE_SELECTOR);
    const query = input ? input.value.trim() : "";
    if (!resultsEl || query === "") return;

    clearHtml(resultsEl);
    try {
        const { ok, data: payload } = await getJson(
            `/help/search?q=${encodeURIComponent(query)}`,
        );
        const items =
            ok && payload && Array.isArray(payload.items) ? payload.items : [];

        if (items.length === 0) {
            const li = document.createElement("li");
            li.className = "text-muted";
            li.textContent = template
                ? template.getAttribute("data-empty-results") || ""
                : "";
            resultsEl.appendChild(li);
            return;
        }

        items.forEach((item) => {
            const li = document.createElement("li");
            const link = document.createElement("button");
            link.type = "button";
            link.className = "link link-primary text-left";
            link.textContent = item.title || item.topic;
            link.addEventListener("click", () => loadTopic(item.topic));
            li.appendChild(link);
            resultsEl.appendChild(li);
        });
    } catch (error) {
        const li = document.createElement("li");
        li.className = "text-muted";
        li.textContent = getDrawerText("data-text-error", "");
        resultsEl.appendChild(li);
    }
}

function renderTopic(payload) {
    const bodyEl = document.querySelector("[data-help-body]");
    const footerEl = document.querySelector("[data-help-footer]");
    const relatedWrap = document.querySelector("[data-help-related]");
    const relatedList = document.querySelector("[data-help-related-list]");
    const thanksEl = document.querySelector("[data-help-feedback-thanks]");

    setTitle(payload.title || payload.topic);
    // body_html kommt serverseitig gerendert aus der Help-Registry.
    if (bodyEl) setHtml(bodyEl, trustedServerHtml(payload.body_html || ""));
    if (footerEl) footerEl.classList.remove("hidden");
    if (thanksEl) thanksEl.classList.add("hidden");
    feedbackSent = false;

    if (relatedWrap && relatedList) {
        clearHtml(relatedList);
        const related = Array.isArray(payload.related) ? payload.related : [];
        if (related.length === 0) {
            relatedWrap.classList.add("hidden");
        } else {
            related.forEach((entry) => {
                // Server liefert {topic, title}; ältere Antworten waren rohe
                // Slugs — beide Formen abdecken, angezeigt wird immer der Titel.
                const slug = typeof entry === "string" ? entry : entry.topic;
                const label =
                    typeof entry === "string"
                        ? entry
                        : entry.title || entry.topic;
                if (!slug) return;
                const li = document.createElement("li");
                const link = document.createElement("button");
                link.type = "button";
                link.className = "link link-primary text-left";
                link.textContent = label;
                link.addEventListener("click", () => loadTopic(slug));
                li.appendChild(link);
                relatedList.appendChild(li);
            });
            relatedWrap.classList.remove("hidden");
        }
    }

    // Absprung zur Hilfecenter-Vollseite (Feature 039, MVP-752): exakt das
    // aktuell geladene Topic; URL-Template kommt serverseitig aus dem Blade.
    const fullpage = document.querySelector("[data-help-fullpage]");
    if (fullpage instanceof HTMLAnchorElement) {
        const template = fullpage.getAttribute("data-url-template") || "";
        if (template && payload.topic) {
            fullpage.href = template.replace(
                "__TOPIC__",
                encodeURIComponent(payload.topic),
            );
            fullpage.classList.remove("hidden");
        } else {
            fullpage.classList.add("hidden");
        }
    }

    currentTopic = payload.topic;
    currentLocale = payload.locale;
}

async function loadTopic(topic, options = {}) {
    if (!topic) return;
    const titleEl = document.querySelector("[data-help-title]");
    const bodyEl = document.querySelector("[data-help-body]");
    if (titleEl) titleEl.textContent = "…";
    if (bodyEl) setHtml(bodyEl, html`<p class="text-muted">…</p>`);

    openDrawer(options);

    try {
        const { ok, data: payload } = await getJson(
            `/help/topics/${encodeURIComponent(topic)}`,
        );
        if (ok && payload && payload.found) {
            renderTopic(payload);
        } else {
            // 404 / unsichtbares Topic → definierter Fallback statt Spinner.
            renderTopicError(
                (payload && payload.message) ||
                    getDrawerText("data-text-missing", ""),
            );
        }
    } catch (error) {
        renderTopicError(getDrawerText("data-text-error", ""));
    }
}

// Topic-Code des aktuellen Seitenkontexts (vom Layout serverseitig gesetzt).
function getContextTopic() {
    return (
        document.body.getAttribute("data-help-context") ||
        document.body.getAttribute("data-help-default-topic") ||
        null
    );
}

// Öffnet die Hilfe zum aktuellen Seitenkontext; ohne Kontext → Fallback-Panel.
function openContextHelp(options = {}) {
    const topic = getContextTopic();
    if (topic) {
        loadTopic(topic, options);
    } else {
        renderFallback();
        openDrawer(options);
    }
}

async function submitFeedback(helpful) {
    if (!currentTopic || feedbackSent) return;
    feedbackSent = true;

    try {
        await postJson(
            `/help/topics/${encodeURIComponent(currentTopic)}/feedback`,
            { helpful: !!helpful, locale: currentLocale },
        );
        const thanksEl = document.querySelector("[data-help-feedback-thanks]");
        if (thanksEl) thanksEl.classList.remove("hidden");
    } catch (error) {
        feedbackSent = false;
    }
}

function bindHelpDrawer() {
    document.addEventListener("click", (event) => {
        const trigger = /** @type {HTMLElement} */ (event.target).closest(
            "[data-help-trigger]",
        );
        if (trigger) {
            event.preventDefault();
            const topic = trigger.getAttribute("data-help-topic");
            // Toggle: Ist die Hilfe bereits offen und der Trigger würde
            // dasselbe (oder gar kein) Topic zeigen, schließt der Klick sie.
            if (isOpen() && (!topic || topic === currentTopic)) {
                closeDrawer();
                return;
            }
            lastTrigger = trigger;
            if (topic) {
                loadTopic(topic);
            } else {
                openContextHelp();
            }
            return;
        }
        const closeBtn = /** @type {HTMLElement} */ (event.target).closest(
            "[data-help-close]",
        );
        if (closeBtn) {
            event.preventDefault();
            closeDrawer();
            return;
        }
        const backdrop = /** @type {HTMLElement} */ (event.target).closest(
            BACKDROP_SELECTOR,
        );
        if (backdrop) {
            // Nur mobil erreichbar — auf Desktop gibt es keinen Backdrop und
            // Klicks außerhalb schließen die nicht-modale Sidebar bewusst NICHT.
            closeDrawer();
            return;
        }
        const feedbackBtn = /** @type {HTMLElement} */ (event.target).closest(
            "[data-help-feedback]",
        );
        if (feedbackBtn) {
            const value = feedbackBtn.getAttribute("data-help-feedback");
            submitFeedback(value === "1");
            return;
        }
        // Footer (Feedback/Aktionen) ein-/ausklappen — schafft auf niedrigen
        // Bildschirmen Platz für den Hilfetext.
        const footerToggle = /** @type {HTMLElement} */ (event.target).closest(
            "[data-help-footer-toggle]",
        );
        if (footerToggle) {
            event.preventDefault();
            toggleFooter();
        }
    });

    // Suche im Fallback-Panel (Inhalt wird dynamisch geklont → Delegation).
    document.addEventListener("submit", (event) => {
        const form = /** @type {HTMLElement} */ (event.target).closest(
            "[data-help-search-form]",
        );
        if (!form) return;
        event.preventDefault();
        runFallbackSearch(form);
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            if (isOpen()) {
                closeDrawer();
            }
            return;
        }
        // F1 öffnet die Hilfe zum aktuellen Seitenkontext
        // (body[data-help-context]); ohne Kontext erscheint das
        // Fallback-Panel mit Hilfe-Suche. Bewusst F1 statt "?": F1 ist die
        // etablierte Hilfe-Taste, funktioniert auch in Eingabefeldern und
        // "?" blieb exklusiv der Tastenkürzel-Übersicht (shortcuts.js) —
        // vorher feuerten BEIDE Handler auf dieselbe Taste.
        if (
            event.key === "F1" &&
            !event.metaKey &&
            !event.ctrlKey &&
            !event.altKey
        ) {
            event.preventDefault();
            openContextHelp();
        }
    });

    // Breakpoint-Wechsel: Backdrop-Zustand nachziehen (Desktop nicht-modal
    // ohne Backdrop, mobil mit Backdrop) UND Transitions kurz abschalten —
    // der Wechsel Rail (Desktop, sichtbar) ↔ Slide-Drawer (mobil, versteckt)
    // würde sonst als kurzes Aufpoppen/Rausrutschen animieren.
    const desktopQuery = window.matchMedia(DESKTOP_QUERY);
    if (typeof desktopQuery.addEventListener === "function") {
        desktopQuery.addEventListener("change", () => {
            const drawer = document.querySelector(DRAWER_SELECTOR);
            if (drawer) {
                drawer.classList.add("help-no-anim");
                requestAnimationFrame(() => {
                    requestAnimationFrame(() =>
                        drawer.classList.remove("help-no-anim"),
                    );
                });
            }
            if (isOpen()) {
                setBackdropHidden(
                    document.querySelector(BACKDROP_SELECTOR),
                    desktopQuery.matches,
                );
            }
        });
    }

    // Initialzustand des Footers: gespeicherte Präferenz oder Auto-Einklappen
    // bei niedriger Viewport-Höhe.
    if (document.querySelector("[data-help-footer-content]")) {
        applyFooterCollapsed(footerShouldStartCollapsed());
    }

    bindNewsRail();

    // War die Sidebar beim letzten Seitenaufruf offen, öffnet sie nach dem
    // (vollen) Page-Load automatisch mit dem NEUEN Seitenkontext. Kein
    // Fokus-Klau beim Laden (focus: false).
    if (document.querySelector(DRAWER_SELECTOR) && wasOpen()) {
        openContextHelp({ focus: false });
    }
}

if (typeof window !== "undefined") {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bindHelpDrawer);
    } else {
        bindHelpDrawer();
    }
}

export { loadTopic, closeDrawer, openContextHelp };
