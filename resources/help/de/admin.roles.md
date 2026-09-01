---
title: "Rollen & Rechte"
topic: admin.roles
version: 2
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - org.members
    - roles.admin
---

## Zweck und Hintergrund

Die Rechteverwaltung steuert, wer in WorkDiary was sehen und tun darf.
Sie gliedert sich in vier Bereiche: **Berechtigungen**
(schreibgeschützter Katalog granularer Rechte im Schema
`ressource.aktion`, z. B. `month.approve`), **Rollen** (Bündel von
Berechtigungen, organisationsspezifisch anpassbar), **Gruppen** (reine
Anzeige-Gruppierung ohne Funktionswirkung) und **Mitglieder**
(Zuweisung von Rollen).

## Voraussetzungen

- Administrationsrechte der Organisation.
- Ein Testkonto ohne Admin-Rechte, um Zuschnitte wirklich zu prüfen.
- Klarheit, welche Aufgabenprofile es im Betrieb gibt (Außendienst,
  Teamleitung, Buchhaltung …).

## Empfohlener Ablauf

1. **Rolle anlegen oder kopieren** — eine vorhandene Rolle als Basis
   spart Fehlversuche.
2. **Berechtigungen zuschneiden:** lieber eine zusätzliche enge Rolle
   als ein breites Sammelrecht (Prinzip der minimalen Rechte).
3. **Mitgliedern zuweisen.**
4. **Mit dem Testkonto prüfen**, bevor die Rolle in die Breite geht.

![Rollenverwaltung mit Systemrollen und Berechtigungszahl](media/administration/rollen.png)
*Die Rollenverwaltung: Systemrollen der Organisation mit der Zahl ihrer Berechtigungen.*

## Beispiel aus der Praxis

Für eine neue Bürokraft wird die Rolle „Innendienst" von „Teamleitung"
kopiert, um Freigaberechte gekürzt und zugewiesen. Der Test mit dem
Prüfkonto zeigt: Monatsfreigaben sind unsichtbar, Auftragsanlage
funktioniert — genau wie beabsichtigt.

## Typische Fehler

- **Globale Admin-Rolle vergeben:** Eine Rolle ohne Organisationsbezug
  wirkt **plattformweit** über alle Mandanten. Sie gehört
  ausschließlich dem Betreiber und darf niemals über delegierbare
  Rechte oder die Organisations-UI vergeben werden —
  Eskalationsrisiko.
- **Admin-Bypass erwarten:** Sensible Module (Datenschutz,
  Hinweisgebersystem) verlangen ausdrückliche Rechtevergabe — auch an
  Admins. Das ist Absicht.
- **Sammelrollen wuchern lassen:** Breite Rollen sind bequem und
  später kaum rückbaubar.

## Auswirkungen und nächste Schritte

Rollenänderungen wirken sofort auf alle zugewiesenen Mitglieder — auch
auf Menüs, Hilfe-Inhalte und Modulzugriffe. Als Nächstes: Zuweisungen
unter „Mitglieder" pflegen und die Sicherheitshinweise im
Admin-Handbuch lesen.
