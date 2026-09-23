---
title: "Quote"
topic: club.fees
version: 1
audience: []
modules:
    - module.club
related:
    - club.members
    - club.groups
---

La gestione delle quote fa parte della base associativa e funziona senza graduazioni. Richiede il diritto «gestire le quote» (tesoreria); l'amministrazione legge, le direzioni di gruppo non vedono dati sulle quote.

**Tariffe e aliquote:** Le tariffe sono nominate liberamente (ad es. adulti, bambini, ridotta, passiva, famiglia) e portano gli importi come aliquote per data di validità: periodicità (mensile, trimestrale, semestrale, annuale), importo, ancora di fatturazione (mese iniziale del periodo), scadenza in giorni dall'inizio del periodo, regola di ripartizione e quota di ammissione opzionale. Nuove aliquote non modificano crediti già rilasciati. I supplementi di sezione sono posizioni proprie per soci con assegnazione attiva a un gruppo della sezione. Non esistono aliquote associative incorporate.

**Conti quote e obbligati al pagamento:** Un conto quote è la persona obbligata al pagamento nell'anagrafica clienti/debitori. Un genitore può pagare per più figli senza essere socio. L'assegnazione socio → conto e tariffa è esplicita con periodo; mai automatica per e-mail o IBAN coincidenti. Per socio vale al massimo un'assegnazione alla volta.

**Quota familiare:** Una tariffa familiare è una tariffa fissa per nucleo: tutti i soci assegnati al conto con questa tariffa producono esattamente una posizione di quota base per periodo. In alternativa restano quote individuali con uno sconto esplicito in percentuale e motivo. Non esiste una quota base familiare e individuale piena contemporaneamente.

**Ripartizione:** Ogni aliquota è «periodo intero» o «giornaliera». Giornaliera conta i giorni di calendario attivi del periodo (assegnazione, ingresso/uscita) divisi per i giorni del periodo; ogni posizione è arrotondata al centesimo. Un ingresso a metà mese produce quindi la quota corrispondente.

**Esenzioni:** Esenzioni e riduzioni si registrano espressamente con periodo e motivo. Una pausa dell'iscrizione da sola non esonera da alcuna quota.

**Limiti di età:** Le tariffe possono avere limiti di età. Se un socio non rientra più alla data di riferimento, il controllo giornaliero contrassegna l'assegnazione per la verifica; la gestione quote conferma il cambio con data di efficacia o mantiene la tariffa. Nulla cambia automaticamente.

**Anteprima:** L'anteprima quote mostra per un mese di fatturazione tutte le posizioni dei periodi che iniziano in quel mese con la base di calcolo. Assegnazioni incomplete o tariffe senza aliquota compaiono come errori, non vengono omesse. I crediti nascono solo con l'elaborazione delle quote.

**Elaborazione e crediti:** Un'elaborazione congela l'anteprima di un mese di fatturazione (tutti i periodi che iniziano in quel mese). Gli errori nell'anteprima bloccano il rilascio. Il rilascio crea esattamente un credito con posizioni per conto quote; ripetizione, recupero ed elaborazione parallela non creano duplicati, perché ogni sorgente e periodo può essere rivendicato una sola volta. Gli importi rilasciati non cambiano con successive modifiche di tariffa o famiglia. Se la fatturazione è esterna (ad es. Lexoffice), anteprima e lista di consegna restano possibili; il rilascio locale è bloccato.

**Avviso di quota:** Ogni credito ha un avviso PDF con periodo, dettaglio, scadenza e causale (il numero del credito). L'invio via e-mail è separato dal rilascio; ogni tentativo riceve una prova di consegna, gli errori restano visibili. Il testo a piè (ad es. nota sull'attribuzione fiscale/contabile) si imposta nelle impostazioni dell'associazione.

**Partite aperte, storno, correzione:** I crediti sono aperti, parzialmente pagati, pagati o stornati; lo scaduto deriva da scadenza e importo residuo. Un credito non pagato può essere stornato con motivo, i periodi tornano liberi per una nuova elaborazione. Le correzioni sono crediti distinti e collegati (integrazione o nota di credito); gli importi rilasciati non vengono mai sovrascritti. Un'uscita termina le quote future ma non elimina le partite aperte esistenti.

**Pagamenti:** I pagamenti sono registrazioni proprie sul conto quote (bonifico, contanti, addebito SEPA, altro). Senza credito scelto vengono assegnati per scadenza: un pagamento cumulativo della famiglia copre più crediti; un resto rimane come credito compensabile con crediti successivi. Lo stesso denaro non viene mai conteggiato due volte: se la riconciliazione bancaria trova un accredito il cui importo è già registrato manualmente o tramite incasso, collega il movimento a quel pagamento invece di crearne un secondo. Annullare un abbinamento riprende solo il pagamento bancario; un pagamento registrato manualmente rimane.

**Storno addebito:** Uno storno compensa esattamente un pagamento (una sola volta), riapre il residuo e blocca un nuovo incasso finché la gestione quote non toglie il blocco esplicitamente. Una commissione bancaria nasce come integrazione collegata; il credito originale resta invariato.

**Sollecito:** Si sollecitano solo crediti scaduti e non bloccati, al massimo in tre livelli (promemoria, sollecito, ultimo sollecito). Termine e spese si impostano consapevolmente per sollecito, non ripresi dai valori predefiniti delle fatture; le spese di sollecito sono un credito collegato proprio. Il sollecito esiste come PDF ed e-mail con prova di consegna. I crediti contestati o dilazionati ricevono un blocco sollecito con motivo.

**Incasso SEPA:** La proposta di incasso elenca i residui scaduti dei conti con mandato utilizzabile (il mandato attivo del cliente o uno fissato sul conto). Un lotto di incasso è un lotto di addebiti del modulo finanze: rilascio ed esportazione pain.008 avvengono lì. Ogni tentativo riceve un riferimento univoco (numero di credito e di tentativo), il lotto riserva la posizione contro un nuovo incasso. L'esportazione non è un pagamento: solo «Registra incasso» dopo l'accredito segna il credito come pagato. Senza il pacchetto finanze restano possibili pagamenti, solleciti e preparazione; solo l'esportazione SEPA no.
