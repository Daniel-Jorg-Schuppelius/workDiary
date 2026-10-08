---
title: "Importar la lista de llamadas de FRITZ!Box"
topic: admin.fritzbox
version: 1
keywords:
    - FRITZ!Box
    - lista de llamadas
    - imputar llamadas
    - llamadas como tiempo
    - informe telefónico
    - fichaje telefónico
    - fichar con una llamada
    - importación CSV de llamadas
    - AVM
    - asignar número de teléfono
    - facturar tiempo al teléfono
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - time-entries.edit
    - attendance.manage
    - contacts.manage
    - foreign-customers
---

La página **Importación FRITZ!Box** convierte las llamadas de la lista de
llamadas de una FRITZ!Box en registros de tiempo. WorkDiary imputa por sí mismo
las llamadas de clientes y clientes finales conocidos; si una llamada se solapa
con un tiempo ya registrado para el mismo cliente, por ejemplo una asistencia
remota, se fusiona con ese tiempo en lugar de facturarse dos veces. Los números
desconocidos se reúnen en la **Bandeja de conciliación**. Además, los empleados
pueden fichar la entrada y la salida llamando a uno de sus propios números.

Para ello WorkDiary no se conecta con la FRITZ!Box. Lee la lista de llamadas
exportada, como archivo subido o como informe telefónico por correo. No
necesita credenciales del router.

## Requisitos previos

- El plugin **FRITZ!Box-Anrufliste** está activado en **Plugins**. A
  continuación aparece la entrada **Importación FRITZ!Box** en el menú del
  sistema (icono de engranaje **Sistema**), dentro del grupo **Plugins**.
- Los números de teléfono de sus clientes y clientes finales están guardados en
  sus datos maestros (teléfono o móvil). Así reconoce WorkDiary a quien llama.
- La página está reservada a los administradores de su organización; la
  **Bandeja de conciliación**, a las personas autorizadas a gestionar la
  facturación.

## Ajustes del plugin

En **Plugins**, abra el diálogo **Configurar** de **FRITZ!Box-Anrufliste**:

- **Registrar las llamadas como facturables** (por defecto: activado): si se
  desactiva, las llamadas importadas nunca se marcan como facturables.
- **Imputar tiempos para el ID de usuario**: el identificador (ID) del usuario
  al que se imputan las llamadas. Si queda vacío, WorkDiary imputa al titular
  de la organización o al primer usuario.
- **Duración mínima (minutos)** (por defecto: 2): las llamadas más cortas se
  omiten.
- **Ventana previa (minutos)** (por defecto: 15): si una llamada termina como
  máximo esos minutos antes de un tiempo registrado del mismo cliente, se
  fusiona con él.
- **Solo números propios**: lista separada por comas de sus números propios
  cuyas llamadas deben importarse, por ejemplo solo la línea principal de la
  empresa. Vacío importa todas. Escriba los números exactamente como aparecen en
  la columna «Eigene Rufnummer» (número propio) de la lista de llamadas.
- **Tratar el tipo 3 como saliente**: solo para listas de versiones antiguas de
  FRITZ!OS, que exportan las llamadas salientes como tipo 3.
- **Comparar contactos externos** (por defecto: activado): los números
  desconocidos se comparan también con directorios de contactos conectados,
  como Lexoffice y Microsoft 365.
- **Número de fichaje: entrada**, **Número de fichaje: salida** y **Número de
  fichaje: entrada/salida**: sus números propios para el fichaje telefónico
  (véase más abajo).

## Subir la lista de llamadas

1. Exporte la lista de llamadas en la FRITZ!Box: FRITZ!Box → Telefonía →
   Llamadas → Guardar (CSV).
2. En la página, elija el archivo en la sección **Subir la lista de llamadas**
   (extensión .csv o .txt, 20 MB como máximo) y haga clic en **Importar**.
3. Un mensaje resume el resultado: imputadas, fusionadas, fichadas, abiertas
   (bandeja), omitidas, filtradas y bloqueadas.

Puede volver a subir la misma lista sin riesgo: WorkDiary omite las llamadas ya
importadas.

El recuadro **Comparación de contactos** muestra qué fuentes de contactos
externas están conectadas en este momento. Sin fuente externa, WorkDiary sigue
comparando con sus clientes y clientes finales.

## Informe telefónico por correo

En lugar de subir el archivo, la FRITZ!Box puede enviar su lista de llamadas
como informe telefónico por correo. Para ello configure en **Recepción de
correo** un buzón que reciba esos mensajes y active allí **Buzón de informes
telefónicos: transferir las listas de llamadas de FRITZ!Box (CSV) a la
importación de la lista de llamadas**. La recepción de correo consulta los
buzones cada cinco minutos de forma predeterminada; las listas de llamadas
reconocidas pasan por la misma importación que una subida. Los informes
entregados dos veces no generan imputaciones duplicadas. Cuando hay un buzón
así conectado, la comprobación de estado del plugin indica «Listo — recepción
del informe telefónico por correo conectada.»

## Qué ocurre con cada llamada

