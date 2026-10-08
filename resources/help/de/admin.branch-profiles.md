---
title: "Branchenprofile"
topic: admin.branch-profiles
version: 3
keywords:
    - Branchenvorlage
    - Branchenpaket
    - Gewerk
    - Vorlagenpaket
    - Elektro
    - SHK
    - Gebäudereinigung
    - Auftragsarten
    - Checklistenvorlagen
    - Ersteinrichtung
    - Standardvorlagen installieren
    - Profilvariante
audience:
    - admin
related:
    - admin.handbook
    - admin.import
---

Branchenprofile bringen ein kuratiertes Vorlagenpaket je Gewerk in
einem Schritt in den Mandanten – statt jede Auftragsart, Kategorie und
Checkliste von Hand anzulegen.

Ein Paket kann enthalten:

- **Auftragsarten** und **Kategorien** (Klassifikationen je Domäne wie
  Tätigkeit, Fehlerbild, Ursache, Ergebnis, Produktgruppe).
- **Pflichtregeln**, die je Auftragsart bestimmte Kategorien beim Anlegen
  oder vor dem Abschluss erzwingen.
- **Checklisten / Prozedurvorlagen** (z. B. Sicherheitscheck Elektro,
  Druckprüfung SHK, Qualitätskontrolle Reinigung) – veröffentlicht und
  sofort einsetzbar.
- **Raumanforderungen** als organisationsweite Vorlagen (z. B. Hygienestufe,
  technische Prüfung, Zutrittsbeschränkung), die beim Pflegen eines Raums
  übernommen werden können.
- **Standard-Tags** sowie – je nach Gewerk – Wartungspläne, SLA-Vorlagen,
  Reinigungsprofile und Softwarekatalog.

So gehen Sie vor:

1. Im Katalog das passende Gewerk suchen (Suche/Filter nach
   Installationsstatus).
2. Die **Inhaltsvorschau** auf der Karte zeigt, was das Paket mitbringt:
   Anzahl Auftragsarten, Kategorien, Pflichtregeln, Checklisten,
   Raumanforderungen und Tags sowie eine Liste der enthaltenen
   Auftragsarten und Checklisten.
3. **Installieren** wählen und bestätigen.

Wichtig zu wissen:

- Die Installation ist **idempotent**: Ein erneutes Installieren erzeugt
  keine Dubletten und überschreibt **keine lokal angepassten Daten**.
- **Erneut anwenden** setzt importierte Vorlagen (Klassifikationen,
  Pflichtregeln, Raumanforderungen) wieder auf den Profilstand zurück.
  Bereits **veröffentlichte Checklisten** bleiben dabei unverändert
  erhalten – Checklisten werden nie automatisch überschrieben.
- Jede Installation wird revisionssicher protokolliert.
- Profile sind als Konfiguration hinterlegt; neue Gewerke lassen sich ohne
  Code-Änderung ergänzen.

Mehrere Profile, Hauptprofil und Deinstallation:

- Profile lassen sich **kombinieren** (z. B. Elektro und SHK). Das zuerst
  installierte ist das **Hauptprofil**; Nav-Fokus und Fach-Vorgaben folgen
  ihm, die Modul-Empfehlung im Funktionsumfang vereint alle installierten
  Profile. **Als Hauptprofil festlegen** wechselt es bewusst.
- **Deinstallieren** (Plattform-Admin) entfernt unbenutzte Auftragsarten,
  Kategorien und Tags des Profils, deaktiviert benutzte Klassifikationen und
  löscht die Pflichtregeln des Profils. Checklisten, Wartungspläne, SLA-,
  Reinigungs- und Raumvorlagen bleiben als Stammdaten erhalten.
- Ein **Update-Hinweis** auf der Karte zeigt, dass eine neuere Profilversion
  vorliegt; „Erneut anwenden" übernimmt sie.
- **Import** nimmt ein JSON-Profil aus dem Katalog entgegen; unbekannte
  Klassifikations-Domänen oder Module werden abgelehnt.
- Auftragsarten sind in den aktivierbaren Sprachen hinterlegt; die Anzeige
  folgt der Sprache des Nutzers, das Quell-Label bleibt bearbeitbar.

Kundenspezifische Varianten:

- Eine **Variante** überlagert ein Branchenprofil: Sie übernimmt das
  Basisprofil, lässt Bausteine weg und ergänzt eigene – das Profil selbst
  bleibt unverändert.
- **Variante anlegen** verlangt Basisprofil, Kürzel (Kleinbuchstaben, Ziffern
  und Bindestriche) und Bezeichnung.
- Unter **Bausteine weglassen** haken Sie an, was bei der Installation nicht
  angelegt werden soll. Bereits vorhandene Einträge bleiben unberührt.
- **Ergänzungen** sind ein Profilausschnitt im JSON-Format, aufgebaut wie ein
  Branchenprofil; gleichnamige Bausteine ersetzen die des Basisprofils.
- **Installieren** wendet die Variante an; „Bestehende Einträge
  aktualisieren“ bringt bereits installierte Einträge auf den Stand der
  Variante. Jedes Speichern erhöht die Fassung.
- **Als JSON exportieren** gibt die Variante weiter. **Löschen** entfernt nur
  die Variante; installierte Einträge bleiben erhalten.
