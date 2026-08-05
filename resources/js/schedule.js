/**
 * schedule.js — Shift schedule interactivity (vanilla JS, no Alpine)
 *
 * Reads window.__scheduleConfig set by the Blade index view.
 * Alle Interaktionen laufen über data-Attribute + Event-Delegation
 * (data-schedule-cell/-drop, data-shift-drag/-edit, data-slot-open/-suggest,
 * data-type-edit/-delete) — Inline-Event-Attribute sind unter der Nonce-CSP
 * (CSP_SCRIPT_NONCE) blockiert und werden hier nicht mehr verwendet.
 */

import { __ } from "./i18n.js";
import { escHtml, escCssValue, html, setHtml, clearHtml } from "./lib/html.js";

/* ──────────────────────────── State ──────────────────────────── */

let _cfg = null; // window.__scheduleConfig
let _dragShiftId = null; // shift id being dragged

/* ──────────────────── Notify helper ──────────────────────────── */

function notifyError(message) {
    if (typeof window.notifyAction === "function") {
        window.notifyAction({ tone: "error", message: String(message) });
    }
}

/* ──────────────────────── Bootstrap ──────────────────────────── */

document.addEventListener("DOMContentLoaded", function () {
    _cfg = window.__scheduleConfig ?? {};

    // Open type-manager dialog button (handled in partial inline script, but
    // also guard here in case partial script runs before this file)
    document
        .getElementById("btn-open-type-manager")
        ?.addEventListener("click", function () {
            /** @type {HTMLDialogElement | null} */ (
                document.getElementById("shift-type-manager")
            )?.showModal();
        });

    // Shift dialog: save + delete
    document
        .getElementById("shift-dialog-form")
        ?.addEventListener("submit", onShiftDialogSave);
    document
        .getElementById("shift-dialog-delete")
        ?.addEventListener("click", onShiftDialogDelete);
    document
        .getElementById("shift-dialog-publish")
        ?.addEventListener("click", onShiftDialogPublish);
    document
        .getElementById("shift-dialog-confirm")
        ?.addEventListener("click", onShiftDialogConfirm);

    // Shift-type form: save
    document
        .getElementById("shift-type-form")
        ?.addEventListener("submit", onShiftTypeSave);

    // Auto-fill default times when shift type changes
    document
        .getElementById("shift-dialog-type")
        ?.addEventListener("change", applyShiftTypeDefaults);

    // Shift-type form: reset to "create" state
    document
        .getElementById("shift-type-reset")
        ?.addEventListener("click", shiftTypeResetForm);
});

/* ───────────── Delegierte Klick-/Drag-Handler (statt Inline) ───────────── */

document.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    // Besetzungsvorschlag anfordern (Button neben offenem Slot)
    const suggest = /** @type {HTMLElement | null} */ (
        target.closest("[data-slot-suggest]")
    );
    if (suggest) {
        event.stopPropagation();
        scheduleSuggestStaffing(
            suggest.dataset.date,
            suggest.dataset.slotTypeSqid,
            suggest.dataset.slotName ?? "",
        );
        return;
    }

    // Vorschlag übernehmen (Button im Vorschlags-Dialog)
    const assign = /** @type {HTMLElement | null} */ (
        target.closest("[data-assign-suggest]")
    );
    if (assign) {
        scheduleAssignSuggested(
            assign.dataset.date,
            assign.dataset.slotTypeSqid,
            assign.dataset.userSqid,
        );
        return;
    }

    // Offenen Slot besetzen → Dialog mit Datum + Typ vorbelegt
    const slot = /** @type {HTMLElement | null} */ (
        target.closest("[data-slot-open]")
    );
    if (slot) {
        event.stopPropagation();
        openShiftDialog({
            date: slot.dataset.date,
            shiftTypeId: slot.dataset.slotType,
        });
        return;
    }

    // Bestehende Schicht bearbeiten (Badge)
    const badge = /** @type {HTMLElement | null} */ (
        target.closest("[data-shift-edit]")
    );
    if (badge) {
        event.stopPropagation();
        let payload = null;
        try {
            payload = JSON.parse(badge.dataset.shiftPayload || "null");
        } catch (_e) {
            payload = null;
        }
        openShiftDialog({ shiftId: badge.dataset.shiftEdit, shift: payload });
        return;
    }

    // Schichttyp bearbeiten / löschen (Zeilen im Typ-Manager)
    const typeEdit = /** @type {HTMLElement | null} */ (
        target.closest("[data-type-edit]")
    );
    if (typeEdit) {
        let payload = null;
        try {
            payload = JSON.parse(typeEdit.dataset.typePayload || "null");
        } catch (_e) {
            payload = null;
        }
        if (payload) shiftTypeOpenEdit(typeEdit.dataset.typeEdit, payload);
        return;
    }
    const typeDelete = /** @type {HTMLElement | null} */ (
        target.closest("[data-type-delete]")
    );
    if (typeDelete) {
        shiftTypeDelete(typeDelete.dataset.typeDelete);
        return;
    }

    // Leere Zelle → neue Schicht (Badges/Slots sind oben bereits abgefangen)
    const cell = /** @type {HTMLElement | null} */ (
        target.closest("[data-schedule-cell]")
    );
    if (cell) {
        if (target.closest(".schedule-shift-badge")) return;
        openShiftDialog({
            date: cell.dataset.date,
            userId: cell.dataset.userId || null,
        });
    }
});

