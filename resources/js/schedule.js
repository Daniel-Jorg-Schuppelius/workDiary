/**
 * schedule.js — Shift schedule interactivity (vanilla JS, no Alpine)
 *
 * Reads window.__scheduleConfig set by the Blade index view.
 * Functions exposed globally so inline handlers in Blade can call them.
 */

/* ──────────────────────────── State ──────────────────────────── */

let _cfg = null; // window.__scheduleConfig
let _dragShiftId = null; // shift id being dragged

/* ──────────────────────── Bootstrap ──────────────────────────── */

document.addEventListener("DOMContentLoaded", function () {
    _cfg = window.__scheduleConfig ?? {};

    // Open type-manager dialog button (handled in partial inline script, but
    // also guard here in case partial script runs before this file)
    document
        .getElementById("btn-open-type-manager")
        ?.addEventListener("click", function () {
            document.getElementById("shift-type-manager")?.showModal();
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
    const startEl = document.getElementById("shift-dialog-start");
    const endEl = document.getElementById("shift-dialog-end");
    const ds = (t.default_start_time ?? "").slice(0, 5);
    const de = (t.default_end_time ?? "").slice(0, 5);
    if (startEl && ds) startEl.value = ds;
    if (endEl && de) endEl.value = de;
}

/* ──────────────────────── Cell click ─────────────────────────── */

/**
 * Called by data-schedule-cell onclick in _week_matrix / _month_matrix.
 * Opens dialog for a NEW shift on that date/user.
 */
window.scheduleCellClick = function (event, date, userId) {
    if (event.target.closest(".schedule-shift-badge")) return; // badge handles its own click
    openShiftDialog({ date, userId });
};

/* ────────────────────── Drag & Drop ──────────────────────────── */

window.scheduleDragStart = function (event, shiftId) {
    _dragShiftId = shiftId;
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(shiftId));
};

window.scheduleDropCell = function (event, date, userId) {
    event.preventDefault();
    if (!_dragShiftId) return;

    const id = _dragShiftId;
    _dragShiftId = null;

    const body = { date };
    if (userId) body.user_id = userId;

    apiFetch("PATCH", `${_cfg.routes.shiftsUpdate}/${id}`, body)
        .then(() => window.location.reload())
        .catch((err) => alert(err.message ?? __("Fehler beim Verschieben.")));
};

/* ─────────────────────── Shift dialog ────────────────────────── */

/**
 * Open dialog to EDIT an existing shift.
 * Called by shift badge onclick in the Blade partials.
 */
window.scheduleOpenEditDialog = function (shiftId, shift) {
    openShiftDialog({ shiftId, shift });
};

/**
 * Open dialog to CREATE a shift for an open slot (date + shift type prefilled).
 * Called by open-slot placeholder buttons in the matrix.
 */
window.scheduleOpenSlotDialog = function (date, shiftTypeId) {
    openShiftDialog({ date, shiftTypeId });
};

function openShiftDialog({
    date = null,
    userId = null,
    shiftId = null,
    shift = null,
    shiftTypeId = null,
} = {}) {
    const dlg = document.getElementById("shift-dialog");
    if (!dlg) return;

    const isEdit = !!shiftId;

    // Reset form
    document.getElementById("shift-dialog-form").reset();
    document.getElementById("shift-dialog-id").value = isEdit ? shiftId : "";
    document.getElementById("shift-dialog-error").classList.add("hidden");
    document.getElementById("shift-dialog-compliance")?.classList.add("hidden");
    document
        .getElementById("shift-dialog-override-row")
        ?.classList.add("hidden");
    const _ovr = document.getElementById("shift-dialog-override");
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
        isEdit && Number(shift?.user_id) === Number(_cfg?.currentUserId ?? 0);
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
    const userEl = document.getElementById("shift-dialog-user");
    const dateEl = document.getElementById("shift-dialog-date");
    const typeEl = document.getElementById("shift-dialog-type");
    const startEl = document.getElementById("shift-dialog-start");
    const endEl = document.getElementById("shift-dialog-end");
    const noteEl = document.getElementById("shift-dialog-note");
    const statEl = document.getElementById("shift-dialog-status");

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

    const id = document.getElementById("shift-dialog-id").value;
    const isEdit = !!id;
    const errEl = document.getElementById("shift-dialog-error");
    const saveBtn = document.getElementById("shift-dialog-save");
    const compEl = document.getElementById("shift-dialog-compliance");
    const compList = document.getElementById("shift-dialog-compliance-list");
    const overrideRow = document.getElementById("shift-dialog-override-row");
    const overrideEl = document.getElementById("shift-dialog-override");

    errEl.classList.add("hidden");
    saveBtn.disabled = true;
    saveBtn.textContent = "…";

    const body = {
        user_id: document.getElementById("shift-dialog-user")?.value ?? null,
        date: document.getElementById("shift-dialog-date").value,
        shift_type_id:
            document.getElementById("shift-dialog-type").value || null,
        start_time: document.getElementById("shift-dialog-start").value || null,
        end_time: document.getElementById("shift-dialog-end").value || null,
        note: document.getElementById("shift-dialog-note").value || null,
    };
    if (isEdit) {
        body.status =
            document.getElementById("shift-dialog-status")?.value ?? undefined;
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
                document.getElementById("shift-dialog").close();
                window.location.reload();
            }, 1500);
            return;
        }
        document.getElementById("shift-dialog").close();
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
    compList.innerHTML = "";
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
    const id = document.getElementById("shift-dialog-id").value;
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
        document.getElementById("shift-dialog").close();
        window.location.reload();
    } catch (err) {
        alert(err.message ?? _t("Fehler beim Löschen."));
    }
}

