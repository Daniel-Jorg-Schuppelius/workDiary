---
title: "Importación de Kimai"
topic: admin.kimai
version: 1
keywords:
    - Kimai
    - importar tiempos
    - importar hojas de tiempo
    - Kimai CSV
    - API de Kimai
    - migrar desde Kimai
    - reescribir tiempos
    - reasiento
    - asignación de usuarios
    - devolver correcciones
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.clockify
    - admin.toggl
    - finance.open-times
    - admin.organization-settings
---

La página **Importación de Kimai** trae a WorkDiary los registros de tiempo de
la herramienta Kimai, ya sea como exportación CSV subida o directamente a
través de la API de Kimai. Si lo desea, también reescribe en Kimai como hojas
de tiempo los tiempos registrados en WorkDiary y devuelve a Kimai las
correcciones de los tiempos importados. Encontrará la página en el menú del
sistema (el engranaje **Sistema** en la cabecera) en **Plugins** →
**Importación de Kimai**, en cuanto el plugin esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada Kimai. La activación y los
  ajustes solo valen para la organización actual.
- La página y los ajustes están reservados a los administradores. La
  **Bandeja de conciliación**, donde resuelve los casos abiertos, también está
  abierta a contabilidad.
- Para la vía CSV basta con una exportación de hojas de tiempo de Kimai.
- Para la vía API necesita la dirección de una instancia de Kimai 2 y el
  token API de un usuario de Kimai (en Kimai en Perfil → Acceso API). Si deben
  llegar los tiempos de todas las personas, ese usuario necesita en Kimai el
  permiso view_other_timesheet.
- La instancia de Kimai debe ser accesible públicamente. Solo el operador de
  su instalación puede habilitar una instancia de una red interna.

## Configuración

Las credenciales se guardan en la página **Plugins** mediante **Configurar**
en la entrada Kimai:

1. **URL base de Kimai**: la dirección con la que abre Kimai en el navegador,
   sin /api al final.
2. **Token API de Kimai**: el token de Kimai. Se guarda cifrado; un campo
   vacío conserva el valor anterior al guardar.
3. **Consultar los tiempos de todos los usuarios**: activado (por defecto) si
   el usuario del token puede leer los tiempos de otros; de lo contrario solo
   llegan sus propios tiempos.
4. **Franja de sincronización (días)**: hasta dónde mira hacia atrás una
   importación API sin período (30 días por defecto).
5. **Adoptar como facturable**: activado, adopta la marca de facturable de
   Kimai; desactivado, nunca marca como facturables los tiempos importados.
6. **Modo de usuario único** e **Imputar tiempos para el ID de usuario**: solo
   para puestos individuales, véase más abajo.
7. Para el reasiento, **Activar la reescritura**, **ID de actividad de Kimai
   para reasientos** y opcionalmente **Devolución inmediata de los tiempos
   nuevos**; para devolver correcciones, **Devolver las correcciones**.
8. **Guardar**. Con **Probar conexión** en el diálogo comprueba el acceso.
   Sin token, el plugin indica el modo CSV; no es un error.

## Importar tiempos

**Subir CSV:** exporte los tiempos de Kimai como CSV (Kimai → Tiempos →
Exportar → CSV), seleccione el archivo en la página **Importación de Kimai** y
haga clic en **Importar**. WorkDiary reconoce las columnas por la fila de
encabezado, en alemán o en inglés: por ejemplo fecha, desde, hasta o
duración, cliente, proyecto, actividad, descripción, facturable, etiquetas y
correo. Sirven como separador tanto la coma como el punto y coma, y el archivo
puede tener como máximo 20 MB. Las horas se leen como hora local de su
organización.

**Importar directamente desde la API de Kimai:** elija opcionalmente un
período (**Desde**, **Hasta**) y haga clic en **Importar desde la API**. Sin
período, WorkDiary consulta los últimos días según la franja de
sincronización. La importación omite las hojas de tiempo en curso, sin fin.

No existe una importación programada de Kimai: cada importación empieza en
esta página. Después la página indica cuántas entradas se crearon, se
omitieron o quedaron abiertas en la bandeja, y cuántas no pudieron asignarse
a ningún usuario.

## Asignar clientes, proyectos y personas

- **Proyectos:** la importación no crea clientes ni proyectos. Una entrada se
  contabiliza cuando se encuentra su proyecto: mediante una asignación
  memorizada, en la importación API mediante el número de proyecto de Kimai y,
  si no, mediante el mismo nombre de proyecto en el cliente correspondiente.
  Si en los ajustes de la organización está activado **Asignar tiempos a
  proyectos por palabra clave**, una coincidencia inequívoca por palabra clave
  ayuda como último paso.
- **Bandeja de conciliación:** todo lo demás se acumula allí, agrupado por
  cliente, proyecto y actividad. La tarjeta **Bandeja de conciliación** de la
  página muestra el número de grupos abiertos y **A la bandeja de entrada**
  lleva hasta ella. Allí elige el cliente, opcionalmente el cliente final, y
  el proyecto, y contabiliza el grupo. La asignación se memoriza; las
  importaciones siguientes contabilizan entonces sin preguntar.
