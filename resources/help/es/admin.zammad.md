---
title: "Conexión con Zammad"
topic: admin.zammad
version: 1
keywords:
    - Zammad
    - helpdesk
    - importar tickets
    - sistema de tickets
    - tickets como tareas
    - asignar cola
    - ID de grupo
    - webhook
    - cerrar ticket
    - retorno de estado
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - helpdesk.overview
    - admin.data-ownership
    - admin.scheduler
    - projects.manage
---

La página **Zammad** trae a WorkDiary, como tareas, los tickets del sistema de
tickets Zammad, para que pueda registrar tiempos, llevar justificantes y
facturar allí. Zammad sigue siendo el sistema de referencia; volver a importar
nunca crea duplicados. Opcionalmente, WorkDiary comunica al ticket las tareas
completadas. Encontrará la página en el menú del sistema (el engranaje
**Sistema** en la cabecera) en **Plugins** → **Zammad**, en cuanto el plugin
esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada Zammad. La conexión en sí se
  configura en la página **Zammad**, no en el diálogo del plugin.
- La página está reservada a los administradores.
- Necesita la dirección de su instancia de Zammad y un token de API (en Zammad
  en Perfil → Acceso por token). El token debe poder leer los tickets y, si
  usa el retorno de estado, también modificarlos.
- La instancia debe ser accesible públicamente. WorkDiary rechaza las
  direcciones de una red interna.
- Por organización existe exactamente una conexión con Zammad.

## Configurar la conexión

En la sección **Conexión** rellena:

- **Etiqueta**: un nombre a su elección.
- **URL de la instancia**: la dirección con la que abre Zammad en el
  navegador. Debe empezar por http:// o https://.
- **Token de API**: obligatorio al guardar por primera vez. Se guarda cifrado;
  más adelante, un campo vacío conserva el token guardado.
- **Secreto del webhook (opcional)**: secreto compartido para las llamadas
  webhook desde Zammad que inician la importación de inmediato. Al guardar, un campo vacío
  conserva el secreto guardado. La página no muestra la
  dirección del webhook; sin webhook, la consulta periódica obtiene los
  tickets.
- **Proyecto predeterminado**: destino de los tickets cuyo grupo no está
  asignado a un proyecto. **— sin proyecto (global) —** los crea como tareas
  globales sin proyecto.
- **Retorno de estado (estado objetivo)**: opcional, véase más abajo.
- **Activo**: activa o desactiva la conexión.

**Guardar** aplica los datos. Cuando la conexión está activa, la página
muestra su estado (por ejemplo **Estado correcto**) y **Probar conexión**.

## Cola → proyecto

En **Cola → proyecto** asigna grupos de Zammad a un proyecto de WorkDiary: a
la izquierda el **ID de grupo** de Zammad, a la derecha el proyecto. Siempre
hay tres filas libres; para más grupos, guarde y después introdúzcalos.
WorkDiary descarta al guardar las filas sin ID de grupo o sin proyecto. La
selección de proyectos muestra como máximo 500 proyectos.

Un ticket llega al proyecto de su grupo; si no, al **Proyecto
predeterminado**; si no, como tarea global.

## Importación y programación

- Cada 15 minutos, WorkDiary consulta los tickets de Zammad. La frecuencia se
  cambia en **Tareas programadas**.
- **Importar ahora** inicia una importación en segundo plano.
- Con un secreto de webhook, un webhook de Zammad inicia además la
  importación de inmediato. Si falla, la consulta periódica lo recupera.
- WorkDiary obtiene los tickets que el token de API puede ver, no solo los de
  los grupos asignados.
- Cada ticket se convierte en tarea una sola vez. Su título es el número y el
  título del ticket, y es facturable. Los tickets cerrados o fusionados llegan
  como tareas completadas.
- WorkDiary no adopta cambios posteriores del ticket (título, estado, grupo);
  la tarea queda como se creó.
- Si, según la **Titularidad de los datos**, otro sistema dirige las tareas,
  WorkDiary no crea una tarea sino un caso en la Bandeja de conciliación.

## Reconocer clientes

Si un ticket contiene el correo de un cliente o una organización, WorkDiary
busca el cliente correspondiente. Con una coincidencia inequívoca, mueve la
tarea a un proyecto de ese cliente, preferiblemente a su proyecto
predeterminado. Si no, se crea una sugerencia en la Bandeja de conciliación,
donde confirma o elige el cliente.

## Retorno de estado

Introduzca un estado de Zammad en **Retorno de estado (estado objetivo)**, por
ejemplo closed. Cuando alguien pone una tarea vinculada en **Hecho** en
WorkDiary, WorkDiary pone el ticket en ese estado y añade una nota interna
«Resuelto en WorkDiary.». La transferencia se ejecuta en segundo plano y se
repite si hay errores. Un campo vacío desactiva el retorno. WorkDiary no
escribe otros datos en Zammad.

## Límites

- Una ejecución solo obtiene la primera página de la lista de tickets, como
  máximo 100 tickets.
- **Desconectar** solo desactiva la conexión. Las tareas y los vínculos se
  conservan, y en Zammad no cambia nada. Para volver a activarla, marque
  **Activo** y guarde.
- Las tareas nunca se eliminan, aunque el ticket desaparezca en Zammad.

## Errores frecuentes

- «La URL de la instancia debe empezar por http:// o https://.»: introduzca
  la dirección completa.
- «Una conexión nueva requiere un token de API.»: falta el token al guardar
  por primera vez.
- «No hay ninguna conexión de Zammad activa.» con **Importar ahora**: la
  conexión está desactivada o incompleta.
- **Estado defectuoso** con «API de Zammad no accesible o token no válido.»:
  compruebe la dirección y el token. Un error de la API de Zammad con
  RuntimeException suele indicar una dirección de una red interna.
- Los tickets llegan al proyecto equivocado: compruebe los ID de grupo en
  **Cola → proyecto** y las sugerencias de cliente en la bandeja.
