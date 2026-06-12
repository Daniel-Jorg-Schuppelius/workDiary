# Hilfe, Dokumentation und In-App-Unterstützung

## Status

In Progress — technische Basis (MVP-051, Issue #50) und kontextbezogene
Prozesshilfe umgesetzt (2026-06-11): rechte, nicht-blockierende
Desktop-Sidebar (mobil Drawer), Route→Topic-Registry
(config/help-topics.php, 80 Mappings) mit automatischem Seitenkontext und
Hilfe-Button im Header, ?-Shortcut, localStorage-Zustand, definierter
Fallback mit Suche; 60 Topics in de+en (ISMS/Zertifizierung, Datenschutz,
Dokumente, Formulare, Wissensbasis, Kommunikation, Faktura-Übergabe,
Lohnexport, 5 Rollen-Einstiege, Glossar, 7-teiliges Admin-Handbuch mit
audience-Steuerung). Offen: fr/it/es-Topics (en-Fallback greift),
Leere-Zustände-Audit, Begriffs-Tooltips in der UI. Verwandt:
[Onboarding-Checkliste](../onboarding-checkliste.md) (MVP-048, Issue #47).

## Ziel

WorkDiary soll Anwendern und Admins direkt im Produkt helfen: kurze Hilfetexte,
Kontextinformationen, leere Zustände, Onboarding, Admin-Handbuch,
Rollenleitfäden und Prozessdokumentation sollen die Einführung und tägliche
Nutzung erleichtern.

Die Hilfe soll nicht nur einzelne Felder oder Begriffe erklären. Auf jeder
relevanten Seite soll sie auch die dort möglichen Prozesse, Voraussetzungen und
nächsten Schritte verständlich zusammenfassen.

## Warum

Ein breites Produkt braucht gute Führung. Ohne Hilfe entstehen Supportaufwand,
Fehlkonfigurationen und Ablehnung bei Nutzern. Gute Dokumentation macht das
Produkt verkaufbarer und erleichtert lokale Installationen.

## MVP

- In-App-Hilfe für zentrale Bereiche.
- Rollenbasierte Einstiegshilfen: Außendienst, Teamleitung, Buchhaltung,
  Admin, Geschäftsführung.
- Admin-Handbuch für Mandanten, Rollen, Backups, Lizenz, Import und Sicherheit.
- Leere Zustände mit nächster sinnvoller Aktion.
- Kurze Erklärung kritischer Begriffe wie Abnahme, Prozedur, SLA, Zeitkonto.
- Linkstruktur von UI zu relevanter Dokumentation.

## Kontextbezogene Prozesshilfe

Auf relevanten Seiten kann über einen einheitlichen Hilfe-Button eine Sidebar
am rechten Bildschirmrand geöffnet werden. Sie bleibt innerhalb des aktuellen
Workflows und erklärt ausschließlich Inhalte, die zur geöffneten Seite und zur
Rolle des Nutzers passen.

Die Sidebar enthält je nach Seite:

- Zweck und fachlichen Kontext der Seite.
- Mögliche Prozesse und typische Reihenfolge der Arbeitsschritte.
- Voraussetzungen, Berechtigungen und benötigte Stammdaten.
- Erklärung wichtiger Statuswerte, Felder und Aktionen.
- Hinweise zu Risiken, Fristen oder irreversiblen Aktionen.
- Nächste sinnvolle Schritte und Links zu verwandten Hilfethemen.
- Optional kurze Beispiele, Screenshots oder Verweise auf ausführliche
  Dokumentation.

Beispiel ISMS: Auf einer Seite zur Konformität erklärt die Hilfe den Weg von
Geltungsbereich und Anforderungen über Kontrollen und Nachweise bis zur
Bewertung des Normstatus und zur Zertifizierung.

## Bedienkonzept

- Desktop: rechte, ein- und ausklappbare Sidebar; der Seiteninhalt bleibt
  bedienbar und wird nicht dauerhaft verdeckt.
- Kleine Bildschirme: Drawer oder Vollbild-Overlay mit identischem Inhalt.
- Der Hilfe-Button befindet sich an einer konsistenten Stelle im Seitenkopf.
- `?` öffnet die Hilfe zum aktuellen Seitenkontext.
- Beim Wechsel der Seite wird automatisch das passende Hilfethema geladen.
- Der zuletzt gewählte Zustand darf lokal gespeichert werden, sofern dadurch
  keine fachlichen oder personenbezogenen Daten erfasst werden.
- Fokusführung, Tastaturbedienung, Screenreader-Beschriftung und Schließen per
  `Escape` sind verbindlich.

## Inhaltsmodell

Die bestehende topic-basierte Hilfe unter `resources/help/{locale}` bleibt die
Quelle der Inhalte. Seiten oder Routen erhalten einen stabilen Topic-Code und
können zusätzlich prozessbezogene Abschnitte referenzieren.

Inhalte müssen:

- versioniert und über Pull Requests pflegbar sein,
- mindestens nach Sprache und Zielgruppe unterscheidbar sein,
- ohne fest codierte HTML-Fragmente in einzelnen Blade-Views auskommen,
- fehlende Übersetzungen kontrolliert auf eine Standardsprache zurückfallen
  lassen,
- Links nur auf erreichbare Bereiche zeigen, für die der Nutzer berechtigt ist.

Die Sidebar darf keine Geschäftslogik duplizieren. Rollen, Berechtigungen,
Modulfreigaben und verfügbare Aktionen werden aus dem tatsächlichen
Seitenkontext abgeleitet.

## Einführung

Die Prozesshilfe wird schrittweise je Modul ergänzt. Priorität haben Bereiche
mit komplexen Abläufen, vielen Statusübergängen oder erhöhtem
Erklärungsbedarf:

1. ISMS, Datenschutz und Zertifizierungsmanagement.
2. Aufträge, Protokolle, Prozeduren und Zeiterfassung.
3. Administration, Rollen, Mandanten und Lizenzierung.
4. Auswertungen, Importe und weitere Fachmodule.

## Akzeptanzkriterien

- Neue Admins können Grundkonfigurationen ohne Entwicklerhilfe finden.
- Nutzer verstehen leere Zustände und Validierungsfehler.
- Kritische Einstellungen enthalten Warn- und Erklärungstexte.
- Dokumentation unterscheidet zwischen Anwender-, Admin- und Betreiberwissen.
- Relevante Seiten können einen stabilen Hilfe-Topic deklarieren, ohne
  individuelles Sidebar-Markup zu duplizieren.
- Die rechte Hilfe-Sidebar zeigt den Kontext, die möglichen Prozesse und die
  nächsten Schritte der aktuellen Seite.
- Nicht verfügbare oder nicht erlaubte Aktionen werden nicht als ausführbar
  dargestellt.
- Seitenwechsel aktualisieren den Hilfekontext ohne veraltete Inhalte
  anzuzeigen.
- Desktop- und Mobilansicht sind vollständig per Tastatur und Screenreader
  bedienbar.
- Fehlt ein Topic oder eine Übersetzung, bleibt die Seite nutzbar und zeigt
  einen definierten Fallback.
- Automatisierte Tests decken Topic-Auflösung, Rollenfilter, Fallback,
  Öffnen/Schließen und mindestens einen vollständigen Modulprozess ab.

## Abhängigkeiten

- Einheitliche Bedienung und UX-Konventionen
- Import, Migration und Onboarding
- Rollen, Rechte und Produktprofile
- Mandantenfähigkeit und Betriebsmodelle

## GitHub Issues

- MVP-Basis: Issue #50
- Kontextbezogene Prozesshilfe als rechte Sidebar: TBD