document.addEventListener("dragstart", (event) => {
    const badge = /** @type {HTMLElement | null} */ (
        event.target instanceof Element
            ? event.target.closest("[data-shift-drag]")
            : null
    );
    if (!badge) return;
    _dragShiftId = badge.dataset.shiftDrag;
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(_dragShiftId));
});

document.addEventListener("dragover", (event) => {
    if (
        event.target instanceof Element &&
        event.target.closest("[data-schedule-drop]")
    ) {
        event.preventDefault();
    }
});

document.addEventListener("drop", (event) => {
    const cell = /** @type {HTMLElement | null} */ (
        event.target instanceof Element
            ? event.target.closest("[data-schedule-drop]")
            : null
    );
    if (!cell) return;
    scheduleDropCell(event, cell.dataset.date, cell.dataset.dropUser || null);
});

/**
 * When a shift type is selected, prefill start/end with its defaults.
 * Always overwrites empty fields; overwrites existing values only if they
 * still match the previously selected type's defaults (so manual edits stay).
 */
function applyShiftTypeDefaults(event) {
    const sel = event.currentTarget ?? event.target;
    const id = sel.value;
    const types = Array.isArray(_cfg?.shiftTypes) ? _cfg.shiftTypes : [];
    const t = types.find((x) => String(x.id) === String(id));
    if (!t) return;
    const startEl = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-dialog-start")
    );
    const endEl = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-dialog-end")
    );
    const ds = (t.default_start_time ?? "").slice(0, 5);
    const de = (t.default_end_time ?? "").slice(0, 5);
    if (startEl && ds) startEl.value = ds;
    if (endEl && de) endEl.value = de;
}

/* ────────────────────── Drag & Drop ──────────────────────────── */

function scheduleDropCell(event, date, userId) {
    event.preventDefault();
    if (!_dragShiftId) return;

    const id = _dragShiftId;
    _dragShiftId = null;

    const body = { date };
    if (userId) body.user_id = userId;

    apiFetch("PATCH", `${_cfg.routes.shiftsUpdate}/${id}`, body)
        .then(() => window.location.reload())
        .catch((err) =>
            notifyError(err.message ?? __("js.schedule.move_failed")),
        );
}

/* ─────────────────────── Shift dialog ────────────────────────── */

/**
 * Feature 007: fetch ranked staffing suggestions for an open slot and let the
 * planner pick a candidate. The pick prefills the shift dialog (the regular
 * store() path then re-checks compliance before the assignment is saved).
 */
async function scheduleSuggestStaffing(date, shiftTypeSqid, typeName) {
    const base = _cfg?.routes?.staffingSuggest;
    if (!base) return;
    const url = `${base}?date=${encodeURIComponent(date)}&shift_type_id=${encodeURIComponent(shiftTypeSqid)}`;
    let data;
    try {
        data = await apiFetch("GET", url);
    } catch (e) {
        notifyError(e.message ?? __("js.schedule.suggest_failed"));
        return;
    }
    const suggestions = Array.isArray(data?.suggestions)
        ? data.suggestions
        : [];
    renderStaffingSuggestions(date, shiftTypeSqid, typeName, suggestions);
}

