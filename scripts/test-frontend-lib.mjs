#!/usr/bin/env node
/**
 * Funktionsprüfung der Escaping-Bibliothek resources/js/lib/html.js.
 *
 * Bewusst ohne Test-Framework: das Projekt hat keinen JS-Runner, und diese
 * eine Datei ist die Sicherheitsgrenze des Frontends — sie darf nicht
 * ungeprüft bleiben, soll aber auch keine Toolchain nach sich ziehen.
 *
 * `window` wird gestubbt, weil safeUrl() die Origin als Basis für relative
 * URLs braucht.
 *
 * Lauf: npm run test:frontend
 */
globalThis.window = { location: { origin: "https://app.example.test" } };

const {
    escHtml, escCssValue, safeUrl, html, setHtml, clearHtml,
    trustedServerHtml, rawHtml, SafeHtml,
} = await import(
    new URL("../resources/js/lib/html.js", import.meta.url).href
);

let pass = 0, fail = 0;
const eq = (label, actual, expected) => {
    const a = String(actual);
    if (a === expected) { pass++; return; }
    fail++;
    console.log(`FAIL ${label}\n  erwartet: ${JSON.stringify(expected)}\n  bekommen: ${JSON.stringify(a)}`);
};
const ok = (label, cond) => { if (cond) pass++; else { fail++; console.log(`FAIL ${label}`); } };

// --- escHtml: alle fünf Zeichen (die alten Escaper ließen ' bzw. & durch) ---
eq("escHtml alle 5", escHtml(`<>&"'`), "&lt;&gt;&amp;&quot;&#039;");
eq("escHtml XSS-Payload", escHtml('<img src=x onerror=alert(1)>'),
   "&lt;img src=x onerror=alert(1)&gt;");
eq("escHtml null", escHtml(null), "");
eq("escHtml undefined", escHtml(undefined), "");
eq("escHtml Zahl", escHtml(42), "42");

// --- Attribut-Ausbruch, einfach und doppelt gequotet ---
eq("escHtml Attr-Ausbruch \"", escHtml('" onmouseover="alert(1)'),
   "&quot; onmouseover=&quot;alert(1)");
eq("escHtml Attr-Ausbruch '", escHtml("' onmouseover='alert(1)"),
   "&#039; onmouseover=&#039;alert(1)");

// --- safeUrl: Protokoll-Allowlist ---
eq("safeUrl javascript:", safeUrl("javascript:alert(1)"), "#");
eq("safeUrl JaVaScRiPt: (Case)", safeUrl("JaVaScRiPt:alert(1)"), "#");
eq("safeUrl data:", safeUrl("data:text/html,<script>alert(1)</script>"), "#");
eq("safeUrl vbscript:", safeUrl("vbscript:msgbox"), "#");
eq("safeUrl relativ", safeUrl("/kunden/42"), "/kunden/42");
eq("safeUrl Anker", safeUrl("#abschnitt"), "#abschnitt");
eq("safeUrl leer", safeUrl(""), "#");
ok("safeUrl https", safeUrl("https://example.test/x").toString().startsWith("https://example.test/x"));
ok("safeUrl mailto", safeUrl("mailto:a@b.test").toString().startsWith("mailto:"));

// --- escCssValue: Allowlist ---
eq("escCssValue Hex", escCssValue("#ff0000"), "#ff0000");
eq("escCssValue rgb", escCssValue("rgb(255, 0, 0)"), "rgb(255, 0, 0)");
eq("escCssValue Name", escCssValue("red"), "red");
eq("escCssValue Injektion", escCssValue("red;background-image:url(//evil.test/x)"), "");
eq("escCssValue Ausbruch", escCssValue('red"></span><script>alert(1)</script>'), "");
eq("escCssValue leer", escCssValue(null), "");

// --- html-Tag: automatisches Escaping + Schachtelung ---
eq("html escaped", html`<p>${"<b>x</b>"}</p>`, "<p>&lt;b&gt;x&lt;/b&gt;</p>");
eq("html Schachtelung nicht doppelt",
   html`<div>${html`<span>${"a&b"}</span>`}</div>`,
   "<div><span>a&amp;b</span></div>");
eq("html Array verbunden", html`${["a", "b", "c"]}`, "abc");
eq("html Array escaped", html`${["<a>", "<b>"]}`, "&lt;a&gt;&lt;b&gt;");
eq("html null/undefined leer", html`x${null}${undefined}y`, "xy");
eq("html false leer (für && )", html`x${false}y`, "xy");
eq("html 0 bleibt", html`${0}`, "0");
eq("html verschachtelte Arrays", html`${[html`<i>${"&"}</i>`, "&"]}`, "<i>&amp;</i>&amp;");

// --- Typen ---
ok("escHtml liefert SafeHtml", escHtml("x") instanceof SafeHtml);
ok("html liefert SafeHtml", html`x` instanceof SafeHtml);
ok("safeUrl liefert SafeHtml", safeUrl("/x") instanceof SafeHtml);
ok("trustedServerHtml unverändert", trustedServerHtml("<b>x</b>").toString() === "<b>x</b>");
ok("rawHtml unverändert", rawHtml("<b>x</b>").toString() === "<b>x</b>");

// --- setHtml: Laufzeit-Absicherung ---
const el = { innerHTML: null, textContent: null };
setHtml(el, html`<b>ok</b>`);
eq("setHtml setzt Markup", el.innerHTML, "<b>ok</b>");

let threw = false;
try { setHtml(el, "<b>roh</b>"); } catch (e) { threw = e instanceof TypeError; }
ok("setHtml wirft bei rohem String", threw);

threw = false;
try { setHtml(el, 42); } catch (e) { threw = e instanceof TypeError; }
ok("setHtml wirft bei Zahl", threw);

// null-Element darf nicht werfen (viele Aufrufstellen prüfen nicht vor)
let noThrow = true;
try { setHtml(null, html`x`); } catch { noThrow = false; }
ok("setHtml toleriert null-Element", noThrow);

clearHtml(el);
eq("clearHtml leert", el.textContent, "");

console.log(`\n${pass} bestanden, ${fail} fehlgeschlagen`);
process.exit(fail === 0 ? 0 : 1);
