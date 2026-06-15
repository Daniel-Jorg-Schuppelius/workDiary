---
title: "Branchenprofile"
topic: admin.branch-profiles
version: 1
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

So gehst du vor:

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
