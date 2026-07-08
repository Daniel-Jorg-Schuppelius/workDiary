---
title: "Vulnerabilità e advisory"
topic: isms.vulnerabilities
version: 1
audience: []
related:
    - isms.incidents
    - isms.software
    - isms.risks
    - glossary.core
---

Nel **registro delle vulnerabilità** gestisci le vulnerabilità note con
criticità, responsabilità e scadenze e decidi consapevolmente sulla loro
sfruttabilità. Registra la vulnerabilità con titolo, eventuale numero CVE,
valore CVSS e componente interessato; la criticità è derivata dal CVSS ma
può essere modificata. Cura lo stato da "Aperta" a "Risolta", oppure
"Accettata" o "Non interessato"; le decisioni "sfruttabile"/"non
sfruttabile" richiedono una **motivazione obbligatoria**. Puoi importare
advisory leggibili da macchina (CSAF/VEX) come JSON: l'import confronta i
componenti con l'inventario software e l'ultima SBOM e crea una voce per
ogni corrispondenza, senza considerarla automaticamente sfruttabile né
creare duplicati a reimport. Consultazione con diritti di lettura ISMS,
gestione e import con diritti di manutenzione ISMS.
