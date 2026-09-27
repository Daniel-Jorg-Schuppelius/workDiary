/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline-reader.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Offline-Leser der Offline-Seite (MVP-914): zeigt die im Gerät gespeicherte
 * Krisenmappe (IndexedDB „workdiary-sync“, Store „crisis“) und die zum
 * Offline-Lesen gespeicherten Kurse (Store „courses“, Befund C3-01) ohne Netz. Liegt
 * als statische Datei neben offline.html und wird vom Service Worker mit ihr
 * vorgecacht. Aufbau nur über textContent — die Mappe enthält Freitext.
 */
(function () {
    "use strict";

    var T = {
        de: { title: "Sie sind offline", text: "workDiary kann den angeforderten Inhalt gerade nicht laden. Sobald die Verbindung wieder steht, ist die App wie gewohnt verfügbar.", retry: "Erneut versuchen", crisis: "Krisenmappe", stand: "Stand", situation: "Lage", risks: "Risiken", actions: "Offene Maßnahmen", team: "Krisenstab", deputy: "Vertretung", messages: "Versandte Mitteilungen", none: "Keine aktive Krise gespeichert.", courses: "Gespeicherte Kurse", storedAt: "gespeichert am", onlineOnly: "nur online verfügbar", solution: "Lösung anzeigen" },
        en: { title: "You are offline", text: "workDiary cannot load the requested content right now. As soon as the connection is back, the app is available as usual.", retry: "Try again", crisis: "Crisis pack", stand: "As of", situation: "Situation", risks: "Risks", actions: "Open actions", team: "Crisis team", deputy: "Deputy", messages: "Messages sent", none: "No active crisis stored.", courses: "Saved courses", storedAt: "saved on", onlineOnly: "available online only", solution: "Show solution" },
        fr: { title: "Vous êtes hors ligne", text: "workDiary ne peut pas charger le contenu demandé pour le moment. Dès que la connexion est rétablie, l'application est de nouveau disponible.", retry: "Réessayer", crisis: "Dossier de crise", stand: "État au", situation: "Situation", risks: "Risques", actions: "Mesures ouvertes", team: "Cellule de crise", deputy: "Suppléant", messages: "Messages envoyés", none: "Aucune crise active enregistrée.", courses: "Cours enregistrés", storedAt: "enregistré le", onlineOnly: "disponible uniquement en ligne", solution: "Afficher la solution" },
        es: { title: "Está sin conexión", text: "workDiary no puede cargar el contenido solicitado en este momento. En cuanto vuelva la conexión, la aplicación estará disponible como siempre.", retry: "Reintentar", crisis: "Carpeta de crisis", stand: "Estado", situation: "Situación", risks: "Riesgos", actions: "Medidas abiertas", team: "Comité de crisis", deputy: "Suplente", messages: "Mensajes enviados", none: "No hay ninguna crisis activa guardada.", courses: "Cursos guardados", storedAt: "guardado el", onlineOnly: "solo disponible en línea", solution: "Mostrar solución" },
        it: { title: "È offline", text: "workDiary non riesce a caricare il contenuto richiesto in questo momento. Appena la connessione torna disponibile, l'app è di nuovo utilizzabile.", retry: "Riprova", crisis: "Cartella di crisi", stand: "Aggiornato al", situation: "Situazione", risks: "Rischi", actions: "Misure aperte", team: "Unità di crisi", deputy: "Sostituto", messages: "Messaggi inviati", none: "Nessuna crisi attiva salvata.", courses: "Corsi salvati", storedAt: "salvato il", onlineOnly: "disponibile solo online", solution: "Mostra soluzione" }
    };
    var lang = (navigator.language || "de").slice(0, 2).toLowerCase();
    var t = T[lang] || T.de;

    function el(tag, text, cls) {
        var node = document.createElement(tag);
        if (text) node.textContent = text;
        if (cls) node.className = cls;
        return node;
    }

    function date(iso) {
        if (!iso) return "";
        try { return new Date(iso).toLocaleString(lang); } catch (e) { return iso; }
    }

    function contact(person) {
        var span = el("span", person.name);
        if (person.phone) {
            var link = el("a", " " + person.phone);
            link.href = "tel:" + String(person.phone).replace(/[^+\d]/g, "");
            span.appendChild(link);
        }
        return span;
    }

    function render(bundle) {
        var box = document.getElementById("crisis");
        if (!box) return;
        box.hidden = false;
        box.appendChild(el("h2", t.crisis));
        box.appendChild(el("p", t.stand + " " + date(bundle.generated_at) + (bundle.organization ? " · " + bundle.organization : ""), "meta"));
        if (!bundle.cases || bundle.cases.length === 0) {
            box.appendChild(el("p", t.none));
            return;
        }
        bundle.cases.forEach(function (item) {
            var section = el("section", null, "case");
            section.appendChild(el("h3", item.title + " (" + item.severity + ")"));
            if (item.situation) {
                section.appendChild(el("h4", t.situation + " · " + date(item.situation.at)));
                section.appendChild(el("p", item.situation.content));
                if (item.situation.risks) section.appendChild(el("p", t.risks + ": " + item.situation.risks));
            }
            if (item.actions && item.actions.length) {
                section.appendChild(el("h4", t.actions));
                var list = el("ul");
                item.actions.forEach(function (a) { list.appendChild(el("li", a.title + (a.assignee ? " — " + a.assignee : "") + (a.due_at ? " · " + date(a.due_at) : ""))); });
                section.appendChild(list);
            }
            if (item.team && item.team.length) {
                section.appendChild(el("h4", t.team));
                var team = el("ul");
                item.team.forEach(function (m) {
                    var li = el("li", (m.role || "") + ": ");
                    if (m.person) li.appendChild(contact(m.person));
                    if (m.deputy) { li.appendChild(el("span", " · " + t.deputy + ": ")); li.appendChild(contact(m.deputy)); }
                    if (m.note) li.appendChild(el("span", " · " + m.note));
                    team.appendChild(li);
                });
                section.appendChild(team);
            }
            if (item.communications && item.communications.length) {
                section.appendChild(el("h4", t.messages));
                item.communications.forEach(function (c) {
                    section.appendChild(el("p", c.subject + " · " + date(c.sent_at), "meta"));
                    section.appendChild(el("p", c.body));
                });
            }
            box.appendChild(section);
        });
    }

    function block(b) {
        var type = b && b.type;
        var caption = b.caption || b.alt || "";
        switch (type) {
            case "heading": return el("h4", b.text || "");
            case "text": return el("p", b.text || "", "pre");
            case "callout": return el("p", b.text || "", "pre callout");
            case "code": var pre = el("pre"); pre.appendChild(el("code", b.text || "")); return pre;
            case "checklist":
                var ul = el("ul");
                (b.items || []).forEach(function (i) { ul.appendChild(el("li", String(i))); });
                return ul;
            case "accordion":
                var box = el("div");
                (b.sections || []).forEach(function (sec) {
                    var d = el("details");
                    d.appendChild(el("summary", sec.title || ""));
                    d.appendChild(el("p", sec.body || "", "pre"));
                    box.appendChild(d);
                });
                return box;
            case "table":
                var table = el("table");
                (b.rows || []).forEach(function (row, i) {
                    var tr = el("tr");
                    (row || []).forEach(function (cell) { tr.appendChild(el(i === 0 ? "th" : "td", String(cell))); });
                    table.appendChild(tr);
                });
                return table;
            case "question":
                var q = el("div");
                q.appendChild(el("p", b.text || "", "pre"));
                var opts = el("ul");
                (b.options || []).forEach(function (o) { opts.appendChild(el("li", o.text || "")); });
                q.appendChild(opts);
                var sol = el("details");
                sol.appendChild(el("summary", t.solution));
                (b.options || []).filter(function (o) { return o.correct; }).forEach(function (o) { sol.appendChild(el("p", "✓ " + (o.text || ""))); });
                if (b.explanation) sol.appendChild(el("p", b.explanation, "pre"));
                q.appendChild(sol);
                return q;
            case "divider": return el("hr");
            default:
                // Medien bleiben online (Bündel ohne Dateien); gezeigt wird die Beschriftung.
                return el("p", (caption ? caption + " — " : "") + t.onlineOnly, "meta");
        }
    }

    function renderCourses(list) {
        var box = document.getElementById("courses");
        if (!box || !list.length) return;
        box.hidden = false;
        box.appendChild(el("h2", t.courses));
        list.forEach(function (bundle) {
            var course = el("details", null, "case");
            course.appendChild(el("summary", (bundle.course && bundle.course.title) || ""));
            course.appendChild(el("p", t.storedAt + " " + date(bundle.stored_at), "meta"));
            if (bundle.course && bundle.course.subtitle) course.appendChild(el("p", bundle.course.subtitle));
            (bundle.units || []).forEach(function (unit) {
                course.appendChild(el("h3", (unit.section ? unit.section + " · " : "") + (unit.title || "")));
                (unit.blocks || []).forEach(function (b) { course.appendChild(block(b)); });
            });
            box.appendChild(course);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById("title").textContent = t.title;
        document.getElementById("text").textContent = t.text;
        var retry = document.getElementById("retry");
        retry.textContent = t.retry;
        retry.addEventListener("click", function () { location.reload(); });
        document.documentElement.lang = T[lang] ? lang : "de";

        if (typeof indexedDB === "undefined") return;
        var request = indexedDB.open("workdiary-sync");
        // Nichts gespeichert: keine leere Datenbank anlegen, die spätere Versionswechsel der App blockiert.
        request.onupgradeneeded = function () { request.transaction.abort(); };
        request.onsuccess = function () {
            var db = request.result;
            db.onversionchange = function () { db.close(); };
            if (db.objectStoreNames.contains("crisis")) {
                var get = db.transaction("crisis", "readonly").objectStore("crisis").get("bundle");
                get.onsuccess = function () { if (get.result) render(get.result); };
            }
            if (db.objectStoreNames.contains("courses")) {
                var all = db.transaction("courses", "readonly").objectStore("courses").getAll();
                all.onsuccess = function () { renderCourses(all.result || []); };
            }
        };
    });
})();
