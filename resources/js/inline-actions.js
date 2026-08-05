/*
 * inline-actions.js — delegierte Ersatz-Handler für frühere Inline-Event-
 * Attribute (onclick/onchange/onsubmit/…). Inline-Handler sind unter der
 * Nonce-CSP (CSP_SCRIPT_NONCE, Stufe 1) grundsätzlich blockiert — Nonces
 * gelten nur für <script>-Tags, nie für Event-Attribute. Alle Muster hier
 * arbeiten mit data-Attributen + Event-Delegation auf document und
 * funktionieren dadurch auch in per AJAX nachgeladenen Dialog-Inhalten.
 *
 * Muster:
 *   data-autosubmit            change → form.submit() (Wert "request" → requestSubmit())
 *   data-navigate-select       change → window.location.href = value (wenn gesetzt)
 *   data-open-dialog="<id>"    click  → <dialog id>.showModal()
 *   data-print                 click  → window.print() (App-Layout; Standalone-
 *                              Druckseiten binden dasselbe Attribut über
 *                              partials/print-script)
 *   data-select-on-click       click  → input.select()
 *   data-copy-text/-target     click  → Clipboard (+ optional data-copy-feedback)
 *   data-check-all="<sel>"     change → Checkboxen im Scope setzen
 *   data-toggle-hidden="<id>"  click  → classList.toggle('hidden')
 *   data-color-preview         change → Vorschau-Swatch (previousElementSibling)
 *   data-submit-on-enter       keydown Enter (ohne Shift) → form.requestSubmit()
 *   data-submit-form="<id>"    click  → <form id>.submit()
 *
 * Schließen von Dialogen: bestehendes data-entry-modal-close (app.js);
 * Bestätigungs-Abfragen: bestehendes data-confirm-dialog (layout.js).
 */

document.addEventListener("change", (event) => {
    const el = event.target instanceof Element ? event.target : null;
    if (!el) return;

    // Filter-Selects/-Inputs: Formular direkt abschicken.
    const auto = /** @type {HTMLInputElement | null} */ (
        el.closest("[data-autosubmit]")
    );
    if (auto && auto.form) {
        if (auto.getAttribute("data-autosubmit") === "request") {
            auto.form.requestSubmit();
        } else {
            auto.form.submit();
        }
        return;
    }

    // Navigations-Select: Option-Wert ist eine URL.
    const nav = /** @type {HTMLSelectElement | null} */ (
        el.closest("[data-navigate-select]")
    );
    if (nav) {
        if (nav.value) window.location.href = nav.value;
        return;
    }

    // "Alle auswählen"-Checkbox: setzt alle Ziel-Checkboxen im Scope.
    const checkAll = /** @type {HTMLInputElement | null} */ (
        el.closest("[data-check-all]")
    );
    if (checkAll) {
        const selector = checkAll.getAttribute("data-check-all");
        if (!selector) return;
        const scope =
            checkAll.getAttribute("data-check-all-scope") === "document"
                ? document
                : checkAll.closest("table") ||
                  checkAll.closest("form") ||
                  document;
        /** @type {NodeListOf<HTMLInputElement>} */ (
            scope.querySelectorAll(selector)
        ).forEach((cb) => {
            cb.checked = checkAll.checked;
        });
        return;
    }

    // Farb-Vorschau neben Farb-Selects (Entry-Types/Expense-Categories).
    const colorSel = /** @type {HTMLSelectElement | null} */ (
        el.closest("select[data-color-preview]")
    );
    if (colorSel && colorSel.previousElementSibling) {
        /** @type {HTMLElement} */ (
            colorSel.previousElementSibling
        ).style.backgroundColor = "var(--color-" + colorSel.value + ")";
    }
});

document.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    // Dialog öffnen.
    const opener = target.closest("[data-open-dialog]");
    if (opener) {
        const dlg = /** @type {HTMLDialogElement | null} */ (
            document.getElementById(opener.getAttribute("data-open-dialog"))
        );
        if (dlg && typeof dlg.showModal === "function") {
            event.preventDefault();
            dlg.showModal();
        }
        return;
    }

    // Drucken.
    if (target.closest("[data-print]")) {
        window.print();
        return;
    }

    // Eingabefeld-Inhalt markieren (Share-/Token-Felder).
    const selectable = /** @type {HTMLInputElement | null} */ (
        target.closest("input[data-select-on-click]")
    );
    if (selectable) {
        selectable.select();
        return;
    }

    // Element ein-/ausblenden (z. B. Kommentar-Bearbeitungsformular).
    const toggler = target.closest("[data-toggle-hidden]");
    if (toggler) {
        document
            .getElementById(toggler.getAttribute("data-toggle-hidden"))
            ?.classList.toggle("hidden");
        return;
    }

    // Fremdes Formular abschicken (Buttons außerhalb des <form>).
    const submitter = target.closest("[data-submit-form]");
    if (submitter) {
        event.preventDefault();
        const form = document.getElementById(
            submitter.getAttribute("data-submit-form"),
        );
        if (form instanceof HTMLFormElement) form.submit();
        return;
    }

    // In die Zwischenablage kopieren: data-copy-text (Literal) oder
    // data-copy-target (id eines Inputs/Elements); optionales Feedback-Label.
    const copier = target.closest("[data-copy-text], [data-copy-target]");
    if (copier) {
        let text = copier.getAttribute("data-copy-text");
        if (text === null) {
            const src = /** @type {HTMLElement | null} */ (
                document.getElementById(copier.getAttribute("data-copy-target"))
            );
            if (!src) return;
            text =
                "value" in src
                    ? /** @type {any} */ (src).value
                    : (src.textContent ?? "");
        }
        navigator.clipboard?.writeText(text).then(() => {
            const feedback = copier.getAttribute("data-copy-feedback");
            if (feedback) copier.textContent = feedback;
        });
    }
});

// Chat-Eingabe u. ä.: Enter (ohne Shift) schickt das Formular ab.
document.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" || event.shiftKey) return;
    const el =
        event.target instanceof Element
            ? /** @type {HTMLInputElement | null} */ (
                  event.target.closest("[data-submit-on-enter]")
              )
            : null;
    if (!el || !el.form) return;
    event.preventDefault();
    el.form.requestSubmit();
});
