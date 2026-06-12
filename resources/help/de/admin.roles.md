---
title: "Rollen & Rechte"
topic: admin.roles
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
    - roles.admin
---

Die Rechteverwaltung gliedert sich in vier Bereiche:

- **Berechtigungen** (schreibgeschützt): Katalog aller granularen
  Rechte im Schema `ressource.aktion` (z. B. `finance.transfer.time`,
  `month.approve`).
- **Rollen**: Bündel von Berechtigungen, organisationsspezifisch
  anpassbar.
- **Gruppen**: reine Anzeige-Gruppierung von Berechtigungen für die
  Übersicht – ohne eigene Funktionswirkung.
- **Mitglieder**: Zuweisung von Rollen an Organisationsmitglieder.

Typischer Ablauf: Rolle anlegen oder kopieren → Berechtigungen
zuschneiden → Mitgliedern zuweisen → mit einem Testkonto prüfen.

Sicherheitsgrundsätze:

- **Globale Admin-Rolle**: Eine Rolle ohne Organisationsbezug wirkt
  **plattformweit** über alle Mandanten. Sie ist ausschließlich dem
  Betreiber vorbehalten und darf **niemals** über delegierbare
  Berechtigungen oder die Organisations-UI vergeben werden –
  Eskalationsrisiko!
- Prinzip der minimalen Rechte: lieber eine zusätzliche enge Rolle
  als ein breites Sammelrecht.
- Bewusst kein Admin-Bypass in sensiblen Modulen (z. B. Datenschutz,
  Hinweisgebersystem): Diese Rechte müssen ausdrücklich vergeben
  werden – auch an Admins.
