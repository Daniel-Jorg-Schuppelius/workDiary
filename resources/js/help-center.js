/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help-center.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Hilfecenter-Vollseite (Feature 039, MVP-752): Hilfreich-Feedback über den
// bestehenden JSON-Endpunkt help.topics.feedback — dieselbe anonyme
// HelpView-Schreibung wie im Drawer, nur an der Vollseiten-Karte.

import { postJson } from "./lib/http.js";
import { recentHelpTopics, rememberHelpTopic } from "./lib/help-recent.js";

function bindHelpCenterFeedback() {
    const wrap = document.querySelector("[data-help-center-feedback]");
    if (!wrap) return;

    const url = wrap.getAttribute("data-feedback-url") || "";
    const locale = wrap.getAttribute("data-feedback-locale") || "";
    const thanks = wrap.querySelector("[data-help-center-thanks]");
    let sent = false;

    wrap.querySelectorAll("[data-help-center-vote]").forEach((button) => {
        button.addEventListener("click", async () => {
            if (sent || url === "") return;
            sent = true;
            try {
                await postJson(url, {
                    helpful: button.getAttribute("data-help-center-vote") === "1",
                    locale,
                });
            } catch {
                // Feedback ist best effort — die Seite bleibt nutzbar.
            }
            if (thanks) thanks.classList.remove("hidden");
        });
    });
}

// Lightbox der Artikel-Bilder (MVP-754): natives <dialog>, Klick auf ein
// Bild öffnet die Großansicht, Escape/Klick daneben schließt. CSP-konform,
// src stammt aus dem serverseitig gerenderten Artikel (help.center.media).
function bindHelpCenterLightbox() {
    const article = document.querySelector(".help-article");
    if (!article) return;

    /** @type {{ dialog: HTMLDialogElement, preview: HTMLImageElement } | null} */
    let lightbox = null;

    const ensureDialog = () => {
        if (lightbox) return lightbox;
        const dialog = document.createElement("dialog");
        dialog.className = "help-lightbox";
        const preview = document.createElement("img");
        preview.alt = "";
        dialog.appendChild(preview);
        dialog.addEventListener("click", (event) => {
            // Klick auf den Backdrop (= das dialog-Element selbst) schließt.
            if (event.target === dialog) dialog.close();
        });
        document.body.appendChild(dialog);
        lightbox = { dialog, preview };
        return lightbox;
    };

    article.addEventListener("click", (event) => {
        const img = event.target;
        if (!(img instanceof HTMLImageElement)) return;
        const { dialog, preview } = ensureDialog();
        preview.src = img.currentSrc || img.src;
        preview.alt = img.alt || "";
        dialog.showModal();
    });
}

// Zuletzt angesehen (MVP-972): die Artikelseite merkt sich das Thema, die
// Übersicht zeigt die Liste — Links nur über die serverseitige URL-Vorlage.
function bindHelpCenterRecent() {
    const article = document.querySelector("[data-help-center-topic]");
    if (article) {
        rememberHelpTopic(article.getAttribute("data-help-center-topic"), article.getAttribute("data-help-center-title"));
    }

    const wrap = /** @type {HTMLElement | null} */ (document.querySelector("[data-help-recent]"));
    const list = wrap?.querySelector("[data-help-recent-list]");
    const template = wrap?.getAttribute("data-show-url") || "";
    const entries = recentHelpTopics();
    if (!wrap || !list || template === "" || entries.length === 0) return;

    entries.forEach((entry) => {
        const item = document.createElement("li");
        const link = document.createElement("a");
        link.className = "link link-primary";
        link.href = template.replace("recent.placeholder", encodeURIComponent(entry.topic));
        link.textContent = entry.title;
        item.appendChild(link);
        list.appendChild(item);
    });
    wrap.hidden = false;
}

function initHelpCenter() {
    bindHelpCenterFeedback();
    bindHelpCenterLightbox();
    bindHelpCenterRecent();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initHelpCenter);
} else {
    initHelpCenter();
}
