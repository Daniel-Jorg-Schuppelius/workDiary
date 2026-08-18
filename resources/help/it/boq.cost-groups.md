---
title: "Gruppi di costo secondo DIN 276"
topic: boq.cost-groups
version: 1
audience: []
related:
    - boq.overview
---

Le voci portano **assegnazioni di catalogo**: il gruppo di costo indica *per che
cosa* si spende, la categoria di lavori *chi* esegue. Entrambi arrivano di norma
già con il file della stazione appaltante — nell’edilizia federale tedesca lo
StLB-Bau è obbligatorio come base del capitolato e fornisce il gruppo di costo
con ogni variante di testo.

**L’anagrafica dei cataloghi è inclusa.** Vengono forniti i gruppi di costo
DIN 276 nelle edizioni **2018-12** (tre livelli) e **2008-12** oltre alle
categorie di lavori StLB — solo numeri e denominazioni brevi, nessun testo
normativo.

**Le due edizioni DIN convivono, non si sostituiscono.** «310» significa «scavo»
nell’edizione 2008 e «scavo, movimento terra» nel 2018; l’edizione 2018 ha
inoltre riorganizzato i 200, 500 e 600/700. Un intervento in corso continua a
contabilizzare secondo la propria edizione.

## Assegnare

**Costruzioni → computo metrico → Assegna.** Il filtro **«Solo senza gruppo di
costo»** è il vero modo di lavoro — ciò che è assegnato non va rivisto. Ogni riga
mostra l’**origine**:

- *dal file* — arrivata con l’importazione, sovrascrivibile al reimport,
- *manuale* — resta intatta al reimport,
- *proposta* — impostata da una regola.

Un codice non presente nel catalogo viene rifiutato. L’analisi somma per numero;
un numero errato passerebbe altrimenti inosservato.

L’**assegnazione di massa** sovrascrive anche gli inserimenti manuali — chi la
avvia intende proprio questo.

Le **voci suddivise** compaiono con le loro quantità parziali come righe
separate, ciascuna con un proprio campo. Nell’analisi l’assegnazione della
quantità parziale prevale su quella della voce.

## Regole di proposta

**Costruzioni → Regole di assegnazione** registra quale prestazione rientra di
norma in quale gruppo di costo. Due punti di aggancio:

- **Categoria di lavori** — indicata nel file e confrontata per prefisso («013»
  comprende anche «013.2»). La base più affidabile.
- **Parola chiave** nel testo breve o lungo — più debole, ma unica leva quando la
  stazione appaltante non trasmette categorie di lavori.

L’esecuzione delle regole **colma solo le lacune**: le assegnazioni esistenti
restano, qualunque sia l’origine. Se più regole si applicano, vince quella con
la posizione più bassa.

## Analizzare

**Costruzioni → computo metrico → Gruppi di costo** mostra i totali per gruppo,
commutabili tra primo, secondo e terzo livello, con grafico e uscita CSV/Excel.

Tre aspetti contano:

1. **Le quantità parziali prevalgono sulla voce.** Se una voce è suddivisa
   (300 m³ su GC 310, 150 m³ su GC 320), conta la suddivisione.
2. **La sezione si trasmette** alle voci prive di assegnazione propria.
3. **«Senza assegnazione» compare sempre nella tabella**, anche a 0,00 €. Vi
   finisce anche il residuo delle suddivisioni incomplete. Un’analisi che tace il
   residuo non è verificabile.

Sotto si trova il **monitoraggio dei costi**: importo del computo, varianti,
misurato e residuo. Le varianti contano separatamente dall’importo del computo —
l’uno era a gara, l’altro si è aggiunto. Un misurato superiore alla quantità
prevista dà un **residuo negativo**; viene mostrato, non appianato. Il **budget**
proviene dalla stima dei costi sul progetto (vedi sotto); lo **stato fatturato**
manca volutamente — risiede nel sistema di fatturazione di riferimento.

## Stima dei costi e budget

Il tariffario tedesco HOAI conosce quattro fasi — **stima, calcolo, preventivo,
consuntivo dei costi**. Non si sostituiscono a vicenda: il loro confronto *è* il
controllo dei costi.

Una stima esterna arriva come **X51** e appartiene al **progetto**, non al
singolo computo metrico — un’opera si stima nel suo complesso. Compare poi come
colonna budget nel monitoraggio dei costi; senza progetto resta vuota, perché un
budget mancante non è un budget pari a zero.

Vengono prodotti solo il **preventivo** (importo del computo più varianti) e il
**consuntivo** (il misurato). Stima e calcolo appartengono alla progettazione; i
parametri necessari qui non sono disponibili.

## Cambiare edizione

Il cambio di norma avviene tramite **Assegna → Cambia edizione** e mostra prima
un’**anteprima**. Vengono convertite solo le corrispondenze univoche; tutto il
resto resta. Le lacune sono l’essenziale — mostrano dove qualcuno deve decidere.
Un codice indovinato sarebbe peggio di quello vecchio.
