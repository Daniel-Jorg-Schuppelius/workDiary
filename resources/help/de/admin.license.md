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

Wichtig zu wissen:

- Plan-Downgrades sperren Module über das Plan-Gating; Inhalte
  aufbewahrungspflichtiger Module bleiben erhalten.
- Es werden keine Dateien hochgeladen – Lizenzen werden als signierte
  Schlüssel eingetragen.
