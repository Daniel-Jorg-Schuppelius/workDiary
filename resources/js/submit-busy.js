/*
 * submit-busy.js
 * Rückmeldung beim Absenden klassischer Formulare (MVP-1783): die auslösende
 * Schaltfläche zeigt sofort „läuft“, ein zweiter Klick sendet nicht erneut.
 * Anlass: „Bezug lösen“ lud die Seite sekundenlang neu, ohne dass sich etwas
 * regte — der Klick galt als wirkungslos und wurde wiederholt.
 *
 * Nicht für: GET-Formulare (Filter), Formulare mit eigenem Ziel (target),
 * `data-no-busy` und alles, was ein anderer Handler abfängt (Dialog-Formulare,
 * Bestätigungsdialog, Offline-Sync) — die verhindern das Absenden selbst.
 * Die Schaltfläche wird nicht `disabled`: sonst fehlte ihr name/value in den
 * Formulardaten.
 */

/** Antwortet der Server mit einer Datei, bleibt die Seite stehen: danach wieder freigeben. */
const RELEASE_MS = 10000;

/** @param {HTMLFormElement} form */
const release = (form) => {
    delete form.dataset.submitting;
    form.querySelectorAll(".wd-busy").forEach((el) => {
        el.classList.remove("wd-busy");
        el.removeAttribute("aria-busy");
    });
};

export const initSubmitBusy = () => {
    document.addEventListener("submit", (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.submitting === "1") {
            event.preventDefault();
            return;
        }
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        // Erst nach allen anderen Handlern entscheiden: fängt einer das Absenden ab, bleibt die Schaltfläche frei.
        window.setTimeout(() => {
            if (event.defaultPrevented || form.method.toLowerCase() === "get" || form.hasAttribute("data-no-busy")) return;
            if (form.target !== "" && form.target !== "_self") return;
            form.dataset.submitting = "1";
            const button = submitter ?? form.querySelector('button[type="submit"]');
            button?.classList.add("wd-busy");
            button?.setAttribute("aria-busy", "true");
            window.setTimeout(() => release(form), RELEASE_MS);
        }, 0);
    });
    // Zurück aus dem Seitencache: die Seite zeigt sonst weiter „läuft“.
    window.addEventListener("pageshow", () => {
        /** @type {NodeListOf<HTMLFormElement>} */ (document.querySelectorAll('form[data-submitting="1"]')).forEach(release);
    });
};