function renderStaffingSuggestions(date, shiftTypeSqid, typeName, suggestions) {
    let dlg = /** @type {HTMLDialogElement | null} */ (
        document.getElementById("staffing-suggest-dialog")
    );
    if (!dlg) {
        dlg = document.createElement("dialog");
        dlg.id = "staffing-suggest-dialog";
        dlg.className = "modal";
        document.body.appendChild(dlg);
    }
    const rows = suggestions.length
        ? suggestions.map((s) => {
              const reasons = (s.reasons || []).map((r) => escHtml(r));
              const warnings = (s.warnings || []).length
                  ? html`<div class="text-warning text-[0.7rem]">
                        ⚠ ${(s.warnings || []).map((w) => escHtml(w))}
                    </div>`
                  : "";
              return html`<tr class="hover">
                  <td>${s.name}</td>
                  <td class="text-right">
                      <span class="badge badge-sm">${Number(s.score)}</span>
                  </td>
                  <td class="text-[0.7rem] opacity-80">
                      ${reasons}${warnings}
                  </td>
                  <td class="text-right">
                      <button
                          type="button"
                          class="btn btn-primary btn-xs"
                          data-assign-suggest
                          data-date="${date}"
                          data-slot-type-sqid="${shiftTypeSqid}"
                          data-user-sqid="${s.user_sqid}"
                      >
                          Zuweisen
                      </button>
                  </td>
              </tr>`;
          })
        : html`<tr>
              <td colspan="4" class="text-center text-sm opacity-60">
                  Keine geeigneten Kandidaten
              </td>
          </tr>`;

    setHtml(
        dlg,
        html` <div class="modal-box max-w-2xl">
                <h3 class="text-lg font-semibold mb-1">Besetzungsvorschläge</h3>
                <p class="text-sm opacity-70 mb-3">${typeName} · ${date}</p>
                <table class="table table-sm table-zebra">
                    <thead>
                        <tr>
                            <th>Mitarbeiter</th>
                            <th class="text-right">Score</th>
                            <th>Begründung</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-sm">Schließen</button>
                    </form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>`,
    );
    dlg.showModal();
}

function scheduleAssignSuggested(date, shiftTypeSqid, userSqid) {
    const dlg = /** @type {HTMLDialogElement | null} */ (
        document.getElementById("staffing-suggest-dialog")
    );
    if (dlg) dlg.close();
    openShiftDialog({ date, shiftTypeId: shiftTypeSqid, userId: userSqid });
}

