---
title: "Registro dei rischi"
topic: isms.risks
version: 3
keywords:
    - analisi dei rischi
    - valutazione dei rischi
    - matrice dei rischi
    - aggiungere rischio
    - mappa dei rischi
    - trattamento del rischio
    - rischio residuo
    - accettazione del rischio
    - probabilità
    - rischio lordo
    - rischio netto
audience: []
modules:
    - module.isms
related:
    - isms.controls
    - isms.overview
    - isms.audits
    - glossary.core
---

Nel **Registro dei rischi** Lei registra, valuta (5×5) e tratta i rischi
per la sicurezza delle informazioni per ambito di applicazione. Lo trova
in **SGSI** → **Governance** → **Registro dei rischi**.

Procedura tipica:

1. **Aggiungi rischio**: **Titolo**, **Categoria** («Organizzativo»,
   «Tecnico», «Fisico», «Personale», «Fornitore»), **Riferimento
   (sistema/processo/sede)**, **Minaccia** (la minaccia o vulnerabilità
   di fondo), **Responsabile** e **Riesame previsto**.
2. **Valutare**: **Probabilità** (1–5) × impatto (1–5) dà il
   **Punteggio** (1–25). Semaforo della matrice dei rischi: Basso
   (punteggio ≤ 6), Medio (punteggio 7–12), Alto (punteggio > 12).
3. Scegliere il **Trattamento**: «Evitare», «Ridurre», «Trasferire» o
   «Accettare» – e assegnare misure in **Misure collegate**.
4. Aggiornare lo **Stato** con **Cambia stato** lungo la catena:
   «Identificato» → «Analizzato» → «Trattato»/«Accettato» → «Chiuso». Un
   rischio chiuso può essere riportato ad «Analizzato».

Storico delle valutazioni:

- Con **Registra valutazione** crea una valutazione; il **Tipo di
  valutazione** è «Lordo», «Netto» oppure «Obiettivo». Ogni valutazione
  ha una **Motivazione**, facoltativamente una data **Valido fino al**
  (data di scadenza o di riesame) e passa da «Bozza» ad «Approvata»
  (**Approva**).
- **Le valutazioni approvate sono immutabili.**
- La valutazione **netta** approvata più recente determina i valori
  mostrati sul rischio. Se modifica probabilità o impatto direttamente
  sul rischio, viene creata automaticamente una valutazione diretta
  approvata – lo storico resta completo.

Regola importante: il passaggio ad **«Accettato»** (accettazione del
rischio residuo) richiede una valutazione netta approvata **con data
«Valido fino al»**.

Autorizzazioni: la consultazione richiede il permesso **Vedere i registri
SGSI (rischi, misure, DdA)**; le modifiche richiedono **Gestire il SGSI
(rischi, misure, importazione catalogo)**.

Passi successivi: quando la data **Valido fino al** della valutazione
netta approvata più recente di un rischio aperto si avvicina o è
superata, la persona responsabile riceve una notifica; con le **Regole
di notifica** è possibile anche un’escalation. Il campo **Riesame
previsto** del rischio serve alla pianificazione e all’ordinamento, ma
non attiva di per sé alcuna notifica.
