---
title: "Pagamenti in uscita SEPA"
topic: finance.sepa
version: 1
audience: []
related:
    - finance.incoming-invoices
    - invoices.manage
    - contacts.manage
---

La distinta di pagamento raggruppa le fatture passive approvate in un bonifico
cumulativo SEPA. workDiary genera un **file, non un ordine di pagamento**: il
pagamento viene disposto nel programma bancario con la sua autorizzazione.

**Proposta di pagamento:** L’elenco contiene tutte le fatture passive aperte
approvate per il pagamento. Per ognuna viene proposta la data di esecuzione
più conveniente — la data dello sconto finché è raggiungibile, altrimenti la
scadenza. L’importo è allora già ridotto dello sconto. Ogni posizione è
deselezionabile; una fattura senza IBAN viene mostrata come bloccata e non
entra nella distinta.

**Tre passaggi:** comporre (bozza) → approvare → esportare. L’approvazione è
un diritto a sé: chi compone la distinta non deve necessariamente poterla
approvare. Dopo l’esportazione la distinta è immutabile; l’annullamento è
possibile solo prima e rende le fatture di nuovo pagabili.

**Detrazione:** Singole posizioni possono essere ridotte finché la distinta è
una bozza — ad esempio per una ritenuta a garanzia verso il fornitore. Un
importo ridotto richiede un motivo; importo fatturato e importo pagato
restano poi affiancati.

**Prova:** Il file generato viene archiviato come documento riservato e il suo
hash SHA-256 registrato sulla distinta. Un secondo recupero restituisce lo
stesso file — mai uno nuovo con un identificativo di messaggio diverso, che
la banca potrebbe interpretare come un secondo pagamento.

**Mandati e addebito:** Per l’addebito diretto il registro dei mandati
conserva riferimento, data di firma e tipo (una tantum/ricorrente). Un
mandato non viene mai cancellato ma revocato — la revoca è la prova da quando
l’addebito non era più consentito. Dopo 36 mesi senza addebiti un mandato
decade. Il preavviso è di cinque giorni lavorativi bancari per il primo
addebito e di due per quelli successivi. L’addebito richiede
l’identificativo creditore dell’organizzazione (impostazione
«identificativo creditore» nel registro delle impostazioni).

**Modulo aggiuntivo:** La generazione del file appartiene al modulo a
pagamento dei formati bancari. Senza di esso distinta e registro dei mandati
restano utilizzabili; manca solo l’esportazione.
