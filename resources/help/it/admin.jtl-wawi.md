---
title: "Collegare JTL-Wawi"
topic: admin.jtl-wawi
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary collega JTL-Wawi come **gestionale di magazzino
principale**: articoli (padre/figlio), magazzini e giacenze arrivano
da JTL; WorkDiary li legge e riconsegna i propri movimenti di
magazzino in modo controllato.

**Modalità operative:** Una Wawi *OnPremise* si collega tramite la sua
istanza API locale (da creare nell’amministratore JTL, porta
predefinita 5883). Se la Wawi si trova nella propria rete, occorre
consentire esplicitamente gli indirizzi privati — questa
autorizzazione è auditata. Il *gateway cloud* usa ID client/segreto e
ID tenant del portale partner JTL.

**Registrazione dell’app (OnPremise):** Aprire prima in JTL-Wawi
«Admin > Registrazione app», poi avviare qui la registrazione e
approvare l’app nella Wawi. La chiave API viene emessa **una sola
volta** e salvata cifrata — non compare mai in log o diagnosi.

**Associazioni:** Dopo la prima sincronizzazione, associare i
magazzini JTL ai magazzini WorkDiary (1:1 per le registrazioni). Gli
articoli vengono associati automaticamente tramite SKU e GTIN; i casi
ambigui finiscono nella inbox delle integrazioni dove si decide —
WorkDiary non crea mai articoli automaticamente.

**Guida delle giacenze:** Sotto «Guida delle giacenze» si sceglie chi
guida: *locale* (WorkDiary), *esterno* (guida JTL, WorkDiary
riconsegna tramite outbox) o *sola lettura*. Il ritorno a «locale»
importa le giacenze JTL come inventario di apertura.

**Nota beta:** L’API JTL-Wawi è attualmente in programma beta/pilota.
Dopo il rilascio ufficiale può dipendere dall’edizione e diventare a
pagamento; una licenza decaduta porta a uno stato bloccato visibile,
mai a registrazioni errate silenziose.
