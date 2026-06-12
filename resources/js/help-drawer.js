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

const DRAWER_SELECTOR = "[data-help-drawer]";
const BACKDROP_SELECTOR = "[data-help-backdrop]";
const FALLBACK_TEMPLATE_SELECTOR = "template[data-help-fallback]";
const OPEN_STORAGE_KEY = "help.open";
const DESKTOP_QUERY = "(min-width: 1024px)";

let currentTopic = null;
let currentLocale = null;
let feedbackSent = false;
let lastTrigger = null;

function isDesktop() {
    return window.matchMedia(DESKTOP_QUERY).matches;
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") || "" : "";
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

function setHidden(el, hidden) {
    if (!el) return;
    el.classList.toggle("hidden", hidden);
    if (!hidden) {
        // sliding-in
        requestAnimationFrame(() => el.classList.remove("translate-x-full"));
    } else {
        el.classList.add("translate-x-full");
    }
}

function isOpen() {
    const drawer = document.querySelector(DRAWER_SELECTOR);
    return !!drawer && !drawer.classList.contains("hidden");
}

function openDrawer(options = {}) {
    const { focus = true } = options;
    const drawer = document.querySelector(DRAWER_SELECTOR);
    const backdrop = document.querySelector(BACKDROP_SELECTOR);
    if (!drawer) return;

    setHidden(drawer, false);
    // Backdrop nur mobil — Desktop-Sidebar ist nicht-modal (zusätzlich per
    // CSS lg:hidden! abgesichert).
    setHidden(backdrop, isDesktop());
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
    const hadFocusInside =
        !!drawer && drawer.contains(document.activeElement);

    setHidden(drawer, true);
    setHidden(backdrop, true);
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
    const template = document.querySelector(FALLBACK_TEMPLATE_SELECTOR);

    currentTopic = null;
    currentLocale = null;
    if (footerEl) footerEl.classList.add("hidden");

    if (template) {
        setTitle(template.getAttribute("data-fallback-title") || "");
        if (bodyEl) {
            bodyEl.innerHTML = "";
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
            bodyEl.innerHTML = "";
            const p = document.createElement("p");
            p.className = "text-base-content/60";
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

    resultsEl.innerHTML = "";
    try {
        const response = await fetch(
            `/help/search?q=${encodeURIComponent(query)}`,
            {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            },
        );
        const payload = await response.json();
        const items =
            response.ok && payload && Array.isArray(payload.items)
                ? payload.items
                : [];

        if (items.length === 0) {
            const li = document.createElement("li");
            li.className = "text-base-content/60";
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
        li.className = "text-base-content/60";
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
    if (bodyEl) bodyEl.innerHTML = payload.body_html || "";
    if (footerEl) footerEl.classList.remove("hidden");
    if (thanksEl) thanksEl.classList.add("hidden");
    feedbackSent = false;

    if (relatedWrap && relatedList) {
        relatedList.innerHTML = "";
        const related = Array.isArray(payload.related) ? payload.related : [];
        if (related.length === 0) {
            relatedWrap.classList.add("hidden");
        } else {
            related.forEach((entry) => {
                // Server liefert {topic, title}; ältere Antworten waren rohe
                // Slugs — beide Formen abdecken, angezeigt wird immer der Titel.
                const slug = typeof entry === "string" ? entry : entry.topic;
                const label = typeof entry === "string" ? entry : (entry.title || entry.topic);
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

    currentTopic = payload.topic;
    currentLocale = payload.locale;
}

async function loadTopic(topic, options = {}) {
    if (!topic) return;
    const titleEl = document.querySelector("[data-help-title]");
    const bodyEl = document.querySelector("[data-help-body]");
    if (titleEl) titleEl.textContent = "…";
    if (bodyEl) bodyEl.innerHTML = `<p class="text-base-content/60">…</p>`;

    openDrawer(options);

    try {
        const response = await fetch(
            `/help/topics/${encodeURIComponent(topic)}`,
            {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            },
        );
        const payload = await response.json();
        if (response.ok && payload && payload.found) {
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
        await fetch(
            `/help/topics/${encodeURIComponent(currentTopic)}/feedback`,
            {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    helpful: !!helpful,
                    locale: currentLocale,
                }),
            },
        );
        const thanksEl = document.querySelector("[data-help-feedback-thanks]");
        if (thanksEl) thanksEl.classList.remove("hidden");
    } catch (error) {
        feedbackSent = false;
    }
}

function bindHelpDrawer() {
    document.addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-help-trigger]");
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
        const closeBtn = event.target.closest("[data-help-close]");
        if (closeBtn) {
            event.preventDefault();
            closeDrawer();
            return;
        }
        const backdrop = event.target.closest(BACKDROP_SELECTOR);
        if (backdrop) {
            // Nur mobil erreichbar — auf Desktop gibt es keinen Backdrop und
            // Klicks außerhalb schließen die nicht-modale Sidebar bewusst NICHT.
            closeDrawer();
            return;
        }
        const feedbackBtn = event.target.closest("[data-help-feedback]");
        if (feedbackBtn) {
            const value = feedbackBtn.getAttribute("data-help-feedback");
            submitFeedback(value === "1");
        }
    });

    // Suche im Fallback-Panel (Inhalt wird dynamisch geklont → Delegation).
    document.addEventListener("submit", (event) => {
        const form = event.target.closest("[data-help-search-form]");
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
        // Shortcut "?" (außerhalb von Eingabefeldern) öffnet die Hilfe zum
        // aktuellen Seitenkontext (body[data-help-context]); ohne Kontext
        // erscheint das Fallback-Panel mit Hilfe-Suche.
        if (
            event.key === "?" &&
            !event.metaKey &&
            !event.ctrlKey &&
            !event.altKey
        ) {
            const target = event.target;
            const isFormField =
                target &&
                (target.tagName === "INPUT" ||
                    target.tagName === "TEXTAREA" ||
                    target.tagName === "SELECT" ||
                    target.isContentEditable);
            if (isFormField) return;
            event.preventDefault();
            openContextHelp();
        }
    });

    // Breakpoint-Wechsel bei offener Hilfe: Backdrop-Zustand nachziehen
    // (Desktop nicht-modal ohne Backdrop, mobil mit Backdrop).
    const desktopQuery = window.matchMedia(DESKTOP_QUERY);
    if (typeof desktopQuery.addEventListener === "function") {
        desktopQuery.addEventListener("change", () => {
            if (isOpen()) {
                setHidden(
                    document.querySelector(BACKDROP_SELECTOR),
                    desktopQuery.matches,
                );
            }
        });
    }

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
