---
title: "Lizenzverwaltung"
topic: admin.license
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
---

Die Lizenzseite zeigt, was deine Installation darf: **Plan**
(free/pro/enterprise), **Benutzer-/Organisations-Limits**,
freigeschaltete **Module** und das **Ablaufdatum**.

So hängt alles zusammen:

- Die **Lizenz ist die Quelle** für Plan und Add-on-Module; die
  Zuordnung Plan → Module liegt in der Konfiguration. Neue Module
  eines Plans stehen damit ohne Neuausstellung der Lizenz bereit.
- **Organisationsgebundene Lizenzen** lassen sich je Organisation
  installieren und entfernen; fehlt eine Org-Lizenz, greift die
  globale Lizenz als Fallback.
- **Ohne gültige Lizenz** läuft die Installation hart im Free-Plan.

Typische Aktionen:

1. Lizenzstatus und Module prüfen.
2. **Feature-Flags** gezielt übersteuern (Override-Schalter).
3. Org-Lizenz **installieren/entfernen** oder – sofern deine
   Installation dazu berechtigt ist – neue Lizenzen **ausstellen**
   (Lizenznehmer, E-Mail, Plan, Add-ons, Ablauf, Limits, Organisation,
   Domain).

Mandantenstatus (SaaS):

- Der **Mandantenstatus** zeigt, ob die Organisation in der
  **Testphase**, **aktiv** oder **gesperrt** ist. Ist kein Status
  fest gesetzt, wird er aus Testphase und Lizenz-Ablauf abgeleitet
  (gültig / in Kulanz / abgelaufen).
- Ein Plattform-Admin kann den Status manuell auf **aktiv**,
  **Testphase** oder **gesperrt** setzen oder per *Automatisch
  (ableiten)* wieder freigeben.
- Bei **gesperrt** (oder endgültig abgelaufener Lizenz) sind
  **schreibende Aktionen deaktiviert**; Lesen bleibt möglich. Die
  Lizenz- und Logout-Seiten bleiben erreichbar, damit die Sperre
  aufgehoben werden kann.
- Das **Nutzerlimit** der Lizenz wird beim Anlegen neuer Mitglieder
  durchgesetzt: ist das Limit erreicht, wird die Anlage mit Hinweis
  blockiert.

Wichtig zu wissen:

- Plan-Downgrades sperren Module über das Plan-Gating; Inhalte
  aufbewahrungspflichtiger Module bleiben erhalten.
- Es werden keine Dateien hochgeladen – Lizenzen werden als signierte
  Schlüssel eingetragen.
