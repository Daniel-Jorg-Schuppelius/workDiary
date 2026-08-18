---
title: "Grupos de costes según DIN 276"
topic: boq.cost-groups
version: 1
audience: []
related:
    - boq.overview
---

Las partidas llevan **asignaciones de catálogo**: el grupo de costes indica *en
qué* se gasta el dinero, el capítulo de obra *quién* lo ejecuta. Ambos llegan por
lo general con el archivo del órgano de contratación: en la construcción federal
alemana el StLB-Bau es obligatorio como base del pliego y aporta el grupo de
costes con cada variante de texto.

**El maestro de catálogos viene incluido.** Se entregan los grupos de costes
DIN 276 en las ediciones **2018-12** (tres niveles) y **2008-12** además de los
capítulos StLB: solo números y denominaciones breves, sin texto normativo.

**Ambas ediciones DIN conviven, no se sustituyen.** «310» significa «excavación»
en la edición 2008 y «excavación, movimiento de tierras» en 2018; además la
edición 2018 reorganizó los 200, 500 y 600/700. Una obra en curso sigue contando
según su edición.

## Asignar

**Construcción → presupuesto → Asignar.** El filtro **«Solo sin grupo de
costes»** es el verdadero modo de trabajo: lo asignado no hace falta revisarlo.
Cada fila muestra el **origen**:

- *del archivo* — llegó con la importación y puede sobrescribirse al reimportar,
- *manual* — permanece intacta al reimportar,
- *propuesta* — establecida por una regla.

Un código que no figura en el catálogo se rechaza. El análisis suma por número;
uno erróneo pasaría de otro modo inadvertido.

La **asignación masiva** sobre la selección también sobrescribe lo introducido a
mano: quien la lanza quiere exactamente eso.

Las **partidas repartidas** aparecen con sus cantidades parciales como filas
propias, cada una con su campo. En el análisis la asignación de la cantidad
parcial prevalece sobre la de la partida.

## Reglas de propuesta

**Construcción → Reglas de asignación** recoge qué prestación corresponde
habitualmente a qué grupo de costes. Dos anclajes:

- **Capítulo de obra** — consta en el archivo y se compara por prefijo («013»
  también cubre «013.2»). La base más fiable.
- **Palabra clave** en el texto breve o largo: más débil, pero el único recurso
  cuando el órgano de contratación no envía capítulos.

La ejecución de las reglas **solo cubre huecos**: las asignaciones existentes se
mantienen, sea cual sea su origen. Si concurren varias reglas, gana la de menor
puesto.

## Analizar

**Construcción → presupuesto → Grupos de costes** muestra los totales por grupo,
conmutables entre primer, segundo y tercer nivel, con gráfico y salida
CSV/Excel.

Tres cosas importan:

1. **Las cantidades parciales prevalecen sobre la partida.** Si una partida está
   repartida (300 m³ al GC 310, 150 m³ al GC 320), cuenta el reparto.
2. **El capítulo se hereda** en las partidas sin asignación propia.
3. **«Sin asignar» figura siempre en la tabla**, incluso con 0,00 €. Allí acaba
   también el resto de los repartos incompletos. Un análisis que oculta el resto
   no es verificable.

Debajo está el **seguimiento de costes**: importe del presupuesto, adicionales,
medido y resto. Los adicionales cuentan aparte del importe del presupuesto: uno
salió a licitación, el otro se añadió. Un medido superior a la cantidad prevista
da un **resto negativo**; se muestra, no se suaviza. El **presupuesto** procede
de la estimación de costes del proyecto (véase más abajo); el **estado
facturado** falta a propósito: reside en el sistema de facturación principal.

## Estimación de costes y presupuesto

El arancel alemán HOAI conoce cuatro fases — **estimación, cálculo, presupuesto
y liquidación de costes**. No se sustituyen entre sí: su comparación *es* el
control de costes.

Una estimación externa llega como **X51** y pertenece al **proyecto**, no al
presupuesto de obra concreto: una obra se estima en su conjunto. Después aparece
como columna de presupuesto en el seguimiento de costes; sin proyecto queda
vacía, porque un presupuesto ausente no es un presupuesto de cero.

Solo se generan el **presupuesto de costes** (importe del presupuesto más
adicionales) y la **liquidación de costes** (lo medido). La estimación y el
cálculo proceden de la fase de proyecto; los ratios necesarios no están aquí.

## Cambiar de edición

El cambio de norma se hace en **Asignar → Cambiar edición** y muestra primero una
**vista previa**. Solo se convierten las correspondencias unívocas; el resto
permanece. Los huecos son lo esencial: muestran dónde alguien tiene que decidir.
Un código adivinado sería peor que el antiguo.