function openShiftDialog({
    date = null,
    userId = null,
    shiftId = null,
    shift = null,
    shiftTypeId = null,
} = {}) {
    const dlg = /** @type {HTMLDialogElement | null} */ (
        document.getElementById("shift-dialog")
    );
    if (!dlg) return;

    const isEdit = !!shiftId;

    // Reset form
    /** @type {HTMLFormElement} */ (
        document.getElementById("shift-dialog-form")
    ).reset();
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-dialog-id")
    ).value = isEdit ? shiftId : "";
    document.getElementById("shift-dialog-error").classList.add("hidden");
    document.getElementById("shift-dialog-compliance")?.classList.add("hidden");
    document
        .getElementById("shift-dialog-override-row")
        ?.classList.add("hidden");
    const _ovr = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-dialog-override")
    );
    if (_ovr) _ovr.checked = false;
    document
        .getElementById("shift-dialog-delete")
        .classList.toggle("hidden", !isEdit);
    document
        .getElementById("shift-dialog-status-row")
        .classList.toggle("hidden", !isEdit);

    // Publish / Confirm buttons (state-driven, edit only)
    const status = isEdit ? (shift?.status ?? "") : "";
    const isAdmin = !!_cfg?.isAdmin;
    const isOwner =
        isEdit &&
        String(shift?.user_id ?? "") === String(_cfg?.currentUserId ?? "");
    const showPublish = isEdit && isAdmin && status === "draft";
    const showConfirm = isEdit && isOwner && status === "published";
    document
        .getElementById("shift-dialog-publish")
        ?.classList.toggle("hidden", !showPublish);
    document
        .getElementById("shift-dialog-confirm")
        ?.classList.toggle("hidden", !showConfirm);
    document.getElementById("shift-dialog-title").textContent = isEdit
        ? _t("Schicht bearbeiten")
        : _t("Schicht anlegen");

    // Populate fields
    const userEl = /** @type {HTMLSelectElement | null} */ (
        document.getElementById("shift-dialog-user")
    );
    const dateEl = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-dialog-date")
    );
    const typeEl = /** @type {HTMLSelectElement | null} */ (
        document.getElementById("shift-dialog-type")
    );
    const startEl = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-dialog-start")
    );
    const endEl = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-dialog-end")
    );
    const noteEl = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-dialog-note")
    );
    const statEl = /** @type {HTMLSelectElement | null} */ (
        document.getElementById("shift-dialog-status")
    );

    if (isEdit && shift) {
        if (userEl?.tagName === "SELECT") userEl.value = shift.user_id ?? "";
        dateEl.value = shift.date ?? "";
        typeEl.value = shift.shift_type_id ?? "";
        startEl.value = (shift.start_time ?? "").slice(0, 5);
        endEl.value = (shift.end_time ?? "").slice(0, 5);
        noteEl.value = shift.note ?? "";
        if (statEl) statEl.value = shift.status ?? "draft";
    } else {
        if (userEl?.tagName === "SELECT" && userId) userEl.value = userId;
        dateEl.value = date ?? "";
        if (shiftTypeId && typeEl) typeEl.value = shiftTypeId;
    }

    dlg.showModal();
}

async function onShiftDialogSave(event) {
    event.preventDefault();

    const id = /** @type {HTMLInputElement} */ (
        document.getElementById("shift-dialog-id")
    ).value;
    const isEdit = !!id;
    const errEl = document.getElementById("shift-dialog-error");
    const saveBtn = /** @type {HTMLButtonElement | null} */ (
        document.getElementById("shift-dialog-save")
    );
    const compEl = document.getElementById("shift-dialog-compliance");
    const compList = document.getElementById("shift-dialog-compliance-list");
    const overrideRow = document.getElementById("shift-dialog-override-row");
    const overrideEl = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-dialog-override")
    );

    errEl.classList.add("hidden");
    saveBtn.disabled = true;
    saveBtn.textContent = "…";

    const body = {
        user_id:
            /** @type {HTMLInputElement | null} */ (
                document.getElementById("shift-dialog-user")
            )?.value ?? null,
        date: /** @type {HTMLInputElement} */ (
            document.getElementById("shift-dialog-date")
        ).value,
        shift_type_id:
            /** @type {HTMLInputElement} */ (
                document.getElementById("shift-dialog-type")
            ).value || null,
        start_time:
            /** @type {HTMLInputElement} */ (
                document.getElementById("shift-dialog-start")
            ).value || null,
        end_time:
            /** @type {HTMLInputElement} */ (
                document.getElementById("shift-dialog-end")
            ).value || null,
        note:
            /** @type {HTMLInputElement} */ (
                document.getElementById("shift-dialog-note")
            ).value || null,
    };
    if (isEdit) {
        body.status =
            /** @type {HTMLInputElement | null} */ (
                document.getElementById("shift-dialog-status")
            )?.value ?? undefined;
    }
    if (overrideEl?.checked) {
        body.override_compliance = 1;
    }

    try {
        const data = isEdit
            ? await apiFetch("PUT", `${_cfg.routes.shiftsUpdate}/${id}`, body)
            : await apiFetch("POST", _cfg.routes.shiftsStore, body);

        if (
            data &&
            Array.isArray(data.compliance_warnings) &&
            data.compliance_warnings.length > 0
        ) {
            // Soft warnings (mode=warn) — kurz anzeigen, dann reload.
            showComplianceWarnings(data.compliance_warnings, false);
            setTimeout(() => {
                /** @type {HTMLDialogElement} */ (
                    document.getElementById("shift-dialog")
                ).close();
                window.location.reload();
            }, 1500);
            return;
        }
        /** @type {HTMLDialogElement} */ (
            document.getElementById("shift-dialog")
        ).close();
        window.location.reload();
    } catch (err) {
        // Compliance-Block (mode=block)?
        const violations = err.complianceViolations;
        if (Array.isArray(violations) && violations.length > 0) {
            showComplianceWarnings(violations, true);
        } else {
            errEl.textContent = err.message ?? _t("Fehler beim Speichern.");
            errEl.classList.remove("hidden");
        }
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = _t("Speichern");
    }
}

