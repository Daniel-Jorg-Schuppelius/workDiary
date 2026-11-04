---
title: "Assistenza remota"
topic: admin.remote-support
version: 4
keywords:
    - AnyDesk
    - TeamViewer
    - RustDesk
    - Splashtop
    - pcvisit
    - token API
    - accesso remoto
    - sessione remota
    - teleassistenza
    - manutenzione remota
    - ID dispositivo
    - report sessione
    - desktop remoto
    - controllo remoto
audience:
    - admin
related:
    - admin.support
    - admin.plugins
    - assets.fleet
---

La manutenzione remota acquisisce i report delle sessioni da AnyDesk,
TeamViewer, RustDesk, Splashtop e pcvisit e li trasforma in voci di
tempo. Le sessioni vengono assegnate a un dispositivo (asset, ad es.
postazione di lavoro, server, notebook) tramite l'ID dispositivo del
fornitore. Con **Importa sessioni** può inoltre importare esportazioni
di sessioni (AnyDesk, RustDesk Server Pro) tramite l'importazione
centrale; la riga di intestazione indica il fornitore, una colonna
**Fornitore** ha la precedenza.

La pagina **Manutenzione remota – connessioni non assegnate** ha due
schede; il campo di ricerca trova ID dispositivo, alias, dispositivo o
nota.

Scheda **Dispositivi non assegnati**:

- Qui si raccolgono gli ID che compaiono nei report ma non sono ancora
  assegnati a nessun dispositivo dell'organizzazione – con numero di
  sessioni, durata e periodo.
- Se è presente una **Proposta** (cliente o dispositivo corrispondente),
  la accetta con **Applica**.
- **Dispositivo esistente**: in **Seleziona dispositivo** sceglie un
  dispositivo esistente e poi **Assegna**; le sessioni salvate vengono
  registrate subito come voci di tempo.
- **Nuovo dispositivo**: indicare **Nome**, **Categoria**, **Cliente** e
  facoltativamente **Cliente finale**, poi **Crea e assegna**.
- **Dispositivo multi-cliente**: questa casella, presente in entrambe
  le schede, contrassegna un dispositivo usato per più clienti. Le sue
  sessioni non vengono quindi registrate automaticamente, ma per
  cliente nella seconda scheda.
- **Scarta**: rifiuta tutte le connessioni di un ID; non vengono
  registrate.

Scheda **Assegna sessioni** (dispositivi multi-cliente):

- Selezionare le sessioni, scegliere **Cliente**, facoltativamente
  **Cliente finale** e **Progetto**, poi **Imputa i selezionati** – così
  il tempo finisce presso il cliente giusto.
- **Registra selezione internamente** registra le sessioni senza
  cliente sul progetto di manutenzione interna.
- **Scarta i selezionati** scarta singole sessioni.

Configurare i fornitori (impostazioni del plugin dell'organizzazione, un
interruttore per fornitore):

- **AnyDesk**: ID licenza e password API della licenza; l'ID dispositivo
  è l'ID AnyDesk del computer.
- **TeamViewer**: token script con autorizzazione per i report di
  connessione; l'ID dispositivo è l'ID TeamViewer.
- **RustDesk**: il server RustDesk gratuito non salva le connessioni. Le
  sessioni arrivano solo da un server API che i client hanno inserito
  sotto «Server API». Per **Server Pro** inserisca l'indirizzo della
  console web e un token API (Impostazioni → Token → Crea, autorizzazione
  «Audit Log»); per il **server API open source** (rustdesk-api)
  l'indirizzo con la porta più nome utente e password di un
  amministratore, il fuso orario del server solo se non opera nel fuso
  dell'organizzazione. Se il server è nella propria rete, attivi
  **Consenti indirizzi di rete privati**. L'ID dispositivo è l'ID RustDesk
  del computer (senza spazi). Le connessioni ancora in corso vengono
  acquisite dopo la loro fine.
- **Splashtop**: token API dalla console web (Impostazioni account → Web
  API Tokens; solo proprietari del team e super amministratori, la Open
  API fa parte di Splashtop Enterprise). Scelga la regione del suo
  account; l'ID team viene ricavato dal token. I tipi di sessione si
  scelgono separati da virgole (predefinito: non presidiato e SOS). L'ID
  dispositivo è l'UUID computer di Splashtop; la posta in arrivo mostra
  accanto il nome del computer. Gli altri piani usano l'esportazione CSV
  della pagina dei log tramite l'importazione centrale.
- **pcvisit**: token API utente dalla pcvisit Web App (impostazioni
  account) e un token integratore che pcvisit rilascia al produttore del
  software (impostazione del gestore; da inserire per organizzazione solo
  con un proprio accordo). Vengono acquisite le sessioni chiuse con la
  durata registrata in pcvisit; l'ID dispositivo è l'ID computer di
  pcvisit, il cliente finale è nella nota.

Se un fornitore non risponde, gli altri continuano; il messaggio compare
nello stato del plugin e nel risultato dell'importazione.

Sicurezza e rischi:

- Le credenziali API dei fornitori si trovano nelle impostazioni del
  plugin dell'organizzazione. Il sistema legge i report delle sessioni –
  non concede alcun accesso remoto diretto.
- I dispositivi multi-cliente richiedono un'assegnazione accurata per
  ogni sessione, per evitare registrazioni errate tra clienti diversi.
- **Le connessioni e le sessioni scartate non vengono registrate**; la
  pagina non offre alcun modo per recuperarle.

Autorizzazione: la pagina è riservata agli amministratori.
