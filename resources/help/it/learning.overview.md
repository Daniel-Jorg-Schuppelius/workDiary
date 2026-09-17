---
title: "Piattaforma di apprendimento"
topic: learning.overview
version: 3
audience: []
related:
    - training.overview
    - safety.overview
    - learning.standards
    - learning.subtitles
---

La piattaforma risponde a **come si impara e come si viene verificati**.
*Che cosa* ciascuno deve svolgere ed entro quando resta nella gestione della
formazione — i due moduli si incastrano senza duplicarsi.

## Costruire un corso

Un corso si compone di sezioni e unità didattiche. Un'unità è contenuto, una
verifica, un compito, un incontro in presenza o materiale esterno. Il
contenuto si costruisce con blocchi (testo, titolo, nota, lista di controllo,
immagine, galleria, file, video, audio, incorporamento, codice, accordion,
tabella, articolo della knowledge base, procedura, domanda di comprensione,
separatore) — l'HTML libero non è previsto di proposito.

**Ogni blocco si usa anche senza vista, udito o mouse.** Le immagini e ogni
immagine di una galleria richiedono un testo alternativo, l'audio una
trascrizione e le colonne di una tabella un'intestazione. L'accordion e la
soluzione di una domanda di comprensione si aprono da tastiera. Una domanda di
comprensione non viene valutata: nell'editor le risposte corrette iniziano con
`*`. Il blocco procedura mostra la versione valida; la procedura si avvia da una
voce del diario.

**Gli incorporamenti richiedono un host abilitato.** Altrimenti la policy di
sicurezza bloccherebbe la pagina silenziosamente nel corso; l'editor rifiuta
quindi subito e in modo visibile un host non abilitato. Gli host consentiti si
gestiscono nelle impostazioni.

Un corso può avere **prerequisiti** (tutti, o ne basta uno): bloccano l’avvio,
non l’assegnazione — le iscrizioni obbligatorie sono esenti. Un **esame senza
corso** è un corso di tipo «esame» con esattamente un’unità di prova; chi lo
supera ottiene il riconoscimento del corso di destinazione configurato — con lo
stesso ritorno in certificato, prova di formazione e qualifica.

Con **Ordine fisso**, un corso sblocca ogni unità solo quando la precedente è
completata; le unità bloccate mostrano «Dopo l'unità precedente». La data di
rilascio dell'unità vale in aggiunta.

Chi arriva da LearnDash importa lo **ZIP di esportazione** (catalogo → «Importazione LearnDash»): corsi, lezioni, argomenti e verifiche nascono come bozze, le domande finiscono nel catalogo con la loro categoria. Immagini e media non vengono copiati (segnaposto da completare), i video delle lezioni solo da host consentiti. I corsi completati vengono annotati come iscrizioni «importate» per le persone con e-mail corrispondente — senza certificato né attestato, perché un completamento importato non è una prova propria. La prova mostra in anticipo cosa verrebbe creato.

## La pubblicazione congela il contenuto

Con la pubblicazione nasce una versione del corso con un'immagine completa del
contenuto. Le partecipazioni in corso restano sulla loro versione — la materia
non cambia sotto chi è già a metà. Dopo la pubblicazione il contenuto è
bloccato; le correzioni passano da una versione successiva.

Se il corso è collegato a un corso obbligatorio, la pubblicazione vi scrive
anche la versione. La prova successiva porta così lo stesso numero.