- **Filtradas:** llamadas con número oculto, llamadas perdidas y rechazadas,
  llamadas a través de números propios que no figuran en **Solo números
  propios**, así como números ignorados en la bandeja.
- **Omitidas:** llamadas ya importadas y llamadas por debajo de la duración
  mínima. Si reduce la duración mínima, una nueva importación recupera esas
  llamadas.
- **Fusionadas:** con un número conocido, WorkDiary busca un tiempo registrado
  del mismo usuario para el mismo cliente que la llamada solape o que empiece
  como muy tarde dentro de la ventana previa tras la llamada. La llamada se
  adjunta a ese tiempo como justificante y su inicio se adelanta al inicio de
  la llamada.
- **Imputadas:** si no existe ese tiempo, se crea un registro de tiempo propio
  en el proyecto predeterminado del cliente o cliente final (se crea si hace
  falta). La descripción indica dirección, nombre y número; si es facturable
  depende del ajuste.
- **Bloqueadas:** si la llamada cae en un mes cerrado, WorkDiary no crea ningún
  registro. Los tiempos ya exportados nunca se modifican.
- **Abiertas (bandeja):** los números desconocidos y los marcados como
  compartidos van a la **Bandeja de conciliación**.

En el reconocimiento tienen prioridad los números recordados, seguidos de los
datos maestros; si un número coincide con un cliente final, este gana como
destino más preciso. Si en un directorio de contactos conectado un número ya
está asignado a un cliente, WorkDiary imputa directamente.

## Asignar números desconocidos

La sección **Bandeja de conciliación** indica el número de grupos de
importación abiertos; **A la bandeja de entrada** lleva allí. Las llamadas de un
mismo número aparecen como grupo, a menudo ya con un cliente propuesto:

- Elija un cliente o cliente final y haga clic en **Asignar e imputar**. Todas
  las llamadas del grupo se imputan con las mismas reglas que en la
  importación. Con **Recordar el número de forma permanente** (preseleccionado)
  las llamadas futuras de ese número pasan sin preguntar.
- **Número compartido** está pensado para números a través de los cuales
  llaman varios clientes, por ejemplo la línea de atención de un proveedor de
  servicios. Las llamadas futuras de ese número llegan una a una a la bandeja
  para asignarlas y nunca se imputan automáticamente.
- **Ignorar número** excluye el número de forma permanente, por ejemplo para
  llamadas privadas; las llamadas futuras ya no se importan.
- **Descartar grupo** descarta solo las llamadas mostradas. No vuelven ni
  siquiera con una nueva importación; las llamadas nuevas del número vuelven a
  aparecer.

## Fichaje telefónico

Así fichan los empleados por teléfono:

1. En los ajustes del plugin, introduzca uno o varios de sus números propios
   como **Número de fichaje: entrada**, **Número de fichaje: salida** o
   **Número de fichaje: entrada/salida**, exactamente como aparecen en la lista
   de llamadas. La sección **Fichaje telefónico** muestra después los números de
   fichaje activos.
2. En la sección **Fichaje telefónico**, asigne a cada empleado su número:
   elija en **Empleados**, introduzca el **Número de teléfono** (por ejemplo
   +49 151 2345678) y haga clic en **Asignar**. Sin prefijo de país se aplica
   Alemania. La tabla muestra todas las asignaciones; **Eliminar** quita una.
3. El empleado llama al número de fichaje. No hace falta atender la llamada: el
   número de quien llama sirve de identificación.
4. En la siguiente importación de la lista de llamadas, la llamada se convierte
   en un fichaje de entrada o de salida a la hora de la llamada. Para
   **entrada/salida** rige: si hay un fichaje abierto, se ficha la salida; si
   no, la entrada.

Límites: el fichaje solo se produce durante la importación, no en el momento de
la llamada. Las llamadas salientes, los números ocultos y los no asignados se
ignoran, igual que una salida sin entrada abierta. La duración mínima no se
aplica aquí. Las llamadas a un número de fichaje nunca se imputan como llamada
telefónica.

## Problemas habituales

- **Archivo rechazado:** si la importación indica que no se reconoció ninguna
  lista de llamadas de FRITZ!Box (archivo vacío o falta la fila de
  encabezado), use la exportación CSV de la lista de llamadas sin modificarla.
- **«No hay ningún usuario al que registrar tiempos en la organización.»** o
  una comprobación de estado que indica que el usuario predeterminado
  configurado ya no existe: revise **Imputar tiempos para el ID de usuario** o
  vacíe el campo.
- **Faltan llamadas salientes:** si la lista procede de un firmware antiguo,
  active **Tratar el tipo 3 como saliente**.
- **Casi todo filtrado:** revise **Solo números propios**; la escritura debe
  coincidir exactamente con la lista de llamadas.
- **Muchos resultados bloqueados:** el mes ya está cerrado; las llamadas de ese
  periodo ya no se imputan.
- **Falta un fichaje:** ¿está asignado el número del empleado y se transmitió
  durante la llamada? ¿Coincide el número de fichaje de los ajustes con la
  lista de llamadas?
