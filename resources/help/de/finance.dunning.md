---
title: "Mahnwesen"
topic: finance.dunning
version: 1
audience:
    - admin
    - buchhaltung
modules:
    - module.finance
related:
    - invoices.manage
    - finance.reconciliation
    - finance.open-times
---

Das Mahnwesen verfolgt **offene Rechnungen** in bis zu drei Stufen. Es gibt
zwei Wege: die **Einzelmahnung** aus der Rechnung heraus und den
**Mahnlauf**, der alle fälligen Posten auf einmal aufnimmt.

## Was eine Mahnung ist — und was nicht

Eine Mahnung ist ein **Schreiben**, kein Beleg. Sie erzeugt **keine
Buchung** und keinen neuen Rechnungsbeleg; sie ändert nur den Mahnstatus
der bestehenden Rechnung. Das ist wichtig für den Abgleich: der offene
Betrag bleibt derselbe, auch nach der dritten Stufe.

**Verzugszinsen werden ausgewiesen, nicht gebucht.** Ist in der
Organisation ein Zinssatz größer null hinterlegt, rechnet das System
taggenau ab Fälligkeit (Basis 365 Tage) und weist den Betrag im Schreiben
aus. Ob und wann er tatsächlich gefordert wird, entscheidet die
Buchhaltung — deshalb entsteht daraus keine Forderung im Konto.

## Stufen und Karenz

Karenztage, Gebühr und Zahlungsfrist je Stufe kommen aus der
Organisationseinstellung. Die Karenz verhindert, dass eine Rechnung am Tag
nach Fälligkeit gemahnt wird, während die Überweisung noch unterwegs ist.

## Bevor Sie mahnen

Prüfen Sie den **Bankabgleich**. Die häufigste vermeidbare Mahnung geht an
jemanden, der längst bezahlt hat — der Eingang war nur noch nicht
zugeordnet.
