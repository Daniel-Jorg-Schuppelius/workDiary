# Externe Beteiligte, Subunternehmer und Prüfer

## Status

In Progress — MVP umgesetzt: kontextbezogene, befristete externe Einladungen
(`external_participants`, Subject DiaryEntry|Protocol|Document) mit
hash-gespeichertem Einmal-Token (Muster ProtocolSignatureToken /
IsmsAuditPackageToken), login-freier öffentlicher Zugriff (`/extern/{token}`,
gedrosselt, 404 bei abgelaufen/widerrufen/unbekannt), serverseitig streng
durchgesetzten `abilities` (view|comment|upload|confirm), append-only
Nachweis aller externen Aktionen (`external_participant_events`), interner
Verwaltung (Panel „Externe Beteiligte" auf der Auftragsdetailseite: Einladen,
Einmal-Link, Widerruf) und Permission `externalParticipant.manage`
(admin, teamleitung + Auftragsverantwortlicher via Subject-Policy). Offen:
externe Kontakt-/Rollenprofile als wiederverwendbares Stammdaten-Modell,
Panel-Anbindung an Protokoll-/Dokument-Detailseiten, E-Mail-Versand des Links.

## Ziel

WorkDiary soll externe Beteiligte abbilden können: Subunternehmer, Lieferanten,
externe Prüfer, Steuerberater, Auditoren, Hersteller oder Kundenvertreter. Sie
brauchen andere Rechte und Nachweise als interne Mitarbeitende oder Kunden.

## Warum

Viele Aufträge werden nicht nur intern erledigt. Externe müssen Aufgaben
erhalten, Protokolle bestätigen, Dokumente hochladen oder Prüfungen freigeben,
ohne Zugriff auf unnötige Kundendaten zu bekommen.

## MVP

- Externe Kontakt- und Rollenprofile.
- Kontextbezogene Einladungen zu Auftrag, Protokoll oder Dokument.
- Begrenzte Rechte und Ablaufdatum.
- Nachweis externer Bestätigungen, Uploads und Kommentare.
- Audit-Log für externe Zugriffe.

## Akzeptanzkriterien

- Externe sehen nur freigegebene Inhalte.
- Externe Aktionen sind klar als extern erkennbar.
- Zugänge können befristet und widerrufen werden.
- Datenschutz und Mandantentrennung gelten vollständig.

## Abhängigkeiten

- Kundenportal und Freigaben
- Rollen, Rechte und Produktprofile
- Datenschutz, Sicherheit und Datenlebenszyklus
- Audit

## GitHub Issues

- TBD
