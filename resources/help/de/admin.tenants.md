---
title: "Organisationen & Mandanten"
topic: admin.tenants
version: 3
keywords:
    - Mandantenverwaltung
    - Mandant anlegen
    - Firma anlegen
    - Organisation löschen
    - Organisation sperren
    - Organisation wechseln
    - Mandantenwechsel
    - Datenexport
    - Purge
    - Tarif ändern
    - Multi-Tenant
    - Freigabestufen
    - Organisationsliste
audience:
    - admin
related:
    - admin.handbook
    - admin.license
    - admin.roles
    - admin.organization-settings
---

Hier verwalten Sie Organisationen (Mandanten). Jede Organisation ist
eine abgeschottete Einheit – sämtliche Daten gehören genau einem
Mandanten.

Typische Aktionen:

- **Anlegen/Bearbeiten**: Stammdaten und Plan der Organisation.
- **Deaktivieren/Reaktivieren**: reversibel – die Organisation wird
  gesperrt, Daten bleiben erhalten.
- **Exportieren**: Datenexport im Sinne der Datenübertragbarkeit
  (Art. 20 DSGVO).
- **Endgültig löschen (Purge)**: Löschung nach Art. 17 DSGVO.
- **Wechseln**: globale Admins können in den Kontext einer anderen
  Organisation wechseln (Org-Switcher).

Plan und Module: Jede Organisation hat einen Plan (free/pro/
enterprise) bzw. eine organisationsgebundene Lizenz; daraus ergeben
sich die freigeschalteten Module (z. B. Finance, ISMS, Datenschutz) –
Details im Kapitel **Lizenz**.

Risiken und unumkehrbare Aktionen:

- **Purge ist irreversibel** – alle Daten der Organisation werden
  endgültig gelöscht (Audit-gepflegt). Vorher immer Export anbieten
  und Aufbewahrungspflichten prüfen.
- Deaktivieren ist die sichere Alternative, wenn nur der Zugang
  beendet werden soll.

Genehmigungen: Im gleichnamigen Abschnitt der Organisation legen Sie fest,
welche Rolle die Freigabestufen einer Vertragsverhandlung je Stufenart
unter „Genehmigungen“ sieht (kaufmännisch, fachlich, HR). Leer bleibt die
Vorgabe: Buchhaltung, Teamleitung, Personalverwaltung. Die Freigabe an der
Akte bleibt davon unberührt.

## Organisationsliste und eigene Organisation

Die Liste **Organisationen** mit allen Mandanten gibt es nur für den
Plattformbetrieb: im Systemmenü (Zahnrad-Symbol **System** in der Kopfzeile)
unter **Organisation** → **Organisationen**. Plattformbetreiber ohne eigene
Organisation finden außerdem im Verwaltungsmenü (Symbol **Verwaltung** in der
Kopfzeile) unter **Personal** den Punkt **Mitarbeiter**; er führt ebenfalls auf
die Organisationsliste. Öffnet ein solcher Administrator die Liste, ordnet
WorkDiary sein Konto der zuerst angelegten Organisation zu – danach führt
**Mitarbeiter** zur Mitgliederverwaltung dieser Organisation.

Administratoren einer Organisation bearbeiten ihre eigene Organisation im
Systemmenü unter **Organisation** → **Organisation** (Dialog **Organisation
bearbeiten**; Einzelheiten im Thema „Organisation und Einstellungen“). **Plan**
und Aktiv-Status setzt nur der Plattformbetrieb: Org-Admins sehen den Plan im
Abschnitt **Plan & Status** nur zur Information („Der Plan folgt der Lizenz und
wird vom Betreiber gepflegt.“), einen Schalter für den Aktiv-Status haben sie
nicht.
