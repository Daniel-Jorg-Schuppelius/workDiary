---
title: "Assets & Fuhrpark"
topic: assets.fleet
version: 9
keywords:
    - Fuhrparkverwaltung
    - Fahrzeugverwaltung
    - Inventar
    - Betriebsmittel
    - Geräteverwaltung
    - Gerät ausgeben
    - Werkzeugausgabe
    - Ausleihe
    - Tankbuch
    - Wartungsintervall
    - Defekt melden
    - Objektakte
audience: []
modules:
    - module.fuhrpark
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Assets und Fahrzeuge bilden betriebliche Objekte mit Status,
Zuständigkeit, Dokumenten und Wartungsinformationen ab. Tank- und
Ladelogs ergänzen den Verbrauchsverlauf.

Erfassen Sie Stammdaten und eindeutige Kennungen, ordnen Sie Standort oder
Verantwortliche zu und pflegen Sie Wartungsintervalle sowie relevante
Dokumente. Statusänderungen sollten den tatsächlichen Lebenszyklus
widerspiegeln.

Vor Löschung oder Ausmusterung prüfen, ob offene Wartungen, Vorgänge,
Fahrten oder Dokumente verknüpft sind. Kritische Historie sollte
archiviert und nicht durch Überschreiben verloren gehen.

## Kategorien

Das Feld „Kategorie“ bietet die allgemeinen IT-Kategorien und die Kategorien
Ihrer Organisation an. Branchenprofile bringen passende Kategorien mit, etwa
Rohrkamera und Pressmaschine bei SHK oder Pulsoximeter und BtM-Schrank im
Profil Ambulante Pflege. Eigene Kategorien legen Administratoren unter
Klassifikationen in der Domäne „Asset-Kategorien“ an. Eine deaktivierte Kategorie wird für neue
Assets nicht mehr angeboten, bleibt aber an Assets stehen, die sie schon
tragen.

## Ausgabe und Rückgabe

Über das Panel „Ausgabe / Rückgabe" auf der Asset-Detailseite wird ein
Gerät an eine Person oder ein Team ausgegeben, optional mit
Auftragsbezug und erwarteter Rückgabe. Pro Asset gibt es höchstens eine
offene Zuweisung; ein bereits ausgegebenes oder wegen Defekt gesperrtes
Asset kann nicht erneut ausgegeben werden. Bei der Rückgabe wird das
Asset wieder verfügbar. Überschreitet eine Ausgabe die erwartete
Rückgabe, erscheint ein Überfällig-Hinweis und der Fristen-Scanner
benachrichtigt die ausleihende Person bzw. die Teamleitung.

## Defekte und Sperren

Im Panel „Defekte / Sperren" lassen sich Mängel mit Schweregrad
erfassen. Ist „Asset sperren" gesetzt, blockiert der offene Defekt jede
weitere Ausgabe, bis er erledigt oder ausgebucht wird. Für das Erledigen
oder Ausbuchen ist eine Lösungsnotiz erforderlich.

## Wartungspläne

In der Karte **Wartungspläne** legen Sie mit **Plan anlegen**
wiederkehrende Wartungen an: **Bezeichnung**, **Intervall** mit **Wert**,
**Toleranz (Tage)** und **Erste Fälligkeit**. Bei Tagen, Wochen und
Monaten rechnet **Erledigt** die nächste Fälligkeit aus dem Intervall;
bei Betriebsstunden und Kilometern tragen Sie sie selbst ein.
**Pausieren** nimmt den Plan aus der Fälligkeitsprüfung.

