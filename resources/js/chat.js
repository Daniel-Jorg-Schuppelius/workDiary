// Chat-Client: lädt/sendet Nachrichten, Echtzeit via Reverb/Echo, Aktionen über
// Event-Delegation (Nachrichten-HTML wird serverseitig gerendert eingefügt).
import { initEcho } from "./echo.js";
import {
    escHtml,
    html,
    setHtml,
    clearHtml,
    trustedServerHtml,
} from "./lib/html.js";

const root = document.getElementById("chat-root");
if (root) {
    initSearch();
    initSidebar(root);
    if (root.dataset.channelId) initChat(root);
}

// Sidebar-Kanalliste live halten – auch OHNE offenen Kanal (Mobil/Listenansicht).
function initSidebar(root) {
    const listEl = document.getElementById("chat-channel-list");
    if (!listEl?.dataset.listUrl) return;
    const activeSqid = root.dataset.channelId || "";
    const refresh = () => {
        fetch(
            listEl.dataset.listUrl +
                (activeSqid ? `?active=${activeSqid}` : ""),
            {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        )
            .then((r) => (r.ok ? r.json() : null))
            // Serverseitig gerenderte Kanalliste (Blade escaped Nutzerdaten).
            .then((d) => {
                if (d && d.html != null)
                    setHtml(listEl, trustedServerHtml(d.html));
            })
            .catch(() => {});
    };
    window.refreshChatChannelList = refresh;
    setInterval(() => {
        if (!document.hidden) refresh();
    }, 12000);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) refresh();
    });
    const meId = root.dataset.meId;
    if (meId) {
        try {
            initEcho()
                .private(`App.Models.User.${meId}`)
                .listen(".channel.list.changed", () => {
                    refresh();
                    window.refreshChatUnread?.();
                });
        } catch (e) {
            /* Echtzeit optional */
        }
    }
}

// Volltextsuche in der Sidebar (funktioniert auch ohne aktiven Kanal).
function initSearch() {
    const input = /** @type {HTMLInputElement | null} */ (
        document.getElementById("chat-search")
    );
    const results = document.getElementById("chat-search-results");
    if (!input || !results) return;
    const hide = () => {
        results.classList.add("hidden");
    };
    let timer;
    input.addEventListener("input", () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) {
            hide();
            clearHtml(results);
            return;
        }
        timer = setTimeout(async () => {
            const r = await fetch(`/chat/search?q=${encodeURIComponent(q)}`, {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            if (!r.ok) return;
            const d = await r.json();
            results.classList.remove("hidden");
            setHtml(
                results,
                d.results.length
                    ? html`${d.results.map(
                          (x) =>
                              html`<a
                                  href="/chat/${x.channel_id}#chat-msg-${x.message_id}"
                                  class="block border-b border-base-200 px-3 py-2 text-sm last:border-b-0 hover:bg-base-200"
                                  ><div class="truncate font-medium">
                                      ${x.channel} ·
                                      <span class="text-base-content/60"
                                          >${x.user || ""}</span
                                      >
                                  </div>
                                  <div class="truncate text-base-content/70">
                                      ${x.snippet}
                                  </div></a
                              >`,
                      )}`
                    : html`<p class="px-3 py-4 text-sm text-base-content/50">
                          ${input.dataset.empty || "Keine Treffer."}
                      </p>`,
            );
        }, 300);
    });
    // Dropdown schließen bei Klick außerhalb.
    document.addEventListener("click", (e) => {
        if (
            !results.contains(/** @type {Node} */ (e.target)) &&
            e.target !== input
        )
            hide();
    });
}

