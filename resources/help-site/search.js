/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : search.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Suche der statischen Doku-Website (MVP-971): filtert den mitgelieferten
// Index (search-index.js) im Browser; alle Wörter müssen in Titel oder Text
// vorkommen, Titeltreffer zuerst. Ausgabe nur über textContent.
(function () {
    "use strict";

    var MAX_RESULTS = 25;

    function init() {
        var input = document.querySelector("[data-site-search-input]");
        var form = document.querySelector("[data-site-search]");
        var results = document.querySelector("[data-site-results]");
        var list = document.querySelector("[data-site-results-list]");
        var empty = document.querySelector("[data-site-results-empty]");
        var sections = document.querySelector("[data-site-sections]");
        var index = Array.isArray(window.HELP_SITE_INDEX) ? window.HELP_SITE_INDEX : [];
        if (!input || !results || !list) return;

        if (form) {
            form.addEventListener("submit", function (event) { event.preventDefault(); });
        }

        input.addEventListener("input", function () {
            var words = input.value.toLowerCase().split(/\s+/).filter(function (word) { return word.length > 1; });
            list.textContent = "";
            if (words.length === 0) {
                results.hidden = true;
                if (sections) sections.hidden = false;
                return;
            }

            var hits = [];
            index.forEach(function (entry) {
                var title = String(entry.t || "").toLowerCase();
                var haystack = title + " " + String(entry.x || "").toLowerCase();
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
                list.appendChild(item);
            });
            if (empty) empty.hidden = hits.length > 0;
            results.hidden = false;
            if (sections) sections.hidden = true;
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