function showComplianceWarnings(violations, allowOverride) {
    const compEl = document.getElementById("shift-dialog-compliance");
    const compList = document.getElementById("shift-dialog-compliance-list");
    const overrideRow = document.getElementById("shift-dialog-override-row");
    if (!compEl || !compList) return;
    clearHtml(compList);
    for (const v of violations) {
        const li = document.createElement("li");
        li.textContent =
            typeof v === "string" ? v : (v.message ?? JSON.stringify(v));
        compList.appendChild(li);
    }
    compEl.classList.remove("hidden");
    if (overrideRow) {
        overrideRow.classList.toggle("hidden", !allowOverride);
    }
}

async function onShiftDialogDelete() {
    const id = /** @type {HTMLInputElement} */ (
        document.getElementById("shift-dialog-id")
    ).value;
    if (!id) return;
    const ok = await (window.confirmAction
        ? window.confirmAction({
              message: _t("Schicht wirklich löschen?"),
              label: _t("Löschen"),
          })
        : Promise.resolve(true));
    if (!ok) return;

    try {
        await apiFetch("DELETE", `${_cfg.routes.shiftsDestroy}/${id}`);
        /** @type {HTMLDialogElement} */ (
            document.getElementById("shift-dialog")
        ).close();
        window.location.reload();
    } catch (err) {
        notifyError(err.message ?? _t("Fehler beim Löschen."));
    }
}

async function onShiftDialogPublish() {
    const id = /** @type {HTMLInputElement} */ (
        document.getElementById("shift-dialog-id")
    ).value;
    if (!id) return;
    try {
        await apiFetch("PATCH", `${_cfg.routes.shiftsPublish}/${id}/publish`);
        /** @type {HTMLDialogElement} */ (
            document.getElementById("shift-dialog")
        ).close();
        window.location.reload();
    } catch (err) {
        notifyError(err.message ?? _t("Fehler beim Veröffentlichen."));
    }
}

async function onShiftDialogConfirm() {
    const id = /** @type {HTMLInputElement} */ (
        document.getElementById("shift-dialog-id")
    ).value;
    if (!id) return;
    try {
        await apiFetch("PATCH", `${_cfg.routes.shiftsConfirm}/${id}/confirm`);
        /** @type {HTMLDialogElement} */ (
            document.getElementById("shift-dialog")
        ).close();
        window.location.reload();
    } catch (err) {
        notifyError(err.message ?? _t("Fehler beim Bestätigen."));
    }
}

/* ─────────────────── Shift-type manager ──────────────────────── */

function shiftTypeOpenEdit(typeId, type) {
    /** @type {HTMLFormElement | null} */ (
        document.getElementById("shift-type-form")
    )?.reset();
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-id")
    ).value = typeId;
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-name")
    ).value = type.name ?? "";
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-abbr")
    ).value = type.abbreviation ?? "";
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-color")
    ).value = type.color ?? "#3b82f6";
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-start")
    ).value = (type.default_start_time ?? "").slice(0, 5);
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-end")
    ).value = (type.default_end_time ?? "").slice(0, 5);
    const active = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-type-active")
    );
    if (active) active.checked = type.is_active ?? true;

    document.getElementById("shift-type-form-title").textContent = _t(
        "Schichttyp bearbeiten",
    );
    document.getElementById("shift-type-error")?.classList.add("hidden");
}

function shiftTypeResetForm() {
    /** @type {HTMLFormElement | null} */ (
        document.getElementById("shift-type-form")
    )?.reset();
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-id")
    ).value = "";
    document.getElementById("shift-type-form-title").textContent = _t(
        "Neuen Schichttyp anlegen",
    );
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-color")
    ).value = "#3b82f6";
    /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-active")
    ).checked = true;
    document.getElementById("shift-type-error")?.classList.add("hidden");
}

