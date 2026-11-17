---
title: "Lexoffice-Konflikte"
topic: admin.lexoffice
version: 10
keywords:
    - Lexware Office
    - Synchronisationskonflikt
    - Sync-Konflikt
    - Datenkonflikt
    - Datenabgleich
    - abweichende Daten
    - Konflikt lösen
    - lokale Werte behalten
    - Werte übernehmen
    - Kontakte abgleichen
    - Belegspiegel
    - Beleg-Sync
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
    - admin.integration-inbox
    - inventory.conflicts
---

Hier lösen Sie Synchronisationskonflikte mit Lexoffice. Ein Konflikt
entsteht, wenn ein lokaler Datensatz (WorkDiary) und der zugehörige
Lexoffice-Kontakt in einem oder mehreren Feldern auseinanderlaufen und
in den Lexoffice-Einstellungen als **Konflikt-Strategie** die
**Manuelle Prüfung** gewählt ist (Standard). Bearbeitet werden die
Konflikte in der **Zuordnungs-Inbox**: Der Aufruf öffnet sie gefiltert
auf die Quelle **Lexoffice** und den Fall **Feld-Konflikt**.

In der Zuordnungs-Inbox:

- Je Konflikt stehen die abweichenden Felder als **Lokal** und
  **Remote** nebeneinander.
- Betroffen sind Kunden und Lieferanten, also die Kontakte aus
  Lexoffice.
- Über den Statusfilter rufen Sie auch erledigte Konflikte wieder auf.

Lösungswege je Konflikt:

- **Remote übernehmen**: aktualisiert den lokalen Datensatz mit den
  Lexoffice-Werten der abweichenden Felder.
- **Lokal behalten**: behält die lokalen Werte; die abweichenden
  Lexoffice-Werte werden nicht übernommen.
- **Verwerfen**: schließt den Konflikt ohne Änderung (z. B. bei bewusst
  unterschiedlichen Daten); er erhält den Status **Verworfen**.

Risiken: **Remote übernehmen** überschreibt lokale Werte. Prüfen Sie die
gegenübergestellten Daten genau, bevor Sie entscheiden. Beachten Sie,
dass bei Rechnungen die Faktura-Hoheit beim externen Programm liegt –
WorkDiary liefert dorthin zu.

Die Konflikt-Strategie gilt für Kontakte. Artikel führt Lexoffice: Der
Artikel-Sync übernimmt jede neuere Lexoffice-Version, Artikelkonflikte
gibt es nicht.

**Anschriften bei „Lexoffice gewinnt“:** Lexoffice führt Anschriften ohne
eigene Kennung, WorkDiary ordnet sie nach Art und Reihenfolge zu. Eine in
WorkDiary angelegte Rechnungs- oder Lieferanschrift übernimmt beim ersten
Abgleich die passende Lexoffice-Anschrift, statt ein zweites Mal angelegt zu
werden: die primäre Rechnungsanschrift die erste aus Lexoffice. Primär ist
danach nur diese; weitere Anschriften in WorkDiary bleiben als weitere stehen.

**Steuerart beim Übertragen:** Rechnungen und Rechnungsentwürfe der
Faktura-Übergabe tragen die Steuerart ihres Steuerfalls: Leistung an
EU-Unternehmen als „Fremdleistungen § 13b EU“, § 13b im Inland als
„Bauleistungen § 13b“, innergemeinschaftliche Lieferung, Ausfuhrlieferung,
nicht steuerbare Leistung als „Dienstleistung an Drittland“, Kleinunternehmer
als „steuerfrei“ — mit 0 % je Position und dem Hinweis als Steuerhinweis.
Diese Steuerarten außer „steuerfrei“ verlangt Lexoffice mit einem dort
angelegten Kontakt. Liegt eine nicht steuerbare Leistung in der EU (etwa ein
Grundstück im EU-Ausland), kennt Lexoffice keine passende Steuerart: Die
Übergabe sperrt mit Grund. Der Beleg aus Zeiten am Kunden ist ein
Buchhaltungsbeleg: je Steuersatz Nettobetrag, Steuer und Buchungskategorie des
Steuerfalls („Dienstleistungen“, „Fremdleistungen § 13b“, „Dienstleistungen
an Drittländer“, „Einnahmen als Kleinunternehmer“), mit Belegnummer
`ZE-<Kundennummer>-<Leistungsende>-<laufende Nummer>`. Für § 13b im Inland,
steuerfreie Umsätze nach § 4, mehrere Steuerfälle oder eine Fremdwährung
sperrt er mit Grund; diese Zeiten gehen über die Faktura-Übergabe.

Belegspiegel: Der Beleg-Sync spiegelt die Lexoffice-Belege nach WorkDiary.
Auch Belege von Lexoffice-Kontakten, die noch keinem Kunden oder Lieferanten
zugeordnet sind, werden gespiegelt – zunächst ohne Kunde und Lieferant;
sobald der Kontakt zugeordnet ist, ordnet der nächste Sync sie zu. Ein Kunde
wird nur an Verkaufsbelegen gesetzt, ein Lieferant nur an Einkaufsbelegen,
auch wenn ein Kontakt beiden zugeordnet ist. Der Positionsabgleich speichert
zusätzlich den Nettobetrag und bei einer Gutschrift ihren Bezug zur
Rechnung. Von Hand stoßen Sie den Lauf in den **Lexoffice-Einstellungen**
(Plugins → Lexoffice) mit **Belege jetzt abgleichen** an: Er holt neue und
geänderte Belege im Hintergrund, der tägliche Vollabgleich bleibt bestehen;
der Knopf braucht das Recht **Belege mit Lexoffice synchronisieren** und hinterlegte Zugangsdaten.

Berechtigung: Die Zuordnungs-Inbox steht Administratoren und der Rolle
**Buchhaltung** offen.

**Datenführerschaft:** Führt laut Datenführerschaft ein anderes Programm Kunden und Lieferanten, übernimmt der Kontakt-Sync keine Felder und legt keine Kontakte an; das Verknüpfen und die Lexoffice-Kontaktnummer bleiben. Der zurückgehaltene Stand wartet im Integrations-Eingang auf **Einmalig übernehmen**.
