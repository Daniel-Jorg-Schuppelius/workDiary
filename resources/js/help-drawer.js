// In-App-Hilfe-Drawer (MVP-051): bindet sich an [data-help-trigger][data-help-topic]
// und lädt das Topic über die JSON-Endpunkte /help/topics/{topic}.

const DRAWER_SELECTOR = "[data-help-drawer]";
const BACKDROP_SELECTOR = "[data-help-backdrop]";

let currentTopic = null;
let currentLocale = null;
let feedbackSent = false;

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") || "" : "";
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

function openDrawer() {
    const drawer = document.querySelector(DRAWER_SELECTOR);
    const backdrop = document.querySelector(BACKDROP_SELECTOR);
    setHidden(drawer, false);
    setHidden(backdrop, false);
}

function closeDrawer() {
    const drawer = document.querySelector(DRAWER_SELECTOR);
    const backdrop = document.querySelector(BACKDROP_SELECTOR);
    setHidden(drawer, true);
    setHidden(backdrop, true);
}

function renderTopicError(message) {
    const titleEl = document.querySelector("[data-help-title]");
    const bodyEl = document.querySelector("[data-help-body]");
    const footerEl = document.querySelector("[data-help-footer]");
    if (titleEl) titleEl.textContent = "—";
    if (bodyEl)
        bodyEl.innerHTML = `<p class="text-base-content/60">${message}</p>`;
    if (footerEl) footerEl.classList.add("hidden");
}

function renderTopic(payload) {
    const titleEl = document.querySelector("[data-help-title]");
    const bodyEl = document.querySelector("[data-help-body]");
    const footerEl = document.querySelector("[data-help-footer]");
    const relatedWrap = document.querySelector("[data-help-related]");
    const relatedList = document.querySelector("[data-help-related-list]");
    const thanksEl = document.querySelector("[data-help-feedback-thanks]");

    if (titleEl) titleEl.textContent = payload.title || payload.topic;
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
            related.forEach((slug) => {
                const li = document.createElement("li");
                const link = document.createElement("button");
                link.type = "button";
                link.className = "link link-primary text-left";
                link.textContent = slug;
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

async function loadTopic(topic) {
    if (!topic) return;
    const titleEl = document.querySelector("[data-help-title]");
    const bodyEl = document.querySelector("[data-help-body]");
    if (titleEl) titleEl.textContent = "…";
    if (bodyEl)
        bodyEl.innerHTML = `<p class="text-base-content/60">…</p>`;

    openDrawer();

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
            renderTopicError(
                (payload && payload.message) ||
                    "Kein Hilfetext verfügbar.",
            );
        }
    } catch (error) {
        renderTopicError("Hilfe konnte nicht geladen werden.");
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
            loadTopic(topic);
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
            closeDrawer();
            return;
        }
        const feedbackBtn = event.target.closest("[data-help-feedback]");
        if (feedbackBtn) {
            const value = feedbackBtn.getAttribute("data-help-feedback");
            submitFeedback(value === "1");
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            const drawer = document.querySelector(DRAWER_SELECTOR);
            if (drawer && !drawer.classList.contains("hidden")) {
                closeDrawer();
            }
            return;
        }
        // Shortcut "?" öffnet die Hilfe, wenn ein Trigger auf der Seite einen
        // Default-Topic kennt (data-help-default-topic auf <body> oder Form).
        if (event.key === "?" && !event.metaKey && !event.ctrlKey && !event.altKey) {
            const target = event.target;
            const isFormField =
                target &&
                (target.tagName === "INPUT" ||
                    target.tagName === "TEXTAREA" ||
                    target.isContentEditable);
            if (isFormField) return;
            const defaultTopic =
                document.body.getAttribute("data-help-default-topic");
            if (defaultTopic) {
                event.preventDefault();
                loadTopic(defaultTopic);
            }
        }
    });
}

if (typeof window !== "undefined") {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bindHelpDrawer);
    } else {
        bindHelpDrawer();
    }
}

export { loadTopic, closeDrawer };
