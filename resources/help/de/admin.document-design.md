---
title: "Dokumentdesign"
topic: admin.document-design
version: 1
audience:
    - admin
modules:
    - module.dokumentdesign
related:
    - admin.branding
    - invoices.manage
---

Im Dokumentdesign passen Sie erzeugte PDF-Dokumente an das
Erscheinungsbild Ihrer Organisation an: Firmenbogen hinterlegen,
Druck- und Sperrbereiche festlegen, Informationsblöcke deklarieren und
ein kuratiertes Tabellenstil-Preset wählen.

Ablauf:

1. **Firmenbogen hochladen** (PDF, JPG oder PNG, A4 Hochformat) — je ein
   Asset für die erste Seite und optional für Folgeseiten. PDFs werden zu
   einer sicheren, nicht interaktiven Rasterseite reduziert; das Original
   bleibt als Nachweis gespeichert.
2. **Profil anlegen** und im Editor Druckbereiche, Empfängerfenster,
   Absenderzeile und Sperrflächen in Millimetern festlegen — visuell oder
   numerisch, auch per Tastatur.
3. **Informationsblöcke deklarieren**: `dynamisch` (WorkDiary druckt),
   `bereits auf dem Firmenbogen` (mit Bestätigung je Profilversion) oder
   `nicht anwendbar`. Pflichtblöcke der zugeordneten Dokumentarten sowie
   veränderliche Belegdaten sind geschützt.
4. **Testdokument** je Dokumentart mit langen Texten, vielen Positionen
   und mehreren Steuersätzen erzeugen; der Preflight zeigt Überlagerungen,
   fehlende Pflichtblöcke und Kontrastprobleme.
5. **Version aktivieren** — nur mit fehlerfreiem Preflight. Aktivierte
   Versionen sind unveränderlich; Änderungen laufen über einen neuen
   Entwurf. Finalisierte Dokumente behalten ihren eingefrorenen Stand.

Ohne Profil gilt der Systemstandard (heutige Ausgabe). ZUGFeRD-/
PDF-A-3-Rechnungen bleiben nach Anwendung des Designs valide — die
strukturierte Rechnung bleibt fachlich führend.

CI-Basisdesign und Vererbung:

- Das org-weite Standardprofil ist Ihr **CI-Basisdesign**. Varianten für
  einzelne Dokumentarten (z. B. Angebot, Rechnung, Gutschrift, Mahnung)
  oder ganze Dokumentfamilien (Vertrieb, Einkauf, Nachweis) **erben** alle
  nicht überschriebenen Sektionen — je Sektion ist sichtbar, ob sie
  geerbt oder überschrieben ist; „Auf Basisdesign zurücksetzen" entfernt
  das Override. Die spezifischere Variante gewinnt: Dokumentart vor
  Familie vor Basisdesign.
- Die **eingebettete PDF-Vorschau** im Editor rendert über dieselbe
  Pipeline wie die finale Ausgabe; Dokumentart und Beispieldaten (lange
  Texte, viele Positionen, mehrere Steuersätze) sind umschaltbar.
- **Schriftfamilie und Grundgröße** stammen aus einer kuratierten,
  PDF-fähigen Liste; Primär-/Akzentfarbe können das
  **Organisationsbranding referenzieren** — Branding-Änderungen wirken
  dann automatisch, ohne Farbkopie im Profil.
- Das Basisdesign wird beim Aktivieren gegen die Pflichtblöcke ALLER
  brandfähigen Dokumentarten geprüft; echte Spezialformate (z. B.
  Etiketten) deklarieren ihre Einschränkung in der zentralen
  Dokumentarten-Registrierung.
- **Kopf-/Fußtexte** der Vertriebsbelege (vormals Rechnungsvorlagen) sind
  eine eigene, vererbbare Profil-Sektion — versioniert und bei
  finalisierten Belegen eingefroren. **Kunden-Sonderdesigns** sind
  reguläre Profile, die Sie in der Kundenakte (Panel „Dokumentdesign")
  zuweist; als „Kunden-Sonderprofil" markierte Profile wirken
  ausschließlich über diese Zuweisung.
- **Feinschliff:** Vererbung gilt je Einstellungsgruppe (Ränder,
  Adressfenster, Sperrflächen, Kopf-/Fußzeilen, Typografie, Firmenbogen,
  Blöcke, Tabellenstil, Texte). Preflight-**Warnungen** blockieren die
  Aktivierung, bis Sie sie im Dialog bewusst bestätigen. Neu sind außerdem
  per-Seite-**Kopf-/Fußzeilen** und die vollständigen Tabellenstil-Schalter
  (Raster, Abstände, Farben, Kopfzeilen-Wiederholung, Summenbetonung).

Einstieg und Firmenbogen-Ansicht (Nachschnitt 6):

- Solange nicht alle fünf Schritte erledigt sind, zeigt die Übersicht eine
  **Checkliste** (Firmenbogen hochladen → Profil anlegen → im Editor
  gestalten → Version aktivieren → Dokumentarten zuweisen) mit Sprung in
  den passenden Dialog bzw. Editor-Reiter. Aus dem Belegfluss und dem
  Branding führt jeweils ein Verweis hierher.
- Jeder Firmenbogen hat ein **Thumbnail** und einen **Ansehen**-Dialog —
  auch bei „Prüfung erforderlich": dann werden Original (Bild) und
  Prüfhinweise gezeigt; das **Original** lässt sich immer herunterladen
  (PDF nur als Download, nicht im Browser-Viewer).
- Der Editor gliedert die rechte Spalte in die Reiter **Aussehen**
  (Firmenbogen, Tabellenstil), **Layout** (Druckbereiche, Fenster,
  Sperrflächen, Typografie), **Inhalte** (Informationsblöcke, Kopf-/
  Fußtexte) und **Freigabe** (Testdokumente, Zuweisung, Versionen). Die
  Firmenbogen-Auswahl erscheint sofort in der A4-Vorschau und wird mit
  „Entwurf speichern" übernommen.
