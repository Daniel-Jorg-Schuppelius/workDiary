---
title: "Gestión ágil de proyectos"
topic: agile.overview
version: 1
audience: []
related:
    - projects.manage
    - work.overview
---

Para cada proyecto puede activarse un tablero ágil — a elección para
trabajo continuo (Kanban) o para sprints (Scrum). Principio básico: la
tarea sigue siendo la entidad rectora. Cada tarjeta del tablero es una
tarea normal con responsables, registros de tiempo y vínculos; el módulo
ágil añade rango, story points y asignación a sprint, pero no sustituye
nada. Los story points nunca se convierten deliberadamente en tiempo ni
en dinero — el tiempo registrado y los puntos figuran por separado, uno
junto al otro, en la tarjeta.

**Backlog de producto:** Por proyecto, una lista ordenada por rango de
todos los elementos de trabajo. Los elementos nuevos se crean
directamente en el backlog o incorporando tareas existentes. Cada
elemento lleva un tipo (como historia o error), story points, criterios
de aceptación y, si es necesario, una marca de bloqueado. El orden se
mantiene desplazando hacia arriba/abajo; los filtros por tipo, bloqueos
y término de búsqueda mantienen manejables incluso los backlogs
grandes.

**Tablero:** Las columnas representan el flujo de trabajo; las tarjetas
avanzan de izquierda a derecha. Con un sprint seleccionado, el tablero
muestra exclusivamente los elementos de ese sprint.

**Sprints:** Planificación asignando elementos del backlog; después,
inicio, cierre o cancelación. Al cerrar, cada elemento sin terminar
exige una decisión explícita — de vuelta al backlog o al sprint
siguiente. Nada se traslada de forma tácita.

**Protección contra conflictos:** Los cambios de rango, los ajustes del
tablero y las acciones de sprint están protegidos contra la edición en
paralelo: el primer guardado gana, la segunda persona recibe un aviso y
retoma sobre el estado actual. No existe la sobrescritura silenciosa.

**Historial:** Cada cambio relevante — rango, columna, asignación a
sprint, puntos — se registra como evento ágil y puede consultarse como
historial.

**Informes:** El burndown muestra para un sprint en curso la serie
diaria de puntos restantes; la velocity, los puntos completados por
sprint cerrado de un tablero. Una vista de gestión resume todos los
tableros: sprint activo, mediana de velocity, trabajo no planificado,
bloqueos y una previsión empírica — esta última solo aparece cuando
existen suficientes semanas comparables, en lugar de fingir una
precisión aparente. La vista muestra solo los proyectos que la persona
que la consulta puede ver de todos modos.
