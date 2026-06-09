# Datenschutz, Sicherheit und Datenlebenszyklus

## Status

Proposed — Teilaspekt *Supportzugriff* ist mit MVP-004 (Issue #4) in
[`docs/security/supportzugriff-grundsaetze.md`](../security/supportzugriff-grundsaetze.md)
verbindlich geregelt. Die **Datenschutzseite für Org-Admins** ist mit MVP-005
(Issue #5) in
[`docs/security/datenschutzseite-konzept.md`](../security/datenschutzseite-konzept.md)
konzipiert (Routen, Sektionen, Permissions, Akzeptanzkriterien).

## Produktversprechen

WorkDiary schützt Betriebs-, Kunden-, Mitarbeitenden- und Auftragsdaten. Das
Geschäftsmodell darf nicht auf Datenverkauf, versteckter Weitergabe,
Werbeprofilen oder fremder kommerzieller Datenauswertung beruhen. Kundendaten
gehören dem Kunden. WorkDiary verarbeitet Daten nur zur Bereitstellung des
Produkts, zur Erfüllung vertraglicher Pflichten und für ausdrücklich
freigegebene Funktionen.

## Ziel

Datenschutz und Datensicherheit sollen als Kernmerkmal des Produkts sichtbar
sein. Das gilt für lokale Installationen, Private-Cloud-Betrieb und SaaS. Die
App muss Datenminimierung, Zweckbindung, Rollen- und Zugriffskontrolle,
Aufbewahrung, Löschung, Export, Supportzugriffe und Protokollierung sauber
abbilden.

## Warum

WorkDiary verarbeitet sensible Daten: Arbeitszeiten, Einsatzorte, Fotos,
Kundeninformationen, Protokolle, Unterschriften, Krankheit, Qualifikationen,
Abrechnungen und interne Betriebsabläufe. Diese Daten dürfen nicht als
Nebenprodukt verwertet werden. Datenschutz ist deshalb nicht nur Compliance,
sondern ein Verkaufsargument und Vertrauensanker.

## Grundsätze

- Kein Verkauf von Kunden-, Mitarbeitenden-, Auftrags- oder Nutzungsdaten.
- Keine versteckte Weitergabe an Werbe-, Tracking- oder Datenbroker-Dienste.
- Keine produktübergreifende Kundendatenanalyse ohne ausdrückliche,
  dokumentierte Freigabe.
- Datenminimierung: nur erfassen, was fachlich notwendig ist.
- Zweckbindung: Daten werden nur für definierte Produkt- und Vertragszwecke
  genutzt.
- Transparenz: Kunden können sehen, welche Daten verarbeitet werden.
- Datenhoheit: Export, Löschung und Mandantenwechsel sind planbar.
- Lokaler Betrieb bleibt möglich, wenn Kunden Daten vollständig selbst halten
  wollen.

## MVP

- Datenschutz- und Sicherheitskonzept als Produktdokumentation.
- Mandantenbezogene Datenexporte und Löschprozesse.
- Aufbewahrungs- und Löschfristen pro Datenbereich.
- Protokollierung von Supportzugriffen und administrativen Zugriffen.
- Rollenbasierte Einschränkung besonders sensibler Daten.
- Konfigurierbare externe Dienste mit klarer Datenfluss-Dokumentation.
- AVV-/DPA-Grundlage für SaaS-Kunden.
- Sicherheitsseite für Admins: aktive Sessions, API-Tokens, externe Integrationen,
  letzte Exporte, letzte Supportzugriffe.

## Akzeptanzkriterien

- Ein Kunde kann nachvollziehen, welche Daten WorkDiary speichert und warum.
- Ein Mandant kann exportiert und gelöscht werden, ohne andere Mandanten zu
  beeinflussen.
- Supportzugriffe sind zeitlich, personell und inhaltlich nachvollziehbar.
- Externe Integrationen sind explizit aktivierbar und dokumentiert.
- Sensible Daten wie Krankheit, Signaturen, personenbezogene Arbeitszeiten und
  private Anhänge sind besonders geschützt.
- SaaS und On-Premise folgen denselben Datenschutzgrundsätzen.

## Später

- Kundenfreigabe für Supportzugriff mit Ablaufzeit.
- Datenschutz-Dashboard mit Datenkategorien und Fristen.
- Operatives Datenschutzmanagement für VVT, AVV, Betroffenenanfragen und
  Datenschutzvorfälle, siehe
  [Feature 043](./043-datenschutzmanagement-vvt-avv-betroffenenrechte.md).
- Automatische Löschläufe mit Review-Modus.
- Verschlüsselung besonders sensibler Felder.
- Regionale Datenhaltung für SaaS.
- Sicherheits- und Datenschutzbericht pro Release.

## Abhängigkeiten

- Mandantenfähigkeit und Betriebsmodelle
- Rollen und Rechte
- Audit
- Anhänge und Storage
- Integrationen und API
- Backup und Restore
- Kundenportal

## GitHub Issues

- TBD
