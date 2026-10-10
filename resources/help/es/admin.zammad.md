---
title: "Conexión con Zammad"
topic: admin.zammad
version: 3
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
    - imputación de tiempo en el ticket
    - solo grupos asignados
    - tickets de servicio
    - destino de los tickets
    - direcciones privadas
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
completadas e imputa en él los tiempos registrados. Encontrará la página en el menú del sistema (el engranaje
**Sistema** en la cabecera) en **Plugins** → **Zammad**, en cuanto el plugin
esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada Zammad. La conexión en sí se
  configura en la página **Zammad**, no en el diálogo del plugin.
- La página está reservada a los administradores.
- Necesita la dirección de su instancia de Zammad y un token de API (en Zammad
  en Perfil → Acceso por token). El token debe poder leer los tickets y, si
  usa el retorno de estado o la imputación de tiempo, también modificarlos.
- La instancia debe ser accesible públicamente. Si Zammad está en su propia
  red, active **Permitir direcciones privadas/internas** (véase más abajo).
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
  conserva el secreto guardado. Una vez guardada la conexión, debajo del
  campo aparece la **Dirección del webhook**: introdúzcala en Zammad en
  Webhook como punto de conexión, con el secreto como token de firma HMAC
  SHA1, y active el webhook mediante un disparador. Sin webhook, la consulta
  periódica obtiene los tickets.
- **Proyecto predeterminado**: destino de los tickets cuyo grupo no está
  asignado a un proyecto. **— sin proyecto (global) —** los crea como tareas
  globales sin proyecto.
- **Retorno de estado (estado objetivo)**: opcional, véase más abajo.
- **Imputación de tiempo en el ticket**: opcional, véase más abajo.
- **Permitir direcciones privadas/internas**: actívelo solo si Zammad está en
  su propia red (por ejemplo 192.168.x.x). Sin este interruptor, WorkDiary
  rechaza las direcciones internas ya al guardar. La activación queda
  registrada. Si el operador de su instalación ha bloqueado esta
  autorización, el interruptor no tiene efecto.
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

**Solo grupos asignados** (desactivado de forma predeterminada) limita la
importación: si está activado, WorkDiary solo crea tareas para los tickets de
los grupos asignados aquí; el **Proyecto predeterminado** deja de aplicarse.
Si está desactivado, llegan todos los tickets que ve el token de API. Con el
interruptor activado y sin ninguna asignación, WorkDiary no importa nada.

## Importación y programación

- Cada 15 minutos, WorkDiary consulta los tickets de Zammad. La frecuencia se
  cambia en **Tareas programadas**.
- **Importar ahora** inicia una importación en segundo plano.
- Con un secreto de webhook, un webhook de Zammad inicia además la
  importación de inmediato. Si falla, la consulta periódica lo recupera.
- WorkDiary obtiene todos los tickets que el token de API puede ver; con
  **Solo grupos asignados**, las tareas solo surgen de los grupos asignados.
  Cada ejecución lee la lista completa de tickets, página a página.
- Cada ticket abierto se convierte en tarea una sola vez. Su título es el
  número y el título del ticket, y es facturable. Los tickets cerrados o
  fusionados que WorkDiary aún no conoce no se recuperan.
- Si un ticket ya vinculado se cierra o se fusiona en Zammad, WorkDiary pone
  la tarea en **Hecho**, sin comunicarlo al ticket. Si después vuelve a abrir
  la tarea, sigue abierta. Un ticket reabierto en Zammad no cambia la tarea.
- WorkDiary no adopta los cambios de título o de grupo del ticket.
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
repite si hay errores. Un campo vacío desactiva el retorno. Aparte del estado,
la nota y, con la imputación de tiempo, los tiempos, WorkDiary no escribe
nada en Zammad.

## Imputación de tiempo en el ticket

En **Imputación de tiempo en el ticket**, elija la unidad en la que su Zammad
registra los tiempos: **Minutos** u **Horas**, según la unidad de registro de
tiempo de Zammad. Cuando alguien registra en WorkDiary un tiempo en una tarea
vinculada, WorkDiary lo imputa en el ticket como registro de tiempo; en horas,
redondeado a dos decimales. Cada tiempo se imputa como máximo una vez. La
transferencia se ejecuta en segundo plano y se repite si hay errores.
WorkDiary no transfiere los tiempos modificados o eliminados más tarde.
**Desactivado** apaga la imputación.

## Destino de los tickets

En la sección **Destino de los tickets**, **Actualmente** indica cómo llegan
los tickets nuevos: como **Tareas** (predeterminado) o como **Tickets de
servicio** de una cola. Para cambiarlo, elija el destino en **Tickets nuevos
como**, para tickets de servicio también la **Cola**, y haga clic en
**Cambiar destino**; WorkDiary pide confirmación antes.

- Los tickets de servicio requieren el módulo Helpdesk. La cola se crea en
  **Service desk** → **Colas**. Después, Zammad gestiona los tickets de esa
  cola.
- Los tickets ya importados permanecen donde están.
- Si hay conflictos abiertos de titularidad de los datos en la Bandeja de
  conciliación, WorkDiary rechaza el cambio hasta que se resuelvan.
- Cada cambio queda registrado.
- Los tickets de servicio no reciben sugerencia de cliente, retorno de estado
  ni imputación de tiempo; eso solo se aplica a las tareas.

## Límites

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
- «La URL de la instancia apunta a una dirección privada/interna.»: si Zammad
  está en su propia red, active **Permitir direcciones privadas/internas**. Si
  el operador ha bloqueado esta autorización, la instancia necesita una
  dirección accesible públicamente.
- **Estado defectuoso** con «API de Zammad no accesible o token no válido.»:
  compruebe la dirección y el token. Un error de la API de Zammad con
  RuntimeException suele indicar una dirección de una red interna sin
  autorización.
- «Elija una cola.» con **Cambiar destino**: falta la cola para los tickets
  de servicio.
- Los tickets llegan al proyecto equivocado: compruebe los ID de grupo en
  **Cola → proyecto** y las sugerencias de cliente en la bandeja.
