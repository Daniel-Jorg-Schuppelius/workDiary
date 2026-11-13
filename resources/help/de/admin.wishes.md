---
title: "Wünsche der Organisationen"
topic: admin.wishes
version: 1
keywords:
    - Plattformsicht Wünsche
    - Wünsche bündeln
    - Herstellerkanal
    - Wunsch an den Hersteller
    - Antwort des Betriebs
audience:
    - admin
related:
    - wishes.overview
    - admin.settings
---

Die Plattformsicht zeigt alle Wünsche, die Organisationen an den Betrieb
weitergegeben haben – nur für Plattform-Betreiber. Zu sehen sind der
bereinigte Text, Art, Seite, App-Version, Stimmenzahl und die Organisation.
Wer den Wunsch verfasst oder ihm zugestimmt hat und der ursprüngliche Text
bleiben in der Organisation.

## Bündeln, Stand und Antwort

- **Bündeln:** Gleichartige Wünsche ordnen Sie einem führenden Wunsch
  derselben Art zu. Die Liste zeigt nur führende Wünsche; die Stimmen des
  Bündels zählen zusammen.
- **Stand und Antwort des Betriebs** setzen Sie am führenden Wunsch. Sie
  gelten für das ganze Bündel und erscheinen im Wunsch jeder Organisation als
  „Antwort des Betriebs“; Verfassende und Abstimmende bekommen einen Hinweis.

## Herstellerkanal (selbst betriebene Installation)

Betreibt der Hersteller die Installation, ist diese Sicht bereits der
Hersteller. In einer selbst betriebenen Installation können Sie einzelne
Wünsche per Mail an den Hersteller senden – nur wenn Sie den Kanal in den
**Systemeinstellungen** mit `wishes.vendor_channel` einschalten (Vorgabe aus;
ohne ihn verlässt kein Wunsch die Installation). Die Herstelleradresse steht in
`wishes.vendor_address`; leer gilt die hinterlegte Kontaktadresse.

1. Passen Sie bei Bedarf den **Text für den Hersteller** an und speichern Sie.
2. Prüfen Sie die **Vorschau**: Empfänger, Antwortadresse, Betreff und genau
   den Text, der hinausgeht – Kennung, Art, Text, Seite, App-Version,
   Stimmenzahl und Sie als Absender. Die Organisation und Namen gehen nie mit.
3. **An den Hersteller senden** schickt genau diese Mail. Hat sich seitdem
   etwas geändert (etwa die Stimmenzahl), verlangt die App eine neue Vorschau.

Die Antwort des Herstellers kommt per Mail an Sie zurück. Tragen Sie sie unter
**Antwort des Herstellers** ein; die Organisation sieht sie am Wunsch.
