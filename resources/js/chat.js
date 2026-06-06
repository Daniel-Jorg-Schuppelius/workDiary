// Chat-Client: lädt/sendet Nachrichten, Echtzeit via Reverb/Echo, Aktionen über
// Event-Delegation (Nachrichten-HTML wird serverseitig gerendert eingefügt).
import { initEcho } from "./echo.js";

const root = document.getElementById("chat-root");
if (root) {
    initSearch();
    if (root.dataset.channelId) initChat(root);
}

// Volltextsuche in der Sidebar (funktioniert auch ohne aktiven Kanal).
function initSearch() {
    const input = document.getElementById("chat-search");
    const results = document.getElementById("chat-search-results");
    if (!input || !results) return;
    const esc = (s) => { const d = document.createElement("div"); d.textContent = String(s ?? ""); return d.innerHTML; };
    const hide = () => { results.classList.add("hidden"); };
    let timer;
    input.addEventListener("input", () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { hide(); results.innerHTML = ""; return; }
        timer = setTimeout(async () => {
            const r = await fetch(`/chat/search?q=${encodeURIComponent(q)}`, { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } });
            if (!r.ok) return;
            const d = await r.json();
            results.classList.remove("hidden");
            results.innerHTML = d.results.length
                ? d.results.map((x) => `<a href="/chat/${x.channel_id}#chat-msg-${x.message_id}" class="block border-b border-base-200 px-3 py-2 text-sm last:border-b-0 hover:bg-base-200"><div class="truncate font-medium">${esc(x.channel)} · <span class="text-base-content/60">${esc(x.user || "")}</span></div><div class="truncate text-base-content/70">${esc(x.snippet)}</div></a>`).join("")
                : `<p class="px-3 py-4 text-sm text-base-content/50">${esc(input.dataset.empty || "Keine Treffer.")}</p>`;
        }, 300);
    });
    // Dropdown schließen bei Klick außerhalb.
    document.addEventListener("click", (e) => {
        if (!results.contains(e.target) && e.target !== input) hide();
    });
}