function initChat(root) {
    const channelId = root.dataset.channelId; // Sqid: API-URLs + Echtzeit-Channelname
    if (!channelId) return;
    const list = document.getElementById("chat-messages");
    const csrf =
        /** @type {HTMLMetaElement | null} */ (
            document.querySelector('meta[name="csrf-token"]')
        )?.content || "";
    const base = headersBase(csrf);
    let oldest = null;
    let newest = 0;
    let loadingOlder = false;
    let noMoreOlder = false;
    let socketId = "";

    function headers() {
        return socketId ? { ...base, "X-Socket-ID": socketId } : { ...base };
    }
    const append = (html) => list.insertAdjacentHTML("beforeend", html);
    const prepend = (html) => list.insertAdjacentHTML("afterbegin", html);
    const bottom = () => {
        // Zuverlässig ganz nach unten – auch wenn Inhalte (Bilder) noch nachladen.
        list.scrollTop = list.scrollHeight;
        requestAnimationFrame(() => {
            list.scrollTop = list.scrollHeight;
        });
        setTimeout(() => {
            list.scrollTop = list.scrollHeight;
        }, 60);
    };
    const nearBottom = () =>
        list.scrollHeight - list.scrollTop - list.clientHeight < 120;
    const scrollBtn = document.getElementById("chat-scroll-bottom");
    const updateScrollBtn = () =>
        scrollBtn?.classList.toggle("hidden", nearBottom());
    scrollBtn?.addEventListener("click", () => {
        bottom();
        setTimeout(updateScrollBtn, 80);
    });
    const markRead = () =>
        fetch(`/chat/${channelId}/read`, { method: "POST", headers: headers() })
            .then(() => {
                window.refreshChatUnread?.();
                window.refreshChatChannelList?.();
            })
            .catch(() => {});

    // "… schreibt …"-Anzeige (über Client-Whisper, kein Server-Load).
    const meName = root.dataset.meName || "";
    const typingEl = document.getElementById("chat-typing");
    const typingTpl = root.dataset.txtTyping || ":name schreibt …";
    let typingTimer = null;
    const showTyping = (name) => {
        if (!typingEl || !name || name === meName) return;
        typingEl.textContent = typingTpl.replace(":name", name);
        typingEl.classList.remove("hidden");
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => typingEl.classList.add("hidden"), 3000);
    };
    const hideTyping = () => {
        clearTimeout(typingTimer);
        typingEl?.classList.add("hidden");
    };

    // Lesebestätigungen: ✓ gesendet → ✓✓ gelesen (sobald andere bis dahin gelesen haben).
    let othersReadTs = 0;
    const applyReadStatus = () => {
        /** @type {NodeListOf<HTMLElement>} */ (
            list.querySelectorAll(".chat-status")
        ).forEach((s) => {
            if (Number(s.dataset.ts || 0) <= othersReadTs) {
                s.textContent = "✓✓";
                s.classList.add("chat-status--read");
            }
        });
    };
    const bumpRead = (ts) => {
        ts = Number(ts || 0);
        if (ts > othersReadTs) othersReadTs = ts;
        applyReadStatus();
    };

    // Datums-Trenner zwischen Tagen (Heute/Gestern/Datum).
    const keyOf = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
    const dayLabel = (ts) => {
        const d = new Date(ts * 1000);
        const k = keyOf(d);
        if (k === keyOf(new Date())) return root.dataset.txtToday || "Heute";
        if (k === keyOf(new Date(Date.now() - 86400000)))
            return root.dataset.txtYesterday || "Gestern";
        return d.toLocaleDateString(undefined, {
            weekday: "short",
            day: "2-digit",
            month: "long",
            year: "numeric",
        });
    };
    const insertDateDividers = () => {
        list.querySelectorAll(".chat-date-divider").forEach((e) => e.remove());
        let lastDay = null;
        /** @type {NodeListOf<HTMLElement>} */ (
            list.querySelectorAll(".chat-msg")
        ).forEach((el) => {
            const ts = Number(el.dataset.ts || 0);
            if (!ts) return;
            const k = keyOf(new Date(ts * 1000));
            if (k !== lastDay) {
                lastDay = k;
                el.insertAdjacentHTML(
                    "beforebegin",
                    `<div class="chat-date-divider my-2 flex justify-center"><span class="rounded-full bg-base-300/80 px-3 py-0.5 text-xs font-medium text-base-content/70 shadow-xs">${dayLabel(ts)}</span></div>`,
                );
            }
        });
    };

    // Aufeinanderfolgende Nachrichten desselben Benutzers (innerhalb 5 min)
    // gruppieren: spätere bekommen 'is-grouped' (Avatar/Name aus, enger).
    const groupMessages = () => {
        let prev = null;
        /** @type {NodeListOf<HTMLElement>} */ (
            list.querySelectorAll(".chat-msg")
        ).forEach((el) => {
            const grouped =
                !!prev &&
                prev.dataset.userId === el.dataset.userId &&
                prev.dataset.mine === el.dataset.mine &&
                Math.abs(Number(el.dataset.ts) - Number(prev.dataset.ts)) <=
                    300;
            el.classList.toggle("is-grouped", grouped);
            prev = el;
        });
    };
    // Nach jeder DOM-Änderung der Liste neu gruppieren (deckt alle Pfade ab).
    new MutationObserver(() => groupMessages()).observe(list, {
        childList: true,
    });

    async function loadInitial() {
        const d = await getJson(`/chat/${channelId}/messages`);
        if (!d) return;
        clearHtml(list);
        d.messages.forEach((m) => append(m.html));
        if (d.messages.length) {
            oldest = d.messages[0].id;
            newest = d.messages.at(-1).id;
        }
        insertDateDividers();
        // "Neue Nachrichten"-Trenner vor der ersten ungelesenen Nachricht.
        let dividerEl = null;
        if (d.first_unread_id) {
            const el = document.getElementById(`chat-msg-${d.first_unread_id}`);
            if (el) {
                const label = root.dataset.txtNew || "Neue Nachrichten";
                el.insertAdjacentHTML(
                    "beforebegin",
                    `<div id="chat-unread-divider" class="my-2 flex items-center gap-2 px-3 text-xs font-semibold text-primary"><span class="h-px flex-1 bg-primary/40"></span>${label}<span class="h-px flex-1 bg-primary/40"></span></div>`,
                );
                dividerEl = document.getElementById("chat-unread-divider");
            }
        }
        if (dividerEl) dividerEl.scrollIntoView({ block: "center" });
        else bottom();
        bumpRead(d.others_read_ts);
        markRead();
    }
    async function loadNew() {
        const d = await getJson(`/chat/${channelId}/messages?after=${newest}`);
        if (!d) return;
        let added = false;
        const wasNear = nearBottom();
        d.messages.forEach((m) => {
            if (!document.getElementById(`chat-msg-${m.sqid}`)) {
                append(m.html);
                newest = Math.max(newest, m.id);
                added = true;
            }
        });
        if (added) {
            insertDateDividers();
            if (wasNear) {
                bottom();
                markRead();
            } else {
                updateScrollBtn();
            }
        }
    }
    async function loadOlder() {
        if (loadingOlder || noMoreOlder || !oldest) return;
        loadingOlder = true;
        const prevH = list.scrollHeight;
        const d = await getJson(`/chat/${channelId}/messages?before=${oldest}`);
        // Nur bei tatsächlich geladenen älteren Nachrichten die Scrollposition
        // anpassen. Sonst (keine älteren mehr) NICHT scrollen – sonst springt
        // die Liste an den Anfang. Und künftige Versuche unterbinden.
        if (d && d.messages.length) {
            oldest = d.messages[0].id;
            [...d.messages].reverse().forEach((m) => prepend(m.html));
            insertDateDividers();
            list.scrollTop = list.scrollHeight - prevH;
        } else {
            noMoreOlder = true;
        }
        loadingOlder = false;
    }
    async function refreshMessage(id) {
        if (!id) return;
        const r = await fetch(`/chat/messages/${id}`, { headers: headers() });
        if (!r.ok) {
            document.getElementById(`chat-msg-${id}`)?.remove();
            return;
        }
        const d = await r.json();
        const el = document.getElementById(`chat-msg-${id}`);
        if (el) el.outerHTML = d.html;
        else {
            append(d.html);
            newest = Math.max(newest, Number(id));
            bottom();
        }
    }
    const removeMessage = (id) =>
        document.getElementById(`chat-msg-${id}`)?.remove();

    // Composer
    const form = /** @type {HTMLFormElement | null} */ (
        document.getElementById("chat-composer")
    );
    const composerBody = /** @type {HTMLTextAreaElement | null} */ (
        form?.querySelector('[name="body"]')
    );
    const fileInput = /** @type {HTMLInputElement | null} */ (
        document.getElementById("chat-file-input")
    );
    const filePreview = document.getElementById("chat-file-preview");

    // Eingabefeld wächst mit dem Inhalt (bis max-h aus den Klassen).
    const autoGrow = () => {
        if (!composerBody) return;
        composerBody.style.height = "auto";
        composerBody.style.height =
            Math.min(composerBody.scrollHeight, 160) + "px";
    };
    composerBody?.addEventListener("input", autoGrow);

    // Format-Helfer
    const wrapSel = (pre, suf) => {
        if (!composerBody) return;
        const s = composerBody.selectionStart,
            en = composerBody.selectionEnd,
            v = composerBody.value;
        const sel = v.slice(s, en);
        composerBody.value = v.slice(0, s) + pre + sel + suf + v.slice(en);
        composerBody.focus();
        const caret = s + pre.length + sel.length;
        composerBody.setSelectionRange(caret, caret);
    };
    const insertText = (t) => {
        if (!composerBody) return;
        const s = composerBody.selectionStart,
            en = composerBody.selectionEnd,
            v = composerBody.value;
        composerBody.value = v.slice(0, s) + t + v.slice(en);
        composerBody.focus();
        composerBody.setSelectionRange(s + t.length, s + t.length);
    };
    /** @type {NodeListOf<HTMLElement>} */ (
        form?.querySelectorAll("[data-fmt]")
    )?.forEach((b) =>
        b.addEventListener("click", () => {
            const f = b.dataset.fmt;
            if (f === "bold") wrapSel("**", "**");
            else if (f === "italic") wrapSel("_", "_");
            else if (f === "code") wrapSel("`", "`");
            else if (f === "codeblock") wrapSel("```\n", "\n```");
        }),
    );

    // Emoji-Panel (in den Text einfügen)
    const emojiInsertBtn = document.getElementById("chat-emoji-insert");
    const emojiPanel = document.getElementById("chat-emoji-panel");
    const toggleEmojiPanel = (show) => {
        if (!emojiPanel) return;
        const open = show ?? emojiPanel.classList.contains("hidden");
        emojiPanel.classList.toggle("hidden", !open);
        emojiPanel.classList.toggle("grid", open);
    };
    emojiInsertBtn?.addEventListener("click", (e) => {
        e.stopPropagation();
        toggleEmojiPanel();
    });
    emojiPanel?.addEventListener("click", (e) => {
        const b = /** @type {HTMLElement | null} */ (
            /** @type {HTMLElement} */ (e.target).closest("[data-insert]")
        );
        if (!b) return;
        insertText(b.dataset.insert);
        toggleEmojiPanel(false);
    });
    document.addEventListener("click", (e) => {
        if (
            emojiPanel &&
            !emojiPanel.classList.contains("hidden") &&
            !emojiPanel.contains(/** @type {Node} */ (e.target)) &&
            e.target !== emojiInsertBtn
        )
            toggleEmojiPanel(false);
    });

    // Datei-Vorschau + Paste/Drag&Drop
    const renderFilePreview = () => {
        if (!fileInput || !filePreview) return;
        const files = [...(fileInput.files || [])];
        filePreview.classList.toggle("hidden", !files.length);
        filePreview.classList.toggle("flex", files.length > 0);
        // Dateinamen werden escaped statt (wie früher) um Sonderzeichen
        // beschnitten — der angezeigte Name bleibt so der tatsächliche.
        setHtml(
            filePreview,
            html`${files.map((f) => {
                const icon = (f.type || "").startsWith("image/")
                    ? "image"
                    : "description";
                return html`<span
                    class="badge badge-ghost max-w-40 gap-1 truncate"
                    title="${f.name}"
                    ><span
                        class="material-symbols-outlined"
                        style="font-size:0.9rem"
                        aria-hidden="true"
                        >${icon}</span
                    >${f.name}</span
                >`;
            })}`,
        );
    };
    const addFiles = (fileList) => {
        if (!fileInput || !fileList?.length) return;
        const dt = new DataTransfer();
        [...(fileInput.files || [])].forEach((f) => dt.items.add(f));
        [...fileList].forEach((f) => dt.items.add(f));
        fileInput.files = dt.files;
        renderFilePreview();
    };
    fileInput?.addEventListener("change", renderFilePreview);
    composerBody?.addEventListener(
        "paste",
        (/** @type {ClipboardEvent} */ e) => {
            const files = [...(e.clipboardData?.items || [])]
                .filter((i) => i.kind === "file")
                .map((i) => i.getAsFile())
                .filter(Boolean);
            if (files.length) {
                e.preventDefault();
                addFiles(files);
            }
        },
    );
    ["dragover", "drop"].forEach((ev) =>
        form?.addEventListener(ev, (e) => e.preventDefault()),
    );
    form?.addEventListener("drop", (e) => addFiles(e.dataTransfer?.files));

    // Zitat-Antwort (Reply-Vorschau über dem Eingabefeld)
    const replyBar = document.getElementById("chat-reply-bar");
    const quotedIdInput = /** @type {HTMLInputElement | null} */ (
        document.getElementById("chat-quoted-id")
    );
    const setReply = (id, name, snippet) => {
        if (!replyBar || !quotedIdInput) return;
        quotedIdInput.value = id || "";
        const nameEl = document.getElementById("chat-reply-name");
        const snEl = document.getElementById("chat-reply-snippet");
        if (nameEl) nameEl.textContent = name || "";
        if (snEl) snEl.textContent = snippet || "";
        replyBar.classList.toggle("hidden", !id);
        replyBar.classList.toggle("flex", !!id);
        if (id) composerBody?.focus();
    };
    const clearReply = () => setReply("", "", "");
    document
        .getElementById("chat-reply-cancel")
        ?.addEventListener("click", clearReply);

    // Weiterleiten
    const forwardDialog = /** @type {HTMLDialogElement | null} */ (
        document.getElementById("chat-forward-dialog")
    );
    const forwardChannel = /** @type {HTMLSelectElement | null} */ (
        document.getElementById("chat-forward-channel")
    );
    let forwardId = null;
    const openForward = (id) => {
        forwardId = id;
        forwardDialog?.showModal();
    };
    document
        .getElementById("chat-forward-send")
        ?.addEventListener("click", async () => {
            const ch = forwardChannel?.value;
            forwardDialog?.close();
            if (forwardId && ch) {
                await send(`/chat/messages/${forwardId}/forward`, "POST", {
                    channel_id: Number(ch),
                });
                window.notifyAction?.({
                    message: root.dataset.txtForwarded || "OK",
                    tone: "success",
                });
            }
        });

    const pad2 = (n) => String(n).padStart(2, "0");
    // Datum (lokal) als YYYY-MM-DD; Zeit als HH:MM; kombiniert zu "YYYY-MM-DDTHH:MM".
    const combineDateTime = (dateVal, timeVal) =>
        dateVal && timeVal ? `${dateVal}T${timeVal}` : "";

    // Erinnerung
    const remindDialog = /** @type {HTMLDialogElement | null} */ (
        document.getElementById("chat-remind-dialog")
    );
    const remindDate = /** @type {HTMLInputElement | null} */ (
        document.getElementById("chat-remind-date")
    );
    const remindTime = /** @type {HTMLInputElement | null} */ (
        document.getElementById("chat-remind-time")
    );
    let remindId = null;
    const fmtLocal = (d) =>
        `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    const openRemind = (id) => {
        remindId = id;
        if (remindDate) remindDate.value = "";
        if (remindTime) remindTime.value = "";
        remindDialog?.showModal();
    };
    const submitRemind = async (whenStr) => {
        remindDialog?.close();
        if (remindId && whenStr) {
            await send(`/chat/messages/${remindId}/remind`, "POST", {
                remind_at: whenStr,
            });
            window.notifyAction?.({
                message: root.dataset.txtReminded || "OK",
                tone: "success",
            });
        }
    };
    /** @type {NodeListOf<HTMLElement>} */ (
        remindDialog?.querySelectorAll("[data-remind]")
    )?.forEach((b) =>
        b.addEventListener("click", () => {
            const v = b.dataset.remind;
            let d;
            if (v === "tomorrow") {
                d = new Date();
                d.setDate(d.getDate() + 1);
                d.setHours(8, 0, 0, 0);
            } else {
                d = new Date(Date.now() + Number(v) * 60000);
            }
            submitRemind(fmtLocal(d));
        }),
    );
    document
        .getElementById("chat-remind-save")
        ?.addEventListener("click", () => {
            const when = combineDateTime(remindDate?.value, remindTime?.value);
            if (when) submitRemind(when);
        });

    // Senden planen
    const scheduleDialog = /** @type {HTMLDialogElement | null} */ (
        document.getElementById("chat-schedule-dialog")
    );
    const scheduleDate = /** @type {HTMLInputElement | null} */ (
        document.getElementById("chat-schedule-date")
    );
    const scheduleTime = /** @type {HTMLInputElement | null} */ (
        document.getElementById("chat-schedule-time")
    );
    document
        .getElementById("chat-schedule-btn")
        ?.addEventListener("click", () => {
            if (scheduleDate) scheduleDate.value = "";
            if (scheduleTime) scheduleTime.value = "";
            scheduleDialog?.showModal();
        });
    document
        .getElementById("chat-schedule-send")
        ?.addEventListener("click", async () => {
            const when = combineDateTime(
                scheduleDate?.value,
                scheduleTime?.value,
            );
            const body = (composerBody?.value || "").trim();
            scheduleDialog?.close();
            if (!when || !body) return;
            const r = await fetch(`/chat/${channelId}/messages`, {
                method: "POST",
                headers: {
                    ...headers(),
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({ body, scheduled_at: when }),
            });
            if (r.ok) {
                if (composerBody) composerBody.value = "";
                window.notifyAction?.({
                    message: root.dataset.txtScheduled || "OK",
                    tone: "success",
                });
            }
        });

    // Zu einer (zitierten) Nachricht springen + kurz hervorheben
    const jumpTo = (id) => {
        const el = document.getElementById(`chat-msg-${id}`);
        if (!el) return;
        el.scrollIntoView({ behavior: "smooth", block: "center" });
        el.classList.add("ring-2", "ring-primary", "rounded-2xl");
        setTimeout(
            () => el.classList.remove("ring-2", "ring-primary", "rounded-2xl"),
            1200,
        );
    };

    form?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const r = await fetch(`/chat/${channelId}/messages`, {
            method: "POST",
            headers: headers(),
            body: fd,
        });
        if (r.ok) {
            const d = await r.json();
            if (!d.parent_id) {
                append(d.html);
                newest = Math.max(newest, d.id);
                insertDateDividers();
                bottom();
            }
            form.reset();
            renderFilePreview();
            clearReply();
            autoGrow();
        }
    });

    // In-App-Dialoge statt Browser-confirm/prompt.
    const editDialog = /** @type {HTMLDialogElement | null} */ (
        document.getElementById("chat-edit-dialog")
    );
    const editInput = /** @type {HTMLInputElement | null} */ (
        document.getElementById("chat-edit-input")
    );
    const emojiDialog = /** @type {HTMLDialogElement | null} */ (
        document.getElementById("chat-emoji-dialog")
    );
    let editId = null;
    let emojiId = null;
    const openEditDialog = (id, body) => {
        editId = id;
        if (editInput) editInput.value = body || "";
        editDialog?.showModal();
        setTimeout(() => editInput?.focus(), 30);
    };
    document
        .getElementById("chat-edit-save")
        ?.addEventListener("click", async () => {
            const b = (editInput?.value || "").trim();
            editDialog?.close();
            if (editId && b !== "") {
                await send(`/chat/messages/${editId}`, "PUT", { body: b });
                refreshMessage(editId);
            }
        });
    const openEmojiPicker = (id) => {
        emojiId = id;
        emojiDialog?.showModal();
    };
    emojiDialog?.addEventListener("click", async (e) => {
        const b = /** @type {HTMLElement | null} */ (
            /** @type {HTMLElement} */ (e.target).closest("[data-emoji]")
        );
        if (!b) return;
        emojiDialog.close();
        if (emojiId) {
            await send(`/chat/messages/${emojiId}/react`, "POST", {
                emoji: b.dataset.emoji,
            });
            refreshMessage(emojiId);
        }
    });

    // Dynamische Umfrage-Optionen (2–20 Felder).
    const pollOptions = document.getElementById("chat-poll-options");
    document.getElementById("chat-poll-add")?.addEventListener("click", () => {
        if (!pollOptions) return;
        const count = pollOptions.querySelectorAll(
            'input[name="options[]"]',
        ).length;
        if (count >= 20) return;
        const ph = pollOptions.dataset.optPlaceholder || "Option";
        const row = document.createElement("div");
        row.className = "flex items-center gap-1";
        setHtml(
            row,
            html`<input
                    type="text"
                    name="options[]"
                    maxlength="200"
                    class="input input-bordered input-sm w-full"
                    placeholder="${ph} ${count + 1}"
                /><button
                    type="button"
                    class="chat-poll-remove btn btn-ghost btn-sm btn-square"
                    tabindex="-1"
                >
                    <span
                        class="material-symbols-outlined"
                        style="font-size:1rem"
                        aria-hidden="true"
                        >close</span
                    >
                </button>`,
        );
        pollOptions.appendChild(row);
        row.querySelector("input")?.focus();
    });
    pollOptions?.addEventListener("click", (e) => {
        const rm = /** @type {HTMLElement} */ (e.target).closest(
            ".chat-poll-remove",
        );
        if (!rm) return;
        if (pollOptions.querySelectorAll('input[name="options[]"]').length <= 2)
            return; // mind. 2 behalten
        rm.closest("div")?.remove();
    });

    // Aktionen per Delegation
    list.addEventListener("click", async (e) => {
        const btn = /** @type {HTMLElement | null} */ (
            /** @type {HTMLElement} */ (e.target).closest("[data-action]")
        );
        if (!btn) return;
        const id =
            btn.dataset.messageId ||
            /** @type {HTMLElement | null} */ (btn.closest(".chat-msg"))
                ?.dataset.messageId;
        const action = btn.dataset.action;
        if (action === "react") {
            await send(`/chat/messages/${id}/react`, "POST", {
                emoji: btn.dataset.emoji,
            });
            refreshMessage(id);
        } else if (action === "react-pick") {
            openEmojiPicker(id);
        } else if (action === "pin") {
            await send(`/chat/messages/${id}/pin`, "POST");
            refreshMessage(id);
        } else if (action === "star") {
            await send(`/chat/messages/${id}/star`, "POST");
            refreshMessage(id);
        } else if (action === "delete") {
            const ok = await (window.confirmAction?.({
                title: root.dataset.txtDelTitle,
                message: root.dataset.txtDelMsg,
                label: root.dataset.txtDelOk,
                tone: "error",
                icon: "delete",
            }) ?? Promise.resolve(false));
            if (ok) {
                await send(`/chat/messages/${id}`, "DELETE");
                removeMessage(id);
            }
        } else if (action === "edit") {
            openEditDialog(id, btn.dataset.body || "");
        } else if (action === "thread") {
            openThread(id);
        } else if (action === "quote") {
            setReply(
                id,
                btn.dataset.quoteName || "",
                btn.dataset.quoteBody || "",
            );
        } else if (action === "forward") {
            openForward(id);
        } else if (action === "remind") {
            openRemind(id);
        } else if (action === "jump") {
            jumpTo(id);
        } else if (action === "vote") {
            await send(`/chat/polls/${btn.dataset.pollId}/vote`, "POST", {
                options: [Number(btn.dataset.optionId)],
            });
            refreshMessage(id);
        }
    });

    list.addEventListener("scroll", () => {
        if (list.scrollTop < 40) loadOlder();
        updateScrollBtn();
    });

    // Thread-Drawer
    async function openThread(id) {
        const drawer = document.getElementById("chat-thread");
        const body = document.getElementById("chat-thread-body");
        const r = await fetch(`/chat/messages/${id}/replies`, {
            headers: headers(),
        });
        if (!r.ok) return;
        const d = await r.json();
        // Nachrichten-HTML kommt serverseitig gerendert (siehe Dateikopf).
        setHtml(
            body,
            html`${trustedServerHtml(d.parent.html)}
                <hr class="my-2 border-base-300" />
                ${d.replies.map((x) => trustedServerHtml(x.html))}`,
        );
        drawer.classList.remove("hidden");
        drawer.classList.add("flex");
        const tf = /** @type {HTMLFormElement | null} */ (
            document.getElementById("chat-thread-form")
        );
        tf.onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(tf);
            fd.append("parent_id", id);
            const rr = await fetch(`/chat/${channelId}/messages`, {
                method: "POST",
                headers: headers(),
                body: fd,
            });
            if (rr.ok) {
                const dd = await rr.json();
                body.insertAdjacentHTML("beforeend", dd.html);
                tf.reset();
                refreshMessage(id);
            }
        };
    }
    document
        .getElementById("chat-thread-close")
        ?.addEventListener("click", () => {
            const d = document.getElementById("chat-thread");
            d.classList.add("hidden");
            d.classList.remove("flex");
        });

    // Echtzeit via Echo (Reverb)
    let realtimeConnected = false;
    try {
        const echo = initEcho();
        socketId = echo.socketId() || "";
        echo.connector?.pusher?.connection?.bind?.("connected", () => {
            realtimeConnected = true;
            socketId = echo.socketId() || socketId;
        });
        echo.connector?.pusher?.connection?.bind?.("unavailable", () => {
            realtimeConnected = false;
        });
        echo.connector?.pusher?.connection?.bind?.("failed", () => {
            realtimeConnected = false;
        });
        const privateChannel = echo.private(`chat.channel.${channelId}`);
        privateChannel
            .listen(".message.sent", () => {
                loadNew();
                hideTyping();
            })
            .listen(".message.updated", (e) => refreshMessage(e.id))
            .listen(".message.deleted", (e) => removeMessage(e.id))
            .listen(".reaction.toggled", (e) => refreshMessage(e.message_id))
            .listen(".poll.voted", (e) => refreshMessage(e.message_id))
            .listen(".channel.read", (e) => bumpRead(e.read_ts))
            .listenForWhisper("typing", (e) => showTyping(e?.name));

        // Präsenz (wer ist online) über Presence-Channel.
        const presenceEl = document.getElementById("chat-presence");
        const presence = new Map();
        const renderPresence = () => {
            if (!presenceEl) return;
            const n = presence.size;
            presenceEl.classList.toggle("hidden", n <= 0);
            presenceEl.classList.toggle("inline-flex", n > 0);
            const countEl = presenceEl.querySelector("[data-count]");
            if (countEl)
                countEl.textContent = (
                    presenceEl.dataset.tpl || ":count online"
                ).replace(":count", /** @type {any} */ (n));
        };
        echo.join(`presence-chat.channel.${channelId}`)
            .here((users) => {
                presence.clear();
                (users || []).forEach((u) => presence.set(u.id, u));
                renderPresence();
            })
            .joining((u) => {
                presence.set(u.id, u);
                renderPresence();
            })
            .leaving((u) => {
                presence.delete(u.id);
                renderPresence();
            })
            .error(() => {});

        // Eigenes Tippen signalisieren (gedrosselt) – nur Client-Whisper.
        const composerInput = document.querySelector(
            '#chat-composer [name="body"]',
        );
        let lastWhisper = 0;
        composerInput?.addEventListener("input", () => {
            const now = Date.now();
            if (now - lastWhisper > 1800) {
                lastWhisper = now;
                try {
                    privateChannel.whisper("typing", { name: meName });
                } catch (e) {
                    /* whisper optional */
                }
            }
        });
    } catch (err) {
        console.warn("Chat-Echtzeit nicht verfügbar (Reverb?):", err);
    }

    // Polling: garantiert Aktualisierung ohne Reload – auch wenn Reverb läuft,
    // aber Broadcasts nicht ankommen (z. B. kein queue:work). Ohne Echtzeit alle
    // 3 s, mit Echtzeit nur als seltener Backstop (~12 s). loadNew() dedupliziert.
    let pollTick = 0;
    setInterval(() => {
        if (document.hidden) return;
        pollTick++;
        const everyN = realtimeConnected ? 4 : 1;
        if (pollTick % everyN === 0) loadNew();
    }, 3000);
    // Beim Zurückkehren in den Tab sofort nachladen (Sidebar via initSidebar).
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) loadNew();
    });

    loadInitial();

    // ── helpers ──
    function headersBase(token) {
        return {
            "X-CSRF-TOKEN": token,
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
        };
    }
    async function getJson(url) {
        const r = await fetch(url, { headers: headers() });
        return r.ok ? r.json() : null;
    }
    function send(url, method, data) {
        return fetch(url, {
            method,
            headers: { ...headers(), "Content-Type": "application/json" },
            body: data ? JSON.stringify(data) : undefined,
        });
    }
}