async function onShiftTypeSave(event) {
    event.preventDefault();

    const id = /** @type {HTMLInputElement} */ (
        document.getElementById("shift-type-id")
    ).value;
    const isEdit = !!id;
    const errEl = document.getElementById("shift-type-error");
    const saveBtn = /** @type {HTMLButtonElement | null} */ (
        document.getElementById("shift-type-save")
    );

    errEl?.classList.add("hidden");
    saveBtn.disabled = true;

    const active = /** @type {HTMLInputElement | null} */ (
        document.getElementById("shift-type-active")
    );
    const body = {
        name: /** @type {HTMLInputElement} */ (
            document.getElementById("shift-type-name")
        ).value,
        abbreviation: /** @type {HTMLInputElement} */ (
            document.getElementById("shift-type-abbr")
        ).value,
        color: /** @type {HTMLInputElement} */ (
            document.getElementById("shift-type-color")
        ).value,
        default_start_time:
            /** @type {HTMLInputElement} */ (
                document.getElementById("shift-type-start")
            ).value || null,
        default_end_time:
            /** @type {HTMLInputElement} */ (
                document.getElementById("shift-type-end")
            ).value || null,
        is_active: active ? (active.checked ? 1 : 0) : 1,
    };

    try {
        const url = isEdit
            ? `${_cfg.routes.typesUpdate}/${id}`
            : _cfg.routes.typesStore;
        const data = await apiFetch(isEdit ? "PUT" : "POST", url, body);
        // Update row in table or add new row
        if (isEdit) {
            updateTypeRow(id, data);
            updateTypeOption(id, data);
        } else {
            addTypeRow(data);
            addTypeOption(data);
        }
        shiftTypeResetForm();
    } catch (err) {
        if (errEl) {
            errEl.textContent = err.message ?? _t("Fehler beim Speichern.");
            errEl.classList.remove("hidden");
        }
    } finally {
        saveBtn.disabled = false;
    }
}

async function shiftTypeDelete(typeId) {
    const ok = await (window.confirmAction
        ? window.confirmAction({
              message: _t("Schichttyp wirklich löschen?"),
              label: _t("Löschen"),
          })
        : Promise.resolve(true));
    if (!ok) return;
    try {
        await apiFetch("DELETE", `${_cfg.routes.typesDestroy}/${typeId}`);
        document.querySelector(`[data-type-row="${typeId}"]`)?.remove();
        removeTypeOption(typeId);
    } catch (err) {
        notifyError(err.message ?? _t("Fehler beim Löschen."));
    }
}

function addTypeOption(type) {
    const sel = document.getElementById("shift-dialog-type");
    if (!sel) return;
    if (sel.querySelector(`option[value="${type.id}"]`)) return;
    const opt = document.createElement("option");
    opt.value = String(type.id);
    opt.style.color = type.color ?? "";
    opt.textContent = `${type.name} (${type.abbreviation})`;
    sel.appendChild(opt);
    // Sync in-memory cache so other code paths can use it.
    if (Array.isArray(_cfg?.shiftTypes)) _cfg.shiftTypes.push(type);
}

function updateTypeOption(id, type) {
    const sel = document.getElementById("shift-dialog-type");
    if (!sel) return;
    const opt = /** @type {HTMLElement | null} */ (
        sel.querySelector(`option[value="${id}"]`)
    );
    if (!opt) {
        addTypeOption(type);
        return;
    }
    opt.style.color = type.color ?? "";
    opt.textContent = `${type.name} (${type.abbreviation})`;
    if (Array.isArray(_cfg?.shiftTypes)) {
        const i = _cfg.shiftTypes.findIndex((t) => String(t.id) === String(id));
        if (i >= 0) _cfg.shiftTypes[i] = type;
    }
}

function removeTypeOption(id) {
    const sel = document.getElementById("shift-dialog-type");
    sel?.querySelector(`option[value="${id}"]`)?.remove();
    if (Array.isArray(_cfg?.shiftTypes)) {
        _cfg.shiftTypes = _cfg.shiftTypes.filter(
            (t) => String(t.id) !== String(id),
        );
    }
}

