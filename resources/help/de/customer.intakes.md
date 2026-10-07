---
title: "Kundeneingänge"
topic: customer.intakes
version: 1
audience: []
related:
    - customer.queries
    - print.orders
---

Kunden reichen über das Kundenportal unter **Anfragen und Aufträge** Druck- oder IT-Anfragen mit Dateien ein. Jeder Eingang erhält eine Vorgangsnummer und erscheint hier mit Kunde, Leistungsart, Status, Wunschtermin und zuständiger Person. Offene Eingänge stehen immer in der Liste, abgeschlossene grenzt der Zeitraum im Kopf ein.

So bearbeiten Sie einen Eingang:

- **Zuständigkeit** übernimmt den Eingang; er wechselt auf „In Bearbeitung".
- **Rückfrage stellen** schickt dem Kunden eine Frage samt Dateien. Der Eingang wartet dann auf seine Rückmeldung; Antwort und Nachreichung nehmen die Bearbeitung wieder auf.
- **Interne Notiz** bleibt im Betrieb — Notiz und angehängte Dateien sieht der Kunde nie.
- **Angebot**: Legen Sie ein neues Angebot an oder verknüpfen Sie ein bestehendes des Kunden. Nach Freigabe und Versand entscheidet der Kunde im Portal; eine überarbeitete Fassung verknüpfen Sie neu, eine frühere Zustimmung gilt nie für eine andere Fassung.
- **Übernehmen** legt nach der Annahme den Druckauftrag bzw. das Ticket an. Eine Teilannahme verlangt einen dokumentierten Abgleich des Umfangs. Wiederholte oder gleichzeitige Übernahmen erzeugen keinen zweiten Vorgang.
- **Ablehnen** verlangt eine Begründung, die der Kunde liest. Nach einer Angebotsannahme ist das nicht mehr möglich.

Nach der Übernahme ist die Fachakte maßgeblich; der Kunde sieht deren Stand. Über **Nachreichung öffnen** darf er weitere Dateien an den übernommenen Eingang hängen. Scheitert eine Mail an den Kunden, zeigt der Eingang einen Hinweis — gespeicherte Angaben und Entscheidungen bleiben davon unberührt.

**Upload-Link (Nextcloud):** Ist das Nextcloud-Plugin mit eigenen Zugangsdaten für den Upload-Kanal eingerichtet, öffnet der Kunde am Eingang einen Upload-Link mit Passwort. WorkDiary übernimmt neue Dateien alle 15 Minuten oder per „Jetzt abholen“, prüft sie wie Portal-Uploads und widerruft den Link nach der letzten Übernahme, sobald der Eingang keine Dateien mehr annimmt oder der Link abläuft. Fehler stehen am Link und im Verlauf; die Dateien bleiben zusätzlich in Nextcloud liegen.
