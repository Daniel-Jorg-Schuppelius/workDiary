---
title: "Colecciones"
topic: knowledge.collections
version: 6
audience: []
related:
    - knowledge.articles
    - communication.notes
    - ideas.overview
    - documents.manage
    - learning.overview
---

Una **colección** ordena contenidos entre módulos: notas, mapas de ideas,
artículos de conocimiento, documentos, cursos y rutas de aprendizaje pueden
estar juntos en una colección. La pertenencia a un cliente, encargo o proyecto
sigue siendo lo principal; la colección es el orden adicional y libre para todo
lo que no pertenece a un único caso.

Procedimiento habitual:

1. Crea una colección desde la entrada **Conocimiento**, con **Gestionar
   colecciones**, si quieres como subcolección de otra.
   Las colecciones se anidan hasta cinco niveles y se pueden mover después.
2. En la página de detalle de un contenido elige **Añadir a colección**. Un
   contenido puede estar en varias colecciones; no se crea ninguna copia.
3. **Archiva** las colecciones que ya no necesites en lugar de borrarlas: las
   asignaciones se conservan y se pueden restaurar.

**Una colección no concede acceso.** Cada persona solo ve lo que puede ver de
todos modos: las notas confidenciales de otros, los mapas de ideas no
compartidos, los documentos confidenciales y los contenidos de aprendizaje sin
el módulo de la plataforma permanecen ocultos, sin mostrar siquiera su número.
Una colección **privada** solo la ve quien la creó.

## La entrada «Conocimiento»

La página **Conocimiento** muestra notas, mapas de ideas, artículos de
conocimiento, documentos, cursos y rutas de aprendizaje en una lista, o en
mosaico. A la izquierda está el árbol de colecciones (una colección incluye sus
subcolecciones); arriba filtran el título, el tipo y el cliente, y las
etiquetas acotan aún más.

**Conocimiento** es la única puerta al área: notas, base de conocimiento, mapas
de ideas y documentos cuelgan encima como pestañas, y las colecciones son su
modo de gestión. Cada pestaña conserva sus columnas y acciones propias: los
plazos y la publicación siguen en los documentos, la publicación de artículos
sigue en la base de conocimiento.

El cambio conserva el filtro: si eliges un cliente y pasas a **Documentos**,
ves sus documentos. Solo viaja lo que la pestaña puede aplicar: un artículo
no pertenece a ningún cliente, así que allí la selección se queda fuera.

Marca varios contenidos y pulsa **Añadir** para ponerlos a la vez en una
colección; lo mismo funciona en los resultados de la **Búsqueda** para notas,
artículos de conocimiento y cursos.

## Convertir una nota en artículo de conocimiento

En el diálogo de lectura de una nota, **Convertir en artículo de conocimiento**
crea un borrador en la base de conocimiento: el asunto pasa a ser el título, el
texto la descripción del problema y las etiquetas se conservan. El artículo
muestra la nota como origen en «Mencionado en»; un segundo clic abre el artículo
existente en lugar de crear otro. Las notas confidenciales no se pueden
convertir.

## Importar de Obsidian y OneNote

Los administradores importan notas existentes **una vez o bajo demanda**: solo
lectura, sin escribir de vuelta y sin sincronización continua. La entrada
**Conocimiento** ofrece dos botones:

- **Importar Obsidian** lee un almacén de Obsidian mediante una conexión de
  carpeta existente de la entrada de documentos en la nube (Nextcloud, OneDrive,
  Dropbox, Google Drive). Indica la ruta del almacén relativa a la carpeta raíz
  de la conexión. Las subcarpetas pasan a ser colecciones, las etiquetas del
  encabezado YAML y las `#etiquetas` del texto se conservan, y los `[[wikilinks]]`
  pasan a ser referencias. `.obsidian/` y `.trash/` quedan fuera.
- **Importar OneNote** solo aparece cuando la organización ha activado
  **Permitir importación de OneNote** en los ajustes del plugin de Microsoft 365
  y ha usado **Conectar OneNote** en el panel de Microsoft 365. La conexión
  solicita el permiso adicional de solo lectura Notes.Read. El bloc de notas
  pasa a ser una colección, los grupos de secciones y las secciones
  subcolecciones, y cada página una nota o un artículo; el contenido se importa
  como texto.

Se importa como nota o como borrador de artículo de conocimiento. Cada contenido
importado muestra su origen («Importado de …»). Otra ejecución omite lo que ya
existe e importa solo lo nuevo: como máximo 300 contenidos nuevos por ejecución.

## Referencias y retroenlaces

En las páginas de detalle de estos contenidos, la tarjeta **Referencias** muestra
a qué remite un contenido y dónde se menciona:

- **Añadir referencia** une el contenido con una nota, un mapa de ideas, un
  artículo de conocimiento, un documento, un curso o una ruta de aprendizaje.
  Puedes acotar la lista con el campo de búsqueda.
- **Mencionado en** lista, agrupado por tipo, todo lo que apunta a la página,
  también los vínculos de la base de conocimiento y los destinos convertidos o
  vinculados desde nodos de ideas. Clientes, proyectos y pedidos muestran esta
  lista en cuanto algo remite a ellos.
- **Quitar referencia** solo elimina las referencias añadidas a mano; los
  vínculos de la base de conocimiento y de los mapas de ideas se gestionan allí.

Como con las colecciones, una referencia no concede acceso: una fuente solo
aparece si puedes abrirla de todos modos.

Para crear y llenar colecciones y añadir referencias hace falta el permiso
«Gestionar colecciones y referencias»; para ver las colecciones, «Ver
colecciones».
