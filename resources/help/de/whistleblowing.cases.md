---
title: "Meldestelle – Fallbearbeitung"
topic: whistleblowing.cases
version: 4
keywords:
    - Hinweisgebersystem
    - HinSchG
    - Whistleblower
    - Hinweisgeberschutz
    - interne Meldestelle
    - Hinweis bearbeiten
    - Eingang bestätigen
    - Compliance-Fall
    - Interessenkonflikt
    - Notfallfreigabe
    - Rückfrage an Hinweisgeber
    - Fall löschen
audience: []
modules:
    - module.compliance
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Hier bearbeiten Sie eingegangene Hinweise interner und externer
Melder. Sie finden die Liste **Hinweisgeber-Meldungen** im Menü
**Compliance** → **Meldestelle**. Die Berechtigung der Meldestelle (Rolle
**Meldestelle**) ist bewusst von der Administration **getrennt**: Auch
Administratoren haben ohne eigene Zuweisung zum Fall keinen Einblick.
Jeder einzelne Zugriff setzt das passende Recht **und** die Zuweisung zum
konkreten Fall voraus; eine Ausnahme für Administratoren gibt es nicht.

Vor dem Zugriff ist eine eigene Zwei-Faktor-Authentifizierung
erforderlich; ohne sie leitet WorkDiary Sie zur Einrichtung weiter.

**Fallliste**: Die Übersicht zeigt nur Stammdaten (**Fallnummer**,
**Kategorie**, **Status**, **Priorität**, **Eingang bis**, **Rückmeldung
bis**) – bewusst **keine Inhaltsvorschau**. Kategorie und Priorität
erscheinen erst, wenn Sie dem Fall zugewiesen sind („Sichtbar nach
Zuweisung“). Die Inhalte jedes Falls sind mit einem eigenen Schlüssel
verschlüsselt.

**Falldetail**: Die Fallakte zeigt **Fallinformationen**,
**Meldeinhalt**, **Bearbeiter** sowie **Kommunikation & Notizen**. Je nach
Berechtigung können Sie

- den **Eingang bestätigen** (Frist **Eingang bis**: 7 Tage nach
  Eingang),
- unter **Status ändern** den nächsten zulässigen Status wählen und mit
  **Status setzen** übernehmen – etwa „Eingegangen“ → „Eingang
  bestätigt“ → „Prüfung“ → „In Bearbeitung“ (zwischendurch „Wartet auf
  Rückmeldung“ oder „Abgegeben“) → „Abgeschlossen – …“; ein Abschluss
  verlangt eine **Begründung**, die als interne Notiz abgelegt wird,
- unter **Bearbeiter zuweisen** eine Person über ihre **Benutzer-ID** mit
  einer **Rolle** hinzufügen (**Zuweisen**),
- eine **Interne Notiz** erfassen (**Notiz speichern**; nie für die
  meldende Person sichtbar),
- eine **Nachricht an die meldende Person** senden (**Senden**); sie
  erscheint in deren geschütztem Postfach.

Anhänge, die die meldende Person hochlädt, werden verschlüsselt
gespeichert und vor der Freigabe auf Schadsoftware geprüft. Die Karte
**Anhänge** nennt je Datei Name und Größe; freigegebene Anhänge laden Sie über
das Symbol **Herunterladen**. Sonst steht dort der Prüfstand: **In Prüfung**,
solange das Ergebnis aussteht, **Abgewiesen** oder **Prüfung fehlgeschlagen** –
diese Dateien gibt WorkDiary nicht heraus. Ohne eingerichteten Virenscanner
bleiben Anhänge in Prüfung.

**Vertraulichkeit und Konflikte**:

- **Interessenkonflikt melden** (Begründung optional) sperrt Sie selbst
  für den Fall: Ihre Zuweisung endet sofort, und Sie können die Sperre
  nicht selbst aufheben.
- **Betroffene Person markieren** sperrt eine Person über ihre
  **Benutzer-ID** für den Fall (**Markieren**, Notiz optional); bestehende
  Zuweisungen enden sofort.
- **Notfallfreigabe** gibt einer nicht zugewiesenen Person befristet Zugriff,
  standardmäßig vier Stunden (Benutzer-ID und Pflichtbegründung,
  **Freigeben**). Freigeben darf nur eine andere Person.
- **Fall exportieren** erzeugt ein mit AES-256 verschlüsseltes Paket
  (Begründung und Passwort mit mindestens zwölf Zeichen, **Exportieren**);
  geben Sie das Passwort getrennt vom Paket weiter.
- Jeder Schritt am Fall wird manipulationssicher im Fallprotokoll
  festgehalten.

**Löschen**: Steht ein Fall im Status „Aufbewahrungsprüfung“, erscheint
die Karte **Kontrollierte Löschung**. **Fall löschen** und die Bestätigung
**Endgültig löschen** vernichten den Schlüssel des Falls: Meldeinhalt,
Nachrichten, Anhänge und Zuweisungen sind danach unwiederbringlich
verloren, es bleibt nur ein inhaltsfreier Löschnachweis. Das ist
unumkehrbar. Steht ein Verfahren oder eine Aufbewahrungspflicht
entgegen, setzen Sie stattdessen den Status „Löschsperre (Legal Hold)“.
