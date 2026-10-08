---
title: "Assistenza remota"
topic: admin.remote-support
version: 3
keywords:
    - AnyDesk
    - TeamViewer
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

La manutenzione remota acquisisce i report delle sessioni da AnyDesk e
TeamViewer e li trasforma in voci di tempo. Le sessioni vengono
assegnate a un dispositivo (asset, ad es. postazione di lavoro, server,
notebook) tramite l'ID dispositivo (ID AnyDesk/TeamViewer). Con
**Importa sessioni** può inoltre importare le sessioni AnyDesk tramite
l'importazione centrale.

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

Sicurezza e rischi:

- Le credenziali API dei fornitori si trovano nelle impostazioni del
  plugin dell'organizzazione. Il sistema legge i report delle sessioni –
  non concede alcun accesso remoto diretto.
- I dispositivi multi-cliente richiedono un'assegnazione accurata per
  ogni sessione, per evitare registrazioni errate tra clienti diversi.
- **Le connessioni e le sessioni scartate non vengono registrate**; la
  pagina non offre alcun modo per recuperarle.

Autorizzazione: la pagina è riservata agli amministratori.
