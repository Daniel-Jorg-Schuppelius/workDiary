// Quick-Buchung offener Zeitblöcke (MVP-015, Rang 37).
//
// Progressive Enhancement über den No-JS-Fallback: jedes Block-Formular
// (`.qb-form`) bleibt ohne JS ganz normal absendbar (Server-Redirect). Mit JS
// kommen hinzu: (a) einen Block per Drag auf ein Projekt-Ziel ziehen, (b)
// Ctrl/Cmd+Enter im Fokus-Formular = buchen + weiter. Beide Wege posten JSON
// an denselben Endpunkt und laden danach die Seite neu (nächster offener Block
// wird automatisch der erste).

function csrfToken() {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || ""
    );
}

async function postBooking(url, payload) {
    const res = await fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken(),
            "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(payload),
    });
    return res.ok;
}

function payloadFromForm(form) {
    const data = new FormData(form);
    const payload = {};
    for (const [key, value] of data.entries()) {
        if (key !== "_token" && value !== "") payload[key] = value;
    }
    return payload;
}

export function bindQuickBook() {
    const panel = /** @type {HTMLElement | null} */ (
        document.querySelector("[data-qb-panel]")
    );
    if (!panel) return;
    const url = panel.getAttribute("data-qb-url");
    if (!url) return;

    // (a) Fallback-Formular je Block: submit abfangen → JSON posten → reload.
    panel.addEventListener("submit", async (event) => {
        const form = /** @type {HTMLFormElement | null} */ (
            /** @type {HTMLElement} */ (event.target).closest(".qb-form")
        );
        if (!form) return;
        // Ungültiges Formular (kein Projekt) → native Validierung greifen lassen.
        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }
        event.preventDefault();
        if (await postBooking(url, payloadFromForm(form))) {
            window.location.reload();
        } else {
            form.submit(); // harter Fallback ohne Enhancement
        }
    });

    // (b) Ctrl/Cmd+Enter im Block-Formular = buchen + weiter.
    panel.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" || !(event.ctrlKey || event.metaKey)) return;
        const form = /** @type {HTMLFormElement | null} */ (
            /** @type {HTMLElement} */ (event.target).closest(".qb-form")
        );
        if (!form) return;
        event.preventDefault();
        form.requestSubmit();
    });

    // (c) Drag eines Blocks auf ein Projekt-Ziel.
    panel.addEventListener("dragstart", (event) => {
        const block = /** @type {HTMLElement} */ (event.target).closest(
            "[data-qb-block]",
        );
        if (!block) return;
        event.dataTransfer.effectAllowed = "copy";
        event.dataTransfer.setData(
            "application/json",
            JSON.stringify({
                started_at: block.getAttribute("data-started-at"),
                ended_at: block.getAttribute("data-ended-at"),
            }),
        );
    });

    panel.addEventListener("dragover", (event) => {
        const target = /** @type {HTMLElement} */ (event.target).closest(
            "[data-qb-target]",
        );
        if (!target) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = "copy";
        target.classList.add("qb-target-over");
    });

    panel.addEventListener("dragleave", (event) => {
        const target = /** @type {HTMLElement} */ (event.target).closest(
            "[data-qb-target]",
        );
        target?.classList.remove("qb-target-over");
    });

    panel.addEventListener("drop", async (event) => {
        const target = /** @type {HTMLElement} */ (event.target).closest(
            "[data-qb-target]",
        );
        if (!target) return;
        event.preventDefault();
        target.classList.remove("qb-target-over");

        let block;
        try {
            block = JSON.parse(event.dataTransfer.getData("application/json"));
        } catch {
            return;
        }
        if (!block?.started_at || !block?.ended_at) return;

        const ok = await postBooking(url, {
            project: target.getAttribute("data-project"),
            started_at: block.started_at,
            ended_at: block.ended_at,
        });
        if (ok) window.location.reload();
    });
}

document.addEventListener("DOMContentLoaded", () => bindQuickBook());
