---
title: "Bürgschaften"
topic: guarantees.overview
version: 1
keywords:
    - Bürgschaftsregister
    - Bankbürgschaft
    - Aval
    - Avalbürgschaft
    - Gewährleistungsbürgschaft
    - Vertragserfüllungsbürgschaft
    - Sicherheitseinbehalt
    - Einbehalt ablösen
    - Bürgschaftsurkunde
    - Bürgschaft zurückfordern
    - Sicherheitsleistung
audience: []
related:
    - invoices.manage
    - warranties.overview
    - contacts.manage
---

Das Bürgschaftsregister führt gestellte und erhaltene Bürgschaften — mit
Aussteller, Betrag, Laufzeit und der hinterlegten Urkunde.

**Richtung:** *Erhalten* sind Bürgschaften von Lieferanten und
Nachunternehmern, *gestellt* die eigenen zugunsten von Auftraggebern. Die
Richtung entscheidet, wer am Ende die Urkunde zurückerhält.

**Einbehalt ablösen:** Eine Bürgschaft kann einen Sicherheitseinbehalt
ablösen. Der Einbehalt gilt danach als *besichert*, der zurückgehaltene
Betrag wird ausgezahlt und die offene Forderung entsprechend erhöht. Eine
Bürgschaft über weniger als den Einbehalt wird abgelehnt — sie würde die
Sicherheit stillschweigend verringern.

**Rückgabe:** Nach Ablauf wird die Urkunde zurückgefordert und der Vorgang
als *zurückgegeben* geschlossen. Eine gezogene Bürgschaft bleibt mit Datum
und Grund dauerhaft dokumentiert.

## Kopplung an die Gewährleistung

Eine Bürgschaft kann auf eine **Gewährleistungsfrist** zeigen. Ist deren Ende erreicht, meldet die Fristenprüfung die **Rückgabe** der Urkunde — wie nach der Freigabe eines abgelösten Einbehalts. Ebenso übernimmt ein Sicherheitseinbehalt an der Rechnung das Ende der gewählten Gewährleistung als Freigabetermin und zieht nach, wenn sich die Frist verschiebt.

## Rückgabe fällig und Verfall

**Rückgabe fällig:** Kachel und Filter zeigen aktive Bürgschaften, deren
Sicherungszweck beendet ist — der abgelöste Einbehalt wurde an der Rechnung
freigegeben, oder die gekoppelte Gewährleistung ist abgelaufen. Ein abgelöster
Einbehalt ist bereits ausgezahlt; seine Freigabe (Rechnungsseite, Karte
**Sicherheitseinbehalte**) erzeugt keine neue Forderung, sondern meldet nur die
Urkunde zur Rückgabe.

**Verfallen:** Ist die Befristung überschritten, setzt die tägliche
Fristenprüfung die Bürgschaft auf *Abgelaufen*; der letzte Tag der Befristung
zählt noch. Im Register lässt sich das auch sofort mit **Als verfallen
vermerken** festhalten. Verfallene Bürgschaften zählen nicht mehr als aktiv.
Die Rückgabe der Urkunde lässt sich weiterhin protokollieren, und wer eine
Verlängerung nachträgt (neues Datum bei **Befristet bis**), macht die
Bürgschaft wieder aktiv.
