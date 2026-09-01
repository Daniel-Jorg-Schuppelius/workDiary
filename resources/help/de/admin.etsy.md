---
title: "Etsy anbinden"
topic: admin.etsy
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary bindet den **Etsy-Shop** der Organisation direkt an (Open API v3):
Bestellungen erscheinen als **Bestellspiegel**, Versandmeldungen mit
Tracking fließen zurück, Gebühren und Auszahlungen aus dem Etsy-Ledger
stehen der Auswertung zur Verfügung.

**Eigene Seller-App:** Jede Organisation registriert unter
etsy.com/developers eine eigene Seller-App (Freigabe in Minuten) und
hinterlegt **Keystring** und **Shared Secret** in der Plugin-Karte. Als
Redirect-URI der App muss exakt die im Panel angezeigte Callback-URL
eingetragen sein (HTTPS, ohne Abweichung). Danach „Mit Etsy verbinden" —
der Shop wird automatisch ermittelt; ein Shop kann nur an **eine**
Organisation gebunden sein.

**Inbox-First:** Käufer werden nie blind als Kunden angelegt. Eindeutige
Treffer oder bereits zugeordnete Wiederkäufer werden verlinkt; alles andere
erscheint als Vorschlag in der Integrations-Inbox. Gastkäufe ohne
Etsy-Käuferkonto bleiben ohne Vorschlag im Spiegel.

**Webhooks (optional):** Im Etsy-Webhook-Portal die im Panel angezeigte
URL mit den vier order.*-Events eintragen und das `whsec_…`-Secret in der
Plugin-Karte hinterlegen — neue Bestellungen erscheinen dann sofort. Ohne
Webhook läuft alles über den regelmäßigen Abgleich (verlässliche Quelle
bleibt immer der Abgleich).

**Versand melden:** Über die Aktion am Spiegel werden Tracking-Nummer und
Carrier an Etsy übertragen (Etsy benachrichtigt den Käufer). Unbekannte
Carrier übermittelt Etsy unter „other". Jede Bestellung wird höchstens
einmal gemeldet.

**Frist beachten:** Etsys Refresh-Token läuft 90 Tage nach der letzten
Nutzung ab; der Gesundheitscheck warnt rechtzeitig, danach hilft nur
Neuverbinden. Etsy stellt keine Test-Umgebung bereit — Tests laufen gegen
den Live-Shop nach Etsys API-Testing-Policy (Gebühren fallen real an).

*The term "Etsy" is a trademark of Etsy, Inc. This application uses the
Etsy API but is not endorsed or certified by Etsy, Inc.*
