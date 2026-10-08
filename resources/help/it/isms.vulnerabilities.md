---
title: "Vulnerabilità e advisory"
topic: isms.vulnerabilities
version: 2
keywords:
    - falla di sicurezza
    - CVE
    - CVSS
    - avviso di sicurezza
    - bollettino di sicurezza
    - CSAF
    - VEX
    - patch
    - gestione delle vulnerabilità
    - sfruttabilità
    - SBOM
audience: []
modules:
    - module.isms
related:
    - isms.incidents
    - isms.software
    - isms.risks
    - glossary.core
---

Nel registro delle **Vulnerabilità** Lei gestisce le vulnerabilità note
con criticità, responsabilità e scadenze e decide consapevolmente sulla
loro esploitabilità.

Procedura tipica:

1. **Registrare la vulnerabilità**: titolo, facoltativamente un
   identificativo (ad es. un numero CVE), il valore CVSS e il componente
   interessato. La criticità viene dedotta dal valore CVSS, ma può essere
   modificata manualmente. Facoltativamente collega un prodotto
   dell'inventario software e imposta una scadenza.
2. **Gestire lo stato**: da «Aperta» passando per «In esame» e «In
   mitigazione» fino a «Risolta»; in alternativa «Accettata» (rischio
   residuo consapevole) o «Non interessato».
3. **Decidere l'esploitabilità**: stabilisca se la vulnerabilità è
   sfruttabile nella configurazione concreta. «Esploitabile» e «Non
   esploitabile» richiedono una **motivazione obbligatoria**.

**Importa advisory** (CSAF/VEX): carichi un advisory leggibile
automaticamente in formato JSON. L'importazione confronta i componenti
interessati con l'inventario software e con l'ultima distinta base della
release (SBOM) e crea una voce di vulnerabilità per ogni corrispondenza.

Regola importante: una corrispondenza importata **non è automaticamente
considerata esploitabile**. Inizia nello stato di indagine; il fatto di
essere interessati è una decisione consapevole e motivata. Se un
documento VEX indica «non interessato», la motivazione viene ripresa.

Evidenza: ogni advisory originale importato viene archiviato con una
checksum. Una nuova importazione dello stesso file ha lo stesso effetto e
non crea duplicati.

Autorizzazioni: la consultazione richiede diritti di lettura SGSI, la
gestione e l'importazione richiedono diritti di gestione SGSI.

Passi successivi: le vulnerabilità scadute vengono segnalate ed
escalate.
