---
title: "Mapas de ideas"
topic: ideas.overview
version: 1
audience: []
related:
    - work.overview
    - projects.manage
---

Los mapas de ideas son lienzos de forma libre compuestos de nodos y
conexiones — para recopilar, ordenar y desarrollar pensamientos antes de
que se conviertan en trabajo concreto. Cada mapa puede asignarse
opcionalmente a un cliente o proyecto y crece desde la lluvia de ideas
suelta hasta el esquema estructurado.

**Privado como estado inicial:** Un mapa nuevo lo ve exclusivamente la
persona que lo creó. Solo se hace visible para otros mediante una
compartición expresa con personas concretas — a elección para lectura o
para coedición. Deliberadamente no existe un acceso directo de
administrador: también los administradores ven los mapas ajenos solo
tras la compartición. Si una persona abandona la organización, la
propiedad de un mapa puede transferirse de forma dirigida para que nada
se pierda.

**Edición con protección contra conflictos:** Crear, renombrar, mover,
conectar y eliminar nodos — individualmente o el mapa completo de una
vez. Si varias personas trabajan a la vez, gana el primer guardado; la
segunda persona recibe un aviso con el estado actual y puede rehacer su
cambio sobre él. No existe la sobrescritura silenciosa. Quién está
colaborando en ese momento es visible como presencia, y el historial de
cambios registra qué cambió y cuándo.

**Ciclo de vida:** Los mapas terminados pueden archivarse; los mapas y
nodos eliminados pueden restaurarse desde la papelera.

**Conversión en trabajo:** Un nodo maduro se convierte con un clic en
una tarea, un proyecto o un artículo de conocimiento. La conversión es
idempotente: si se vuelve a ejecutar para el mismo nodo y tipo de
destino, no se crea ningún duplicado — queda exactamente un destino, y
el vínculo permanece visible de forma permanente en el nodo. Así queda
trazable qué idea condujo a qué trabajo.

**Importación y exportación:** Los mapas mentales existentes pueden
importarse desde archivos FreeMind u OPML; los mapas pueden exportarse
como JSON, OPML, Markdown o PDF — por ejemplo para compartirlos fuera
del sistema.