function initChat(root) {
    const channelId = root.dataset.channelId;
    if (!channelId) return;
    const list = document.getElementById("chat-messages");
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
    const base = headersBase(csrf);
    let oldest = null;
    let newest = 0;
    let loadingOlder = false;
    let socketId = "";

    function headers() {
        return socketId ? { ...base, "X-Socket-ID": socketId } : { ...base };
    }
    const append = (html) => list.insertAdjacentHTML("beforeend", html);
    const prepend = (html) => list.insertAdjacentHTML("afterbegin", html);
    const bottom = () => { list.scrollTop = list.scrollHeight; };
    const markRead = () => fetch(`/chat/${channelId}/read`, { method: "POST", headers: headers() }).catch(() => {});

    // Aufeinanderfolgende Nachrichten desselben Benutzers (innerhalb 5 min)
    // gruppieren: spätere bekommen 'is-grouped' (Avatar/Name aus, enger).
    const groupMessages = () => {
        let prev = null;
        list.querySelectorAll(".chat-msg").forEach((el) => {
            const grouped = !!prev
                && prev.dataset.userId === el.dataset.userId
                && prev.dataset.mine === el.dataset.mine
                && Math.abs(Number(el.dataset.ts) - Number(prev.dataset.ts)) <= 300;
            el.classList.toggle("is-grouped", grouped);
            prev = el;
        });
    };
    // Nach jeder DOM-Änderung der Liste neu gruppieren (deckt alle Pfade ab).
    new MutationObserver(() => groupMessages()).observe(list, { childList: true });

    async function loadInitial() {
        const d = await getJson(`/chat/${channelId}/messages`);
        if (!d) return;
        list.innerHTML = "";
        d.messages.forEach((m) => append(m.html));
        if (d.messages.length) { oldest = d.messages[0].id; newest = d.messages.at(-1).id; }
        bottom();
        markRead();
    }
    async function loadNew() {
        const d = await getJson(`/chat/${channelId}/messages?after=${newest}`);
        if (!d) return;
        let added = false;
        d.messages.forEach((m) => {
            if (!document.getElementById(`chat-msg-${m.id}`)) { append(m.html); newest = Math.max(newest, m.id); added = true; }
        });
        if (added) { bottom(); markRead(); }
    }
    async function loadOlder() {
        if (loadingOlder || !oldest) return;
        loadingOlder = true;
        const prevH = list.scrollHeight;
        const d = await getJson(`/chat/${channelId}/messages?before=${oldest}`);
        if (d) {
            [...d.messages].reverse().forEach((m) => prepend(m.html));
            if (d.messages.length) oldest = d.messages[0].id;
            list.scrollTop = list.scrollHeight - prevH;
        }
        loadingOlder = false;
    }
    async function refreshMessage(id) {
        if (!id) return;
        const r = await fetch(`/chat/messages/${id}`, { headers: headers() });
        if (!r.ok) { document.getElementById(`chat-msg-${id}`)?.remove(); return; }
        const d = await r.json();
        const el = document.getElementById(`chat-msg-${id}`);
        if (el) el.outerHTML = d.html;
        else { append(d.html); newest = Math.max(newest, Number(id)); bottom(); }
    }
    const removeMessage = (id) => document.getElementById(`chat-msg-${id}`)?.remove();

    // Composer
    const form = document.getElementById("chat-composer");
    form?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const r = await fetch(`/chat/${channelId}/messages`, { method: "POST", headers: headers(), body: fd });
        if (r.ok) {
            const d = await r.json();
            if (!d.parent_id) { append(d.html); newest = Math.max(newest, d.id); bottom(); }
            form.reset();
        }
    });

    // Aktionen per Delegation
    list.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-action]");
        if (!btn) return;
        const id = btn.dataset.messageId || btn.closest(".chat-msg")?.dataset.messageId;
        const action = btn.dataset.action;
        if (action === "react") { await send(`/chat/messages/${id}/react`, "POST", { emoji: btn.dataset.emoji }); refreshMessage(id); }
        else if (action === "react-pick") { const emo = prompt(root.dataset.txtEmoji || "Emoji:", "❤️"); if (emo) { await send(`/chat/messages/${id}/react`, "POST", { emoji: emo }); refreshMessage(id); } }
        else if (action === "pin") { await send(`/chat/messages/${id}/pin`, "POST"); refreshMessage(id); }
        else if (action === "delete") { if (confirm(root.dataset.txtDelete || "Löschen?")) { await send(`/chat/messages/${id}`, "DELETE"); removeMessage(id); } }
        else if (action === "edit") { const b = prompt(root.dataset.txtEdit || "Bearbeiten:", btn.dataset.body || ""); if (b !== null && b.trim() !== "") { await send(`/chat/messages/${id}`, "PUT", { body: b }); refreshMessage(id); } }
        else if (action === "thread") { openThread(id); }
        else if (action === "vote") { await send(`/chat/polls/${btn.dataset.pollId}/vote`, "POST", { options: [Number(btn.dataset.optionId)] }); refreshMessage(id); }
    });

    list.addEventListener("scroll", () => { if (list.scrollTop < 40) loadOlder(); });

    // Thread-Drawer
    async function openThread(id) {
        const drawer = document.getElementById("chat-thread");
        const body = document.getElementById("chat-thread-body");
        const r = await fetch(`/chat/messages/${id}/replies`, { headers: headers() });
        if (!r.ok) return;
        const d = await r.json();
        body.innerHTML = d.parent.html + '<hr class="my-2 border-base-300">' + d.replies.map((x) => x.html).join("");
        drawer.classList.remove("hidden");
        drawer.classList.add("flex");
        const tf = document.getElementById("chat-thread-form");
        tf.onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(tf);
            fd.append("parent_id", id);
            const rr = await fetch(`/chat/${channelId}/messages`, { method: "POST", headers: headers(), body: fd });
            if (rr.ok) { const dd = await rr.json(); body.insertAdjacentHTML("beforeend", dd.html); tf.reset(); refreshMessage(id); }
        };
    }
    document.getElementById("chat-thread-close")?.addEventListener("click", () => {
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
        echo.connector?.pusher?.connection?.bind?.("unavailable", () => { realtimeConnected = false; });
        echo.connector?.pusher?.connection?.bind?.("failed", () => { realtimeConnected = false; });
        echo.private(`chat.channel.${channelId}`)
            .listen(".message.sent", () => loadNew())
            .listen(".message.updated", (e) => refreshMessage(e.id))
            .listen(".message.deleted", (e) => removeMessage(e.id))
            .listen(".reaction.toggled", (e) => refreshMessage(e.message_id))
            .listen(".poll.voted", (e) => refreshMessage(e.message_id));
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
    // Beim Zurückkehren in den Tab sofort nachladen.
    document.addEventListener("visibilitychange", () => { if (!document.hidden) loadNew(); });

    loadInitial();

    // ── helpers ──
    function headersBase(token) {
        return { "X-CSRF-TOKEN": token, Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
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