- **Personas:** cada tiempo pertenece a la persona que lo registró en Kimai.
  La importación CSV usa la columna de correo y la importación API el nombre
  de usuario de Kimai. WorkDiary compara uno u otro con la dirección de correo
  de los usuarios activos. Sin coincidencia se crea en la bandeja un caso
  «Usuario desconocido» o «Entrada sin señal de usuario», en lugar de que el
  tiempo acabe sin aviso en el usuario principal. Elija allí el usuario; la
  elección se memoriza.
- **Modo de usuario único:** solo si está activado, la importación asigna las
  entradas sin persona identificable al usuario predeterminado. Es el usuario
  de **Imputar tiempos para el ID de usuario** o, si no, el propietario de la
  organización o el primer usuario.

## Nueva importación y cambios

- Una nueva importación nunca crea dos veces entradas ya importadas.
- En la importación API, WorkDiary reconoce cada entrada por su número de
  Kimai. Si una entrada conocida cambió en Kimai (inicio, fin, duración,
  descripción), WorkDiary adopta el cambio. Si el tiempo ya está facturado o
  exportado aquí, WorkDiary no cambia nada; el caso aparece en la bandeja a
  título informativo.
- Si una importación API con **Consultar los tiempos de todos los usuarios**
  ya no encuentra, en el período consultado, una entrada importada
  anteriormente, esta se considera eliminada en Kimai. WorkDiary elimina
  entonces también el tiempo, salvo que esté facturado; en ese caso se crea un
  caso en la bandeja.
- En la vía CSV, WorkDiary reconoce una entrada por su hora, cliente,
  proyecto, actividad, descripción y correo. Si se modificó en Kimai, volver a
  subirla crea una entrada adicional. Las importaciones CSV no provocan
  eliminaciones. Para una sincronización continua es más adecuada la vía API.
- Las etiquetas de Kimai se añaden, nunca se quitan.

## Reescribir los tiempos en Kimai

La sección **Reescribir los tiempos en Kimai** aparece en cuanto hay un acceso
API guardado y **Activar la reescritura** está activado.

- Se reescriben los tiempos registrados en WorkDiary con inicio y fin que aún
  no se hayan exportado y cuyo proyecto esté vinculado a un proyecto de
  Kimai. Ese vínculo surge de la importación API, para los proyectos
  encontrados automáticamente y para los grupos API que contabiliza en la
  bandeja. Una importación CSV no lo aporta.
- WorkDiary nunca reescribe tiempos importados de Kimai.
- Kimai exige una actividad por cada hoja de tiempo: el **ID de actividad de
  Kimai para reasientos**, es decir, el número de la actividad en Kimai, se
  aplica a todos los tiempos reescritos. La descripción y la marca de
  facturable se transmiten.
- **Exportar a Kimai** inicia la reescritura tras una confirmación,
  opcionalmente para un período. El mensaje indica las entradas
  contabilizadas, omitidas y fallidas.
- Un tiempo reescrito cuenta en WorkDiary como exportado: deja de aparecer en
  **Tiempos abiertos** y ya no se factura aquí. Con **Devolución inmediata de
  los tiempos nuevos** esto ocurre ya al registrarlo, sin posibilidad de
  corrección.

## Devolver las correcciones

Con **Devolver las correcciones**, WorkDiary transmite a Kimai los cambios de
los tiempos importados por la API (descripción, inicio, fin, duración,
facturable) y su eliminación. Antes, WorkDiary compara el estado actual en
Kimai: si la entrada se modificó allí mientras tanto, WorkDiary no sobrescribe
nada y crea en su lugar un conflicto en la bandeja. Los tiempos facturados y
los importados por CSV nunca se devuelven. La transferencia se ejecuta en
segundo plano y se repite si hay errores.

## Errores frecuentes

- **No hay acceso API registrado** en lugar de la sección de importación:
  faltan la URL base o el token en los ajustes del plugin.
- «No hay ID de actividad de Kimai registrado — reasiento no posible.»:
  introduzca el número de una actividad de Kimai.
- «Ningún proyecto está asignado a un proyecto de Kimai»: ejecute primero una
  importación API o contabilice los grupos API en la bandeja.
- Muchos casos «Usuario desconocido»: los nombres de usuario de Kimai o la
  columna de correo no coinciden con las direcciones de correo de WorkDiary.
  Asigne cada persona una vez en la bandeja.
- Solo llegan los tiempos del usuario del token: le falta en Kimai el permiso
  view_other_timesheet, o **Consultar los tiempos de todos los usuarios** está
  desactivado.
- La importación CSV no crea nada: la fila de encabezado necesita al menos una
  fecha y una hora de fin o una duración.
- La API no es accesible: introduzca la dirección sin /api, compruebe el token
  y utilice **Probar conexión**. Si la instancia está en una red interna,
  WorkDiary indica una dirección privada. Si los errores se acumulan,
  WorkDiary desactiva el plugin automáticamente; una vez corregida la causa,
  restablézcalo en la página **Plugins** con **Restablecer y reactivar**.