async function onShiftDialogPublish() {
    const id = document.getElementById("shift-dialog-id").value;
    if (!id) return;
    try {
        await apiFetch("PATCH", `${_cfg.routes.shiftsPublish}/${id}/publish`);
        document.getElementById("shift-dialog").close();
        window.location.reload();
    } catch (err) {
        alert(err.message ?? _t("Fehler beim Veröffentlichen."));
    }
}

async function onShiftDialogConfirm() {
    const id = document.getElementById("shift-dialog-id").value;
    if (!id) return;
    try {
        await apiFetch("PATCH", `${_cfg.routes.shiftsConfirm}/${id}/confirm`);
        document.getElementById("shift-dialog").close();
        window.location.reload();
    } catch (err) {
        alert(err.message ?? _t("Fehler beim Bestätigen."));
    }
}

/* ─────────────────── Shift-type manager ──────────────────────── */

window.shiftTypeOpenEdit = function (typeId, type) {
    document.getElementById("shift-type-form")?.reset();
    document.getElementById("shift-type-id").value = typeId;
    document.getElementById("shift-type-name").value = type.name ?? "";
    document.getElementById("shift-type-abbr").value = type.abbreviation ?? "";
    document.getElementById("shift-type-color").value = type.color ?? "#3b82f6";
    document.getElementById("shift-type-start").value = (
        type.default_start_time ?? ""
    ).slice(0, 5);
    document.getElementById("shift-type-end").value = (
        type.default_end_time ?? ""
    ).slice(0, 5);
    const active = document.getElementById("shift-type-active");
    if (active) active.checked = type.is_active ?? true;

    document.getElementById("shift-type-form-title").textContent = _t(
        "Schichttyp bearbeiten",
    );
    document.getElementById("shift-type-error")?.classList.add("hidden");
};

window.shiftTypeResetForm = function () {
    document.getElementById("shift-type-form")?.reset();
    document.getElementById("shift-type-id").value = "";
    document.getElementById("shift-type-form-title").textContent = _t(
        "Neuen Schichttyp anlegen",
    );
    document.getElementById("shift-type-color").value = "#3b82f6";
    document.getElementById("shift-type-active").checked = true;
    document.getElementById("shift-type-error")?.classList.add("hidden");
};

async function onShiftTypeSave(event) {
    event.preventDefault();

    const id = document.getElementById("shift-type-id").value;
    const isEdit = !!id;
    const errEl = document.getElementById("shift-type-error");
    const saveBtn = document.getElementById("shift-type-save");

    errEl?.classList.add("hidden");
    saveBtn.disabled = true;

    const active = document.getElementById("shift-type-active");
    const body = {
        name: document.getElementById("shift-type-name").value,
        abbreviation: document.getElementById("shift-type-abbr").value,
        color: document.getElementById("shift-type-color").value,
        default_start_time:
            document.getElementById("shift-type-start").value || null,
        default_end_time:
            document.getElementById("shift-type-end").value || null,
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

window.shiftTypeDelete = async function (typeId) {
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
        alert(err.message ?? _t("Fehler beim Löschen."));
    }
};

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
    const opt = sel.querySelector(`option[value="${id}"]`);
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
    const colorSwatch = row.querySelector("span.inline-block");
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
    tr.innerHTML = `
        <td><span class="inline-block h-4 w-4 rounded" style="background:${escHtml(type.color)};"></span></td>
        <td class="font-mono font-bold" id="type-abbr-${type.id}">${escHtml(type.abbreviation)}</td>
        <td id="type-name-${type.id}">${escHtml(type.name)}</td>
        <td id="type-start-${type.id}">${escHtml(type.default_start_time ?? "–")}</td>
        <td id="type-end-${type.id}">${escHtml(type.default_end_time ?? "–")}</td>
        <td><span class="badge badge-sm ${type.is_active ? "badge-success" : "badge-ghost"}">${type.is_active ? "ja" : "nein"}</span></td>
        <td class="text-right">
            <button type="button" onclick="shiftTypeOpenEdit(${type.id}, ${escAttr(JSON.stringify(type))})" class="btn btn-xs btn-ghost">Bearbeiten</button>
            <button type="button" onclick="shiftTypeDelete(${type.id})" class="btn btn-xs btn-ghost text-error">Löschen</button>
        </td>`;
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
                e.complianceViolations = compliance;
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

/** Escape for HTML text nodes */
function escHtml(str) {
    return String(str ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

/** Escape for HTML attribute values (single-quoted context) */
function escAttr(str) {
    return String(str ?? "")
        .replace(/'/g, "&#039;")
        .replace(/"/g, "&quot;");
}
