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

    let dialog = null;
    let dialogImg = null;

    const ensureDialog = () => {
        if (dialog) return;
        dialog = document.createElement("dialog");
        dialog.className = "help-lightbox";
        dialogImg = document.createElement("img");
        dialogImg.alt = "";
        dialog.appendChild(dialogImg);
        dialog.addEventListener("click", (event) => {
            // Klick auf den Backdrop (= das dialog-Element selbst) schließt.
            if (event.target === dialog) dialog.close();
        });
        document.body.appendChild(dialog);
    };

    article.addEventListener("click", (event) => {
        const img = event.target;
        if (!(img instanceof HTMLImageElement)) return;
        ensureDialog();
        dialogImg.src = img.currentSrc || img.src;
        dialogImg.alt = img.alt || "";
        dialog.showModal();
    });
}

function initHelpCenter() {
    bindHelpCenterFeedback();
    bindHelpCenterLightbox();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initHelpCenter);
} else {
    initHelpCenter();
}