function updateTypeRow(id, type) {
    const row = document.querySelector(`[data-type-row="${id}"]`);
    if (!row) return;
    const q = (sel) => row.querySelector(sel);
    const colorSwatch = /** @type {HTMLElement | null} */ (
        row.querySelector("span.inline-block")
    );
    if (colorSwatch)
        colorSwatch.style.backgroundColor = type.color ?? "#6b7280";
    const abbrEl = document.getElementById(`type-abbr-${id}`);
    if (abbrEl) abbrEl.textContent = type.abbreviation ?? "";
    const nameEl = document.getElementById(`type-name-${id}`);
    if (nameEl) nameEl.textContent = type.name ?? "";
    const startEl = document.getElementById(`type-start-${id}`);
    if (startEl) startEl.textContent = type.default_start_time ?? "–";
    const endEl = document.getElementById(`type-end-${id}`);
    if (endEl) endEl.textContent = type.default_end_time ?? "–";
}

function addTypeRow(type) {
    const tbody = document.getElementById("shift-type-table-body");
    if (!tbody) return;
    const tr = document.createElement("tr");
    tr.setAttribute("data-type-row", type.id);
    // escCssValue statt escHtml: `color` landet im style-Attribut, also im
    // CSS-Kontext — dort schützt Entity-Escaping nicht vor eingeschleusten
    // Deklarationen. Eine Wert-Allowlist entscheidet.
    setHtml(
        tr,
        html` <td>
                <span
                    class="inline-block h-4 w-4 rounded"
                    style="background:${escCssValue(type.color)};"
                ></span>
            </td>
            <td class="font-mono font-bold" id="type-abbr-${type.id}">
                ${type.abbreviation}
            </td>
            <td id="type-name-${type.id}">${type.name}</td>
            <td id="type-start-${type.id}">
                ${type.default_start_time ?? "–"}
            </td>
            <td id="type-end-${type.id}">${type.default_end_time ?? "–"}</td>
            <td>
                <span
                    class="badge badge-sm ${type.is_active
                        ? "badge-success"
                        : "badge-ghost"}"
                    >${type.is_active ? "ja" : "nein"}</span
                >
            </td>
            <td class="text-right">
                <button
                    type="button"
                    data-type-edit="${type.id}"
                    data-type-payload="${JSON.stringify(type)}"
                    class="btn btn-sm btn-ghost"
                >
                    Bearbeiten
                </button>
                <button
                    type="button"
                    data-type-delete="${type.id}"
                    class="btn btn-sm btn-ghost text-error"
                >
                    Löschen
                </button>
            </td>`,
    );
    tbody.appendChild(tr);
}

/* ──────────────────────── Utilities ──────────────────────────── */

/**
 * Generic fetch helper — sends JSON, returns parsed JSON, throws on errors.
 */
async function apiFetch(method, url, body = null) {
    const opts = {
        method,
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": _cfg?.csrf ?? "",
        },
    };
    if (body && method !== "GET") opts.body = JSON.stringify(body);

    const resp = await fetch(url, opts);

    if (resp.status === 204) return null; // DELETE success

    const json = await resp.json().catch(() => null);

    if (!resp.ok) {
        // Laravel validation errors (422) return { errors: { field: [...] } }
        if (json?.errors) {
            const compliance = json.errors.compliance;
            const msgs = Object.values(json.errors).flat().join(" ");
            const e = new Error(msgs);
            if (Array.isArray(compliance)) {
                /** @type {any} */ (e).complianceViolations = compliance;
            }
            throw e;
        }
        throw new Error(json?.message ?? `HTTP ${resp.status}`);
    }

    return json;
}

/** Simple translation stub — returns the key (labels are in German anyway). */
function _t(key) {
    return key;
}

// Escaping läuft zentral über lib/html.js (siehe Import am Dateikopf).
// Die früheren lokalen escHtml/escAttr hatten Lücken: escHtml escapte kein
// einfaches Anführungszeichen, escAttr kein kaufmännisches Und.
