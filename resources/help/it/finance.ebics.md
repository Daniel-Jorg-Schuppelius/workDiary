---
title: "Accesso bancario EBICS"
topic: finance.ebics
version: 1
keywords:
    - collegamento bancario
    - estratti conto
    - estratto conto giornaliero
    - importare movimenti
    - inviare distinta di pagamento
    - bonifici
    - lettera INI
    - chiavi bancarie
    - firma elettronica
    - configurare EBICS
    - interfaccia bancaria
    - CAMT
audience: []
modules:
    - module.finance
related:
    - finance.reconciliation
---

Con EBICS (versione 3.0) workDiary recupera gli estratti giornalieri
direttamente dalla banca e invia le distinte di pagamento, senza scaricare
e caricare file nell'online banking.

**Configurazione:** Nei conti bancari il simbolo della banca apre
l'accesso EBICS del conto. Inserisca l'URL EBICS, l'ID host, l'ID cliente
e l'ID partecipante indicati nella lettera di accesso della banca. Poi, in
questo ordine: generare le chiavi, inviarle alla banca (INI e HIA),
scaricare la lettera di inizializzazione, firmarla e inviarla alla banca.
Quando la banca ha attivato l'accesso, recuperi le chiavi della banca:
solo allora l'accesso è attivo.

**Estratti giornalieri:** Un accesso attivato recupera ogni mattina gli
estratti (camt.053) e li inserisce nella riconciliazione dei pagamenti;
«Recupera estratti ora» lo fa subito. Gli estratti già importati vengono
riconosciuti e saltati.

**Distinte di pagamento:** Una distinta approvata si può inviare alla
banca con «Invia tramite EBICS»: lo stesso file disponibile per il
download, e una sola volta. Il firmatario autorizzato concede poi
l'autorizzazione del pagamento (firma elettronica) presso la banca.

**Sicurezza:** Le chiavi sono memorizzate cifrate e protette in più da una
passphrase. Ogni passo e ogni ordine sono registrati nella cronologia
dell'accesso. In caso di sospetto abuso, «Blocca accesso» blocca le chiavi
presso la banca; poi la configurazione ricomincia con nuove chiavi.
