---
title: "Schwachstellen & Advisories"
topic: isms.vulnerabilities
version: 1
audience: []
related:
    - isms.incidents
    - isms.software
    - isms.risks
    - glossary.core
---

Im **Schwachstellenregister** führst du bekannte Schwachstellen mit
Kritikalität, Verantwortung und Fristen und entscheidest bewusst über
ihre Ausnutzbarkeit.

Typischer Ablauf:

1. **Schwachstelle erfassen**: Titel, optional eine Kennung (z. B. eine
   CVE-Nummer), den CVSS-Wert und die betroffene Komponente. Die
   Kritikalität wird aus dem CVSS-Wert abgeleitet, lässt sich aber
   übersteuern. Optional verknüpfst du ein Produkt aus dem
   Softwareinventar und setzt eine Frist.
2. **Status pflegen**: von „Offen" über „In Prüfung" und „In Behebung"
   bis „Behoben"; alternativ „Akzeptiert" (bewusstes Restrisiko) oder
   „Nicht betroffen".
3. **Ausnutzbarkeit entscheiden**: Lege fest, ob die Schwachstelle in der
   konkreten Konfiguration ausnutzbar ist. „Ausnutzbar" und „Nicht
   ausnutzbar" erfordern eine **Pflichtbegründung**.

**Advisories importieren** (CSAF/VEX): Lade ein maschinenlesbares Advisory
als JSON hoch. Der Import gleicht die betroffenen Komponenten gegen das
Softwareinventar und die letzte Release-Stückliste (SBOM) ab und legt je
Treffer einen Schwachstelleneintrag an.

Wichtige Regel: Ein importierter Treffer gilt **nicht automatisch als
ausnutzbar**. Er startet in der Untersuchung; die Betroffenheit ist eine
bewusste, begründete Entscheidung. Sagt ein VEX-Dokument „nicht betroffen",
wird die Begründung übernommen.

Nachweis: Jedes importierte Original-Advisory wird mit Prüfsumme
abgelegt. Ein erneuter Import derselben Datei bleibt wirkungsgleich und
erzeugt keine Dubletten.

Berechtigungen: Einsicht erfordert ISMS-Leserechte, Pflege und Import
erfordern ISMS-Pflegerechte.

Nächste Schritte: Überfällige Schwachstellen werden gemeldet und
eskaliert.
