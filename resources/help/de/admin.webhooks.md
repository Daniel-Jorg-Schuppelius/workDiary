---
title: "Webhooks"
topic: admin.webhooks
version: 1
audience:
    - admin
related:
    - admin.notification-rules
    - admin.handbook
    - glossary.core
---

Webhooks senden ausgehende Ereignis-Benachrichtigungen an externe
Systeme (z. B. ein ERP, eine Automatisierungsplattform oder ein eigenes
Tool). Sobald ein abonniertes Ereignis eintritt, stellt WorkDiary eine
signierte JSON-Nutzlast per HTTPS-`POST` an deine URL zu.

Typischer Ablauf:

1. **Webhook anlegen**: Bezeichnung und Ziel-URL (HTTPS) eingeben.
2. **Ereignisse** abonnieren (Checkboxen). Nur ausgewählte Ereignisse
   lösen einen Versand aus.
3. Der **Signing-Key** wird einmalig im Klartext angezeigt — jetzt
   kopieren. Danach ist er nur noch verschlüsselt gespeichert und kann
   bei Bedarf rotiert werden.
4. Mit **Test-Event senden** die Erreichbarkeit und Signaturprüfung
   verifizieren.

## Nutzlast

Die Nutzlast ist bewusst minimal und arm an personenbezogenen Daten:

```json
{
  "event": "openIssue.assigned",
  "occurred_at": "2026-06-14T12:00:00+00:00",
  "organization": { "id": 1 },
  "data": {
    "subject_type": "OpenIssue",
    "subject_id": 42,
    "title": "..."
  }
}
```

Reichere bei Bedarf weitere Felder über die REST-API an.

## Signatur prüfen

Jede Zustellung trägt folgende Header:

- `X-WorkDiary-Signature: sha256=<hmac>`
- `X-WorkDiary-Timestamp: <unix-zeit>`
- `X-WorkDiary-Event: <ereignis-schlüssel>`

Der HMAC wird über `<timestamp>.<body>` mit dem Signing-Key gebildet:

```
expected = HMAC_SHA256(timestamp + "." + raw_body, signing_key)
```

Vergleiche `expected` zeitkonstant mit dem Signaturwert und verwirf
Anfragen mit zu altem Zeitstempel (Replay-Schutz).

## Zuverlässigkeit

Fehlgeschlagene Zustellungen werden mit Backoff wiederholt. Nach mehreren
aufeinanderfolgenden Fehlversuchen wird der Endpunkt **automatisch
deaktiviert**; speichere ihn als aktiv, um ihn wieder zu aktivieren. Das
Zustellprotokoll je Endpunkt zeigt Status, HTTP-Code und Zeitpunkt der
letzten Versuche.