Le opzioni del corso governano il percorso: un **piano di sblocco** (giorni
dall'iscrizione e/o data fissa — vale la più tarda) blocca un'unità fino a quel
giorno in ogni punto di completamento — player, portale, accesso esterno e
sincronizzazione offline — non solo nella visualizzazione. Una **permanenza
minima** conta dalla prima apertura dell'unità o tramite il tempo di
apprendimento. Le **unità di anteprima** si leggono nel portale senza
iscrizione (solo testo). Le **categorie** delle impostazioni ordinano il
catalogo, i **tag** aggiungono un asse trasversale e restano modificabili
dopo la pubblicazione; **finestra di disponibilità** e **limite di partecipanti** valgono
per l'auto-iscrizione — l'amministrazione può sempre assegnare, le iscrizioni
obbligatorie ignorano il limite. I compiti hanno **regole sui file**
(estensioni, numero, dimensione — mai più permissive del sistema) e, a scelta,
un'**approvazione automatica** a punteggio pieno, incompatibile con il
principio dei quattro occhi.

## Il tempo di apprendimento è orario di lavoro

La formazione obbligatoria deve svolgersi **durante l'orario di lavoro**
(§ 12 c. 1 ArbSchG). Ogni corso porta quindi una politica oraria:

- **Solo durante l'orario di lavoro** (predefinito per i corsi obbligatori):
  l'avvio fuori orario viene rifiutato.
- **Conta sempre come orario di lavoro**: per la formazione disposta.
- **Fuori orario solo con approvazione**.
- **Volontario, non retribuito**: solo per offerte davvero aggiuntive —
  bloccato per i corsi con obbligo collegato.

Il tempo **in orario** non viene contato due volte: è già registrato dalla
presenza. Il tempo **fuori orario** crea una fascia di presenza, così vengono
verificati riposo, durata massima e lavoro notturno.

## Verifiche

Un tentativo congela le domande poste. Se una domanda cambia in seguito, un
risultato passato resta spiegabile — è esattamente ciò che chiede un
verificatore dopo un incidente. I tentativi non vengono mai eliminati; una
correzione si affianca al valore iniziale invece di sostituirlo.

I temi li valuta una persona. L'IA propone corsi e domande e risponde alle
domande nel contesto del corso — **non può valutare né decidere**.

Le domande vivono nella **banca delle domande** dell’organizzazione (menu
«Formazione» → «Banca delle domande») con categoria e nome breve; una prova
punta a domande della banca e la stessa domanda può comparire in più prove.
Oltre alla lista fissa, le **regole di estrazione** scelgono a ogni tentativo
un numero di domande casuali da una categoria («5 da antincendio»). Rimuovere
una domanda da una prova la lascia nella banca; si elimina solo ciò che non è
usato — e un tentativo svolto conserva sempre la propria copia delle domande.

Lo svolgimento si imposta per prova: tutte le domande in una pagina o una
domanda per pagina, indietro e salto consentiti, risposte obbligatorie, testi di
risultato per fascia percentuale e un suggerimento per domanda. Le risposte
vengono salvate a ogni modifica — dopo un’interruzione della connessione non si
perde nulla; scaduto il tempo conta solo ciò che era salvato in tempo. La
panoramica mostra le domande risposte e segnate.

I valutatori consultano l’**atto del tentativo** (domande della copia
congelata, risposte date, punti, correzioni) — ogni consultazione viene
registrata. Ogni prova ha le sue **statistiche** (tentativi, tasso di
superamento, tempo, tasso di errore per domanda — quote solo dal gruppo minimo).
Dall’elenco dei partecipanti si può concedere un **tentativo aggiuntivo**
nonostante limite o attesa, una sola volta e con motivazione.

Il **registro voti** per corso mostra chi apprende × componenti. Senza componenti somma i punti di verifiche e compiti; se i formatori definiscono **componenti** (verifica, compito, voto manuale) possono assegnare pesi — tutti insieme 100 oppure nessuno. I voti manuali sono additivi: una correzione è una nuova voce, vale l'ultima. La **pagella** (PDF) e l'export CSV derivano dallo stesso calcolo; finché qualcosa è aperto la pagella riporta «provvisorio».

Finezze per domanda: le opzioni di risposta possono avere **punti propri** («Etichetta {3}», anche negativi) — conta allora l'opzione scelta invece del tutto-o-niente; un'**autovalutazione** è una scala senza risposta corretta, il livello scelto è il valore; un **elaborato** accetta testo, file o entrambi — il file è disponibile nella valutazione. Per verifica il superamento può essere richiesto in aggiunta **in punti** e il sottoinsieme per tentativo fissato **in percentuale** delle domande disponibili.

## Prove

Un corso superato produce effetti in un unico punto: certificato con codice di
verifica, prova di formazione nel registro sicurezza, obbligo assolto e
qualifica prolungata. Non nasce un secondo sistema di prove.

I certificati si verificano tramite un link. La pagina mostra corso, data,
validità ed emittente — il nome solo abbreviato.

I dati di apprendimento appartengono alla persona: il **rapporto per
l'interessato** (modulo protezione dati) elenca iscrizioni, tentativi,
certificati, tempo di apprendimento e prenotazioni come contatori con periodo —
mai i testi delle domande o le risposte. Il **piano di conservazione** propone
la cancellazione delle iscrizioni concluse senza certificato una volta scaduto
il termine regionale (tentativi e tempo di apprendimento seguono); i certificati
restano più a lungo come prova e vengono poi ridotti alle iniziali — il link di
verifica continua a rispondere.

## Competenze

La **matrice delle competenze** (Apprendimento → Competenze)
mostra il livello raggiunto da ogni persona per ciascuna competenza. I livelli
nascono in due modi: un corso collegato a una competenza attesta il suo livello
al completamento — ripeterlo non lo abbassa mai e, nei corsi con periodo di
validità, il livello vale solo per quel periodo. Una **valutazione** da parte
della gestione della formazione può invece anche abbassare un livello.

Per ruolo si può impostare un **livello richiesto**. Se una persona è al di
sotto, la matrice segnala la lacuna; i livelli scaduti non contano. Le
competenze non bloccano nulla: il blocco resta alle qualifiche.

## Chi impara

Oltre ai dipendenti, i clienti possono formarsi tramite il portale e i
partecipanti esterni senza account. Questi ultimi ricevono un link monouso a
tempo; la loro prova è identica a quella interna.

I partecipanti di un corso si gestiscono dalla scheda del corso in
«Partecipanti»: iscrivere persone dell'organizzazione o esterne, modificare
scadenza e accesso con un motivo, annullare (mai le iscrizioni obbligatorie) e
creare il link di accesso per gli esterni — un nuovo link invalida il
precedente. Una prenotazione confermata invia il link automaticamente.

Le **impostazioni della piattaforma** (catalogo, diritto di gestione)
contengono l’interruttore per punti e classifica, gli host di incorporamento
consentiti e la **vista formatore**: se attiva, chi ha diritti di autore o di
valutazione vede solo i corsi propri o a cui è assegnato — catalogo, cockpit di
valutazione, statistiche e analisi seguono la stessa regola. La gestione
continua a vedere tutto.

Nel player ogni persona conserva **note private** sull'unità o sul corso —
visibili solo a lei, nemmeno all'amministrazione, assenti dalla ricerca
attività; «I miei corsi» le raccoglie. Una **domanda al formatore** va alla
persona responsabile e ai formatori del corso: come ticket con il modulo
helpdesk, altrimenti via e-mail — entrambi vengono inoltre notificati. Due
riquadri della dashboard (nascosti di default) mostrano i propri corsi aperti
e le valutazioni arretrate; la ricerca attività trova i corsi pubblicati —
chi apprende i propri, gli autori tutti.

Il **catalogo** si mostra come elenco o riquadri (la scelta resta salvata per persona) e riporta una **valutazione a stelle** dal feedback del corso — solo da cinque risposte, perché nulla sia riconducibile a singoli. Il portale mostra inoltre il **prezzo** dell'articolo collegato. Nel player la **modalità concentrazione** nasconde la barra laterale; «Duplica» crea una nuova bozza da un corso — materiale sì, iscrizioni e attestati no.

## Analisi e cogestione

L'analisi mostra tassi e anomalie, non profili individuali. I tassi compaiono
solo da cinque iscrizioni in su, così non si risale alle persone. Punti,
riconoscimenti e classifica sono disattivati per impostazione predefinita; la
classifica mostra inoltre solo chi acconsente espressamente.

Le notifiche seguono le regole dell’organizzazione: assegnazione, scadenza
vicina, ritardo (con escalation), consegna ricevuta, valutazione disponibile,
certificato, promozione dalla lista d’attesa, decisione sulla prenotazione e
approvazione del tempo di apprendimento. L’IA ha tre ingressi — bozza della
struttura e delle domande nell’editor, tutor nel player — e si limita a
proporre; adozione e valutazione restano manuali. Punti e riconoscimenti
compaiono in «I miei corsi»; la classifica mostra solo chi ha acconsentito.
