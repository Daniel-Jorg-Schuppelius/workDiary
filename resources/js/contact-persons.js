/*
 * contact-persons.js — Zeileneditor für Ansprechpartner in den Kunden-/
 * Lieferanten-Dialogen (Vollaudit 2026-07, N43). Ersetzt zwei identische
 * Inline-Skripte in customers/_form_dialog und suppliers/_form_dialog.
 * Event-Delegation auf document (Konvention inline-actions.js) — funktioniert
 * dadurch auch in per AJAX nachgeladenen Dialog-Inhalten.
 *
 * Markup-Kontrakt:
 *   [data-contact-persons]  Wurzel des Editors
 *   [data-contact-rows]     Container der Zeilen
 *   [data-contact-row]      eine Personenzeile (name/email/phone/primary)
 *   [data-contact-add]      Button: neue (geleerte) Zeile per Klon anhängen
 *   [data-contact-remove]   Button: Zeile entfernen; letzte Zeile wird geleert
 */

function renumber(rows) {
    rows.querySelectorAll('[data-contact-row]').forEach((row, idx) => {
        row.querySelectorAll('input[name]').forEach(inp => {
            inp.name = inp.name.replace(/contact_persons\[\d+\]/, 'contact_persons[' + idx + ']');
        });
    });
}

function clearInputs(row) {
    row.querySelectorAll('input').forEach(inp => {
        if (inp.type === 'checkbox') { inp.checked = false; }
        else if (inp.type !== 'hidden') { inp.value = ''; }
    });
}

document.addEventListener('click', (e) => {
    const target = e.target instanceof Element ? e.target : null;
    if (!target) return;

    const addBtn = target.closest('[data-contact-add]');
    if (addBtn) {
        const rows = addBtn.closest('[data-contact-persons]')?.querySelector('[data-contact-rows]');
        const first = rows?.querySelector('[data-contact-row]');
        if (!rows || !first) return;
        const clone = first.cloneNode(true);
        clearInputs(clone);
        rows.appendChild(clone);
        renumber(rows);
        return;
    }

    const removeBtn = target.closest('[data-contact-remove]');
    if (removeBtn) {
        const rows = removeBtn.closest('[data-contact-rows]');
        const row = removeBtn.closest('[data-contact-row]');
        if (!rows || !row) return;
        if (rows.querySelectorAll('[data-contact-row]').length > 1) {
            row.remove();
            renumber(rows);
        } else {
            // Letzte Zeile: nur leeren
            clearInputs(row);
        }
    }
});
