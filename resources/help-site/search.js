/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : search.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Suche der statischen Doku-Website (MVP-971): filtert den mitgelieferten
// Index (search-index.js) im Browser; alle Wörter müssen in Titel,
// Suchbegriffen (MVP-1079) oder Text vorkommen, Titel- und
// Suchbegriff-Treffer zuerst. Akzente zählen nicht. Ausgabe nur über textContent.
(function () {
    "use strict";

    var MAX_RESULTS = 25;

    /**
     * Eintrag aus search-index.js (HelpSiteExporter): t Titel, u Seite,
     * s Bereich, x Textauszug, k Suchbegriffe.
     * @typedef {{t: string, u: string, s: string, x: string, k: string}} HelpSiteEntry
     */
    /** @typedef {Window & {HELP_SITE_INDEX?: unknown}} HelpSiteWindow */

    /** @param {string} text */
    function fold(text) {
        return String(text || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/ß/g, "ss");
    }

    function init() {
        var input = /** @type {HTMLInputElement | null} */ (document.querySelector("[data-site-search-input]"));
        var form = document.querySelector("[data-site-search]");
        var results = /** @type {HTMLElement | null} */ (document.querySelector("[data-site-results]"));
        var list = document.querySelector("[data-site-results-list]");
        var empty = /** @type {HTMLElement | null} */ (document.querySelector("[data-site-results-empty]"));
        var sections = /** @type {HTMLElement | null} */ (document.querySelector("[data-site-sections]"));
        var index = /** @type {HelpSiteEntry[]} */ (Array.isArray(/** @type {HelpSiteWindow} */ (window).HELP_SITE_INDEX) ? /** @type {HelpSiteWindow} */ (window).HELP_SITE_INDEX : []);
        if (!input || !results || !list) return;

        if (form) {
            form.addEventListener("submit", function (event) { event.preventDefault(); });
        }

        input.addEventListener("input", function () {
            var words = fold(/** @type {HTMLInputElement} */ (input).value).split(/\s+/).filter(function (word) { return word.length > 1; });
            /** @type {Element} */ (list).textContent = "";
            if (words.length === 0) {
                /** @type {HTMLElement} */ (results).hidden = true;
                if (sections) sections.hidden = false;
                return;
            }

            /** @type {Array<{entry: HelpSiteEntry, inTitle: boolean}>} */
            var hits = [];
            index.forEach(function (entry) {
                var title = fold(entry.t) + " " + fold(entry.k);
                var haystack = title + " " + fold(entry.x);
                if (words.every(function (word) { return haystack.indexOf(word) !== -1; })) {
                    hits.push({ entry: entry, inTitle: words.every(function (word) { return title.indexOf(word) !== -1; }) });
                }
            });
            hits.sort(function (a, b) { return (b.inTitle ? 1 : 0) - (a.inTitle ? 1 : 0); });

            hits.slice(0, MAX_RESULTS).forEach(function (hit) {
                var item = document.createElement("li");
                var link = document.createElement("a");
                link.href = String(hit.entry.u || "");
                link.textContent = String(hit.entry.t || "");
                item.appendChild(link);
                if (hit.entry.s) {
                    var section = document.createElement("span");
                    section.className = "site-muted";
                    section.textContent = " · " + String(hit.entry.s);
                    item.appendChild(section);
                }
                /** @type {Element} */ (list).appendChild(item);
            });
            if (empty) empty.hidden = hits.length > 0;
            /** @type {HTMLElement} */ (results).hidden = false;
            if (sections) sections.hidden = true;
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
