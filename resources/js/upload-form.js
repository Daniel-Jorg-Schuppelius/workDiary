/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : upload-form.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/**
 * Mehrfachupload mit Fortschritt (MVP-1074) für Formulare mit
 * `data-upload-form` (Grenzen und Texte im `data-upload-config`-Block der
 * Komponente `x-upload-input`). Ohne JS sendet das Formular normal; mit JS prüft das
 * Modul Format, Größe und Anzahl vorab, sendet per XHR (Fortschrittsbalken)
 * und zeigt Serverfehler je Datei. Texte und Grenzen kommen serverseitig als
 * data-Attribute — das Portal hat kein JS-Übersetzungsbündel.
 */
import { sameOriginPath } from "./lib/html.js";

/**
 * @param {string | undefined} template
 * @param {Record<string, string | number>} replace
 * @returns {string}
 */
function fill(template, replace) {
    let text = String(template ?? "");
    for (const [key, value] of Object.entries(replace)) {
        text = text.split(":" + key).join(String(value));
    }
    return text;
}

/**
 * @param {number} bytes
 * @returns {string}
 */
function formatBytes(bytes) {
    const units = ["B", "KB", "MB", "GB"];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    return (unit === 0 ? String(value) : value.toFixed(1)) + " " + units[unit];
}

/**
 * @param {HTMLFormElement} form
 */
function bindUploadForm(form) {
    const config = form.querySelector("[data-upload-config]");
    const d = config instanceof HTMLElement ? config.dataset : form.dataset;
    const maxBytes = Number(d.maxBytes || 0);
    const maxTotal = Number(d.maxTotalBytes || 0);
    const maxFiles = Number(d.maxFiles || 0);
    const extensions = String(d.extensions || "")
        .split(",")
        .map((e) => e.trim().toLowerCase())
        .filter(Boolean);
    /** @type {HTMLInputElement | null} */
    const input = form.querySelector("[data-upload-input]");
    const list = form.querySelector("[data-upload-list]");
    const errors = form.querySelector("[data-upload-errors]");
    /** @type {HTMLProgressElement | null} */
    const progress = form.querySelector("[data-upload-progress]");
    const status = form.querySelector("[data-upload-status]");
    /** @type {HTMLButtonElement | null} */
    const submit = form.querySelector("[type=submit]");

    /** @param {string[]} messages */
    const showErrors = (messages) => {
        if (!errors) return;
        errors.replaceChildren();
        for (const message of messages) {
            const li = document.createElement("li");
            li.textContent = message;
            errors.appendChild(li);
        }
        errors.toggleAttribute("hidden", messages.length === 0);
        if (messages.length > 0 && errors instanceof HTMLElement) {
            errors.focus();
        }
    };

    /** @returns {string[]} */
    const clientErrors = () => {
        const files = input?.files ? Array.from(input.files) : [];
        const messages = [];
        let total = 0;
        for (const file of files) {
            total += file.size;
            const ext = file.name.includes(".") ? file.name.split(".").pop().toLowerCase() : "";
            if (extensions.length > 0 && !extensions.includes(ext)) {
                messages.push(fill(d.msgType, { name: file.name }));
            } else if (maxBytes > 0 && file.size > maxBytes) {
                messages.push(fill(d.msgTooLarge, { name: file.name, size: formatBytes(maxBytes) }));
            }
        }
        if (maxFiles > 0 && files.length > maxFiles) {
            messages.push(fill(d.msgCount, { count: maxFiles }));
        }
        if (maxTotal > 0 && total > maxTotal) {
            messages.push(fill(d.msgTotal, { size: formatBytes(maxTotal) }));
        }
        return messages;
    };

    const renderList = () => {
        if (!list || !input?.files) return;
        list.replaceChildren();
        for (const file of Array.from(input.files)) {
            const li = document.createElement("li");
            li.textContent = file.name + " (" + formatBytes(file.size) + ")";
            list.appendChild(li);
        }
        showErrors(clientErrors());
    };

    input?.addEventListener("change", renderList);

    form.addEventListener("submit", (event) => {
        const messages = clientErrors();
        if (messages.length > 0) {
            event.preventDefault();
            showErrors(messages);
            return;
        }
        if (typeof XMLHttpRequest === "undefined" || typeof FormData === "undefined") {
            return;
        }
        event.preventDefault();
        showErrors([]);

        const xhr = new XMLHttpRequest();
        xhr.open("POST", form.action);
        xhr.setRequestHeader("Accept", "application/json");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        if (submit) submit.disabled = true;
        if (progress) {
            progress.value = 0;
            progress.hidden = false;
        }

        xhr.upload.addEventListener("progress", (e) => {
            if (!e.lengthComputable) return;
            const percent = Math.round((e.loaded / e.total) * 100);
            if (progress) progress.value = percent;
            if (status) status.textContent = fill(d.msgProgress, { percent });
        });

        const fail = (/** @type {string[]} */ texts) => {
            if (submit) submit.disabled = false;
            if (progress) progress.hidden = true;
            if (status) status.textContent = "";
            showErrors(texts);
        };

        xhr.addEventListener("load", () => {
            /** @type {any} */
            let body = null;
            try {
                body = JSON.parse(xhr.responseText || "null");
            } catch (_e) {
                body = null;
            }
            if (xhr.status >= 200 && xhr.status < 300 && body && body.redirect) {
                const target = sameOriginPath(body.redirect);
                if (target !== null) {
                    window.location.assign(target);
                    return;
                }
            }
            if (xhr.status === 422 && body && body.errors) {
                fail(Object.values(body.errors).flat().map(String));
                return;
            }
            if (xhr.status === 413) {
                fail([fill(d.msgTotal, { size: formatBytes(maxTotal) })]);
                return;
            }
            if (xhr.status === 419) {
                window.location.reload();
                return;
            }
            fail([String(d.msgFailed || "")]);
        });
        xhr.addEventListener("error", () => fail([String(d.msgFailed || "")]));
        xhr.addEventListener("abort", () => fail([String(d.msgFailed || "")]));

        xhr.send(new FormData(form));
    });
}

if (typeof document !== "undefined") {
    const init = () => {
        document.querySelectorAll("form[data-upload-form]").forEach((form) => {
            if (form instanceof HTMLFormElement && !form.dataset.uploadBound) {
                form.dataset.uploadBound = "1";
                bindUploadForm(form);
            }
        });
    };
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
}
