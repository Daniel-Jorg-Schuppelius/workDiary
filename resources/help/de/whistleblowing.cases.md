---
title: "Meldestelle – Fallbearbeitung"
topic: whistleblowing.cases
version: 1
audience: []
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Hier bearbeitest du eingegangene Hinweise interner und externer
Melder (`/compliance/meldungen`). Die Berechtigung der Meldestelle
ist bewusst von der Administration **getrennt**: Ein globaler Admin
hat ohne eigene Fall-Zuweisung keinen Einblick. Jeder einzelne
Zugriff wird über die Fall-Policy geprüft (Permission **und**
konkrete Zuweisung zum Fall); es gibt keinen Admin-Bypass.

Vor dem Zugriff ist eine eigene Zwei-Faktor-Authentifizierung der
Meldestelle erforderlich.

**Fallliste**: Die Übersicht zeigt nur Stammdaten (Fallnummer,
Kategorie, Status, Priorität, Fristen) – bewusst **keine
Inhaltsvorschau**. Inhalte sind pro Fall mit einem eigenen Schlüssel
verschlüsselt (DEK).

**Falldetail**: Im Detail kannst du je nach Berechtigung

- den **Eingang bestätigen** (7-Tage-Frist),
- den **Status** entlang des Lebenszyklus ändern (Eingegangen →
  Bestätigt → Triage → In Bearbeitung → … → Abgeschlossen);
  Abschluss verlangt eine Begründung,
- **Bearbeiter zuweisen** (mit Rolle),
- **interne Notizen** erfassen (nie für den Melder sichtbar),
- **Nachrichten an die meldende Person** senden (über das anonyme
  Postfach),
- verschlüsselte **Anhänge** herunterladen.

**Vertraulichkeit und Konflikte**:

- **Interessenkonflikt erklären** sperrt dich selbst für den Fall.
- Eine **betroffene Person markieren** sperrt diese dauerhaft für den
  Fall.
- Eine **Notfallfreigabe** (mit Pflicht-Begründung) erteilt einer
  weiteren Person Zugriff – jeder dieser Schritte wird in der eigenen
  Ereignis-Hash-Kette protokolliert.

**Löschen**: Das kontrollierte Löschen am Ende der Aufbewahrung
erfolgt per **Crypto-Shredding** (der Fallschlüssel wird vernichtet,
die Inhalte werden damit unlesbar). Das ist unumkehrbar.