Wer SLA-Verträge sehen darf, bindet den Plan an einen **SLA-Vertrag** –
einen Vertrag des Asset-Kunden oder einen ohne Kundenbindung – und setzt
**Vertragspflicht**, wenn der Plan einen Pflichttermin dieses Vertrags
abbildet. Der Plan erscheint dann am Vertrag unter **Vertragspflichtige
Wartungen**; das Kennzeichen steht auch am Asset, in der Objektakte und
im Kundenportal. Ist der Helpdesk freigeschaltet, legt **Bei Fälligkeit**
fest, was die tägliche Fälligkeitsprüfung tut: **Service-Ticket anlegen**
erzeugt je Fälligkeit genau ein Ticket mit dem gewählten Vertrag, **Nur
Hinweis (kein Vorgang)** vermerkt die Fälligkeit nur.

## SLA-Vertrag am Asset

Wer SLA-Verträge sehen darf, wählt im Formular **Bearbeiten** unter
**Verträge & SLA** einen **SLA-Vertrag** für das Asset – einen Vertrag des
gespeicherten Kunden oder einen ohne Kundenbindung. OLA, projektgebundene und
inaktive Verträge stehen nicht zur Wahl. Der Vertrag gilt für Service-Tickets
zu diesem Asset vor den Verträgen von Projekt und Kunde (Thema „SLA, Verträge
& Service-Level“); er steht im Kopf der Detailseite und in der Objektakte als
**Direktzuordnung**. Ohne Recht auf SLA-Verträge fehlt das Feld, und die
Bindung bleibt beim Speichern unverändert.

## Objektakte (Lebenszyklus)

Die „Objektakte" bündelt den gesamten Lebenszyklus eines Assets in einer
zusammenhängenden, druckbaren Ansicht: Stammdaten, Standort und Raum,
abgeleiteter Lebenszyklus-Status (in Betrieb, ersetzt oder stillgelegt),
Inbetriebnahme, Außerbetriebnahme und Garantie. Darunter erscheinen
Wartungen, Ausgaben und Rückgaben, Defekte und Sperren, verknüpfte
Aufträge, Protokolle, Materialeinsätze, offene Punkte und Anhänge sowie
der vollständige Lebenszyklus-Verlauf.

Die Akte ist über den Button „Objektakte" auf der Asset-Detailseite
erreichbar und lässt sich über die Druckfunktion des Browsers als
Dokument ausgeben (Anhängen von „?print=1" öffnet den Druckdialog
direkt). Der Lebenszyklus-Status wird aus Status, Außerbetriebnahme und
Garantie abgeleitet – es gibt keine separate Pflege.

## Außer Betrieb nehmen

**Außer Betrieb nehmen** auf der Asset-Detailseite nimmt ein Asset endgültig
aus dem Betrieb. Datum und Grund sind Pflicht; beides steht danach im Kopf der
Detailseite und in der Objektakte. Ein noch ausgegebenes Asset nehmen Sie
vorher zurück. Mit dem Schritt werden die Wartungspläne pausiert, die
Prüfpflichten ruhen und offene Prüftermine werden storniert. Das Asset lässt
sich danach nicht mehr ausgeben, verleihen, reservieren oder in einer Prozedur
verwenden und fehlt in den Auswahllisten für Verleih und Druckaufträge. Die
Akte mit allen Nachweisen bleibt erhalten. Der Schritt lässt sich nicht
zurücknehmen; im Formular „Bearbeiten" steht der Status „Außer Betrieb" nicht
zur Wahl.

**Berechtigung:** **Assets/Objekte stilllegen**.

## Eigentümerwechsel

**Eigentümer wechseln** auf der Asset-Detailseite verzeichnet einen Wechsel
von Eigentum (Organisation, Kunde, Extern) und Kunde, auf Wunsch mit Notiz.
Jeder Wechsel steht mit Datum, Von, Nach, Person und Notiz in der Objektakte
unter „Eigentümerwechsel-Historie"; die Einträge lassen sich nicht ändern.
Wählen Sie im Formular „Bearbeiten" einen anderen Kunden, ist das derselbe
Wechsel: Er wird mit der dort eingetragenen Notiz verzeichnet.

Wechselt der Kunde, entfallen Bindungen an Verträge des bisherigen Kunden –
am Asset und an seinen Wartungsplänen, dort samt **Vertragspflicht**; Verträge
ohne Kundenbindung bleiben. Einen Vertrag des neuen Kunden wählen Sie nach dem
Speichern im erneut geöffneten Formular.

**Berechtigung:** **Eigentümerschaft von Assets übertragen** – auch für den
Kundenwechsel im Formular „Bearbeiten".

## Status, Sperre und Zustand

Die Status **Gesperrt** und **In Reparatur** sperren ein Asset überall, wo
auch eine Sperre aus Prüfwesen oder Verleih wirkt: Ausgabe, Verleih,
Fahrzeugreservierung, Druckauftrag und Start einer Prozedur am Asset oder an
einem Auftrag zu diesem Asset. Eine Prozedurvorlage, die gesperrte Assets
ausdrücklich erlaubt (etwa die Reparatur), startet trotzdem. Der Status legt
dafür eine Sperre mit dem Grund „Manuell gesperrt" bzw. „Wartung" an. Sie
endet, wenn Sie den Status zurücksetzen oder die Sperre unter **Prüfmittel**
aufheben; das Asset ist dann wieder **Aktiv**. Befristete Ausnahmefreigaben
gelten für diese Sperre wie für jede andere.

Der **Zustand** neben dem Status wird abgeleitet und nicht von Hand gepflegt:
**Kritisch** bei aktiver Sperre oder blockierendem Defekt, **Eingeschränkt**
bei offenem Defekt oder überfälliger Wartung bzw. Prüfung, sonst **In
Ordnung**. Er wird bei jeder Änderung und nachts nachgezogen.

Ändern Sie den Standort-Freitext oder den Raum, steht der Wechsel als
„Standort geändert" mit altem und neuem Standort in der Timeline.

## Rücknahme mit Zustand

**Zurücknehmen** öffnet einen Dialog für **Zustand bei Rückgabe** und
**Notiz**. Beides steht danach in der Verlaufstabelle des Panels „Ausgabe /
Rückgabe" neben dem Zustand bei Ausgabe und in der Objektakte.

## Cockpit

Unter den Abschnitten der Detailseite zeigen die Module, was zum Asset vorliegt: offene **Service-Tickets**, **Leasing und Finanzierung** (Verträge, an denen das Asset hängt), offene **Reklamationen**, offene **Gewährleistungsfristen** aus Aufträgen und Protokollen zum Asset, **offene Punkte** und **Prozeduren**. Ein Panel erscheint nur mit freigeschaltetem Modul, mit dem Recht der zugehörigen Liste und wenn es Einträge gibt; der Klick öffnet den Eintrag.

**Wieder in Betrieb nehmen:** Ein außer Betrieb genommenes Asset nehmen Sie über die gleichnamige Aktion wieder in Betrieb — mit Grund und Eintrag im Audit-Protokoll, mit demselben Recht wie die Außerbetriebnahme. Pausierte Wartungspläne und ruhende Prüfpflichten bleiben aus und werden einzeln wieder aufgenommen. Ein über einen Entsorgungsauftrag entsorgtes Asset bleibt außer Betrieb; das Bearbeitungsformular ändert diesen Status nicht.

## Stückliste

Die **Stückliste** eines Geräts führt, was in diesem Exemplar verbaut ist:
Artikel oder Freitext, Menge, Einbauort, Seriennummer, Einbaudatum und
Wechselintervall. Ersetzen legt ein neues Teil an und lässt das alte mit
Ausbaudatum in der Historie stehen; fällige Verschleißteile erscheinen als
Vorschlag. Unter **Material aus Einsätzen** stehen die Materialverbräuche, die
am Stundenzettel diesem Gerät zugeordnet wurden. **Übernehmen** trägt ein
Material mit dem Einsatztag als Einbaudatum in die Stückliste ein – mit Artikel,
wenn das Material einen hat; jeder Verbrauch lässt sich nur einmal übernehmen.
