---
title: "Resumen de la gestión de protección de datos"
topic: privacy.overview
version: 2
keywords:
    - RGPD
    - registro de actividades de tratamiento
    - encargado del tratamiento
    - contrato de encargo
    - medidas técnicas y organizativas
    - derechos ARCO
    - solicitud de acceso
    - brecha de seguridad
    - notificación en 72 horas
    - política de conservación
    - retención legal
audience: []
modules:
    - module.datenschutz
related:
    - documents.manage
    - isms.overview
    - glossary.core
    - privacy.portal
---

El módulo de protección de datos cubre el registro de actividades de
tratamiento (art. 30 RGPD) con versiones inmutables tras la aprobación,
encargados y contratos (art. 28), solicitudes de interesados
(art. 15–21) con plazo de 30 días, medidas técnicas y organizativas y
brechas de seguridad con vista al plazo de 72 horas; la notificación a la autoridad y la comunicación a los
interesados (art. 34) se registran por separado. Los contenidos de
las solicitudes se guardan **cifrados** con una clave propia por caso y
no existe acceso automático para administradores: los derechos se
asignan expresamente. Tras el plazo de conservación, la clave del caso
puede destruirse (crypto-shredding) y el contenido queda
**irrecuperable**; las evidencias se gestionan en el módulo
**Documentos**.

El informe de acceso (art. 15/20) también puede crearse para **socios del
club** si su organización utiliza la gestión de clubes: datos maestros con
tutores legales, direcciones y datos bancarios, y un resumen de los datos del
club (periodos de afiliación, grupos, asistencias, cuotas, donaciones,
graduaciones, rendimientos y más) con un extracto por área. La búsqueda
encuentra a los socios por nombre, correo electrónico o número de socio.

Todas las páginas siguientes se encuentran en la barra lateral, en
**Protección de datos**. Para consultarlas basta el permiso de lectura del
módulo de protección de datos (excepción: portal de interesados); para los
cambios existe un permiso propio por área. El rol **Protección de datos**
tiene todos estos permisos.

## Corresponsabilidad

**Protección de datos** → **Registros** → **Corresponsabilidad**. El
**Registro de acuerdos de corresponsabilidad** recoge los acuerdos de
corresponsabilidad según el art. 26 RGPD. La lista muestra **Título**,
**Socio**, **Estado** e **Información esencial proporcionada**.

**Crear nuevo acuerdo de corresponsabilidad**:

- **Socio (proveedor)** del registro de proveedores y **Título**
  (obligatorios), opcionalmente **Válido desde** y **Punto de contacto
  común**.
- **Matriz de responsabilidades**: para **Obligaciones de información (Art.
  13/14)**, **Derechos de los afectados**, **Incidentes de protección de
  datos** y **Contacto de la autoridad de control** usted fija en cada caso
  quién es responsable: **Nosotros**, **Socio** o **Conjunto** (por defecto).
- Casilla **Información esencial del acuerdo de corresponsabilidad
  proporcionada a los interesados**.
- Opcionalmente el **Documento del contrato** (PDF, DOC o DOCX, hasta 20 MB).

Un acuerdo nuevo empieza con el estado **Borrador**. En el acuerdo cambia la
matriz, el **Punto de contacto**, el **Estado** (**Borrador**, **Activo**,
**Rescindido**, **Caducado**) e **Información esencial proporcionada**. En
**Actividades de tratamiento vinculadas** marca las actividades afectadas del
registro y guarda con **Guardar vínculos**. El documento del contrato se
descarga mediante el enlace de los datos clave.

**Permiso:** consulta con el permiso de lectura; creación y modificación con
el permiso para proveedores y contratos de encargo del tratamiento.

## Catálogo TOM

**Protección de datos** → **Registros** → **Catálogo TOM**. El catálogo reúne
en un solo lugar las medidas técnicas y organizativas (art. 32 RGPD). La lista
muestra **Medida**, **Área**, **Estado** y **Revisión pendiente**; si la fecha
de revisión ha pasado, aparece resaltada en rojo.

**Nueva medida**: **Denominación**, **Área de medidas** (por ejemplo
**Control de acceso físico**, **Control de acceso a sistemas**, **Control de acceso a datos**, **Control de
transmisión**, **Control de entrada**, **Control de disponibilidad**,
**Recuperabilidad**, **Control de separación** o **Gestión de la protección
de datos**), **Descripción**, **Riesgos abordados** y **Evidencias
(políticas, actas, certificados …)**. La medida se crea con la versión 1 como
borrador.

En la medida:

- **Versiones**: guarda los cambios mediante **Nueva versión** con
  descripción, riesgos abordados y **Nota de cambio**; las versiones
  anteriores se conservan. **Liberar** convierte una versión en la **Versión
  válida**.
- **Actividades de tratamiento asignadas**: elija una actividad y
  **Asignar**. Cuando se aprueba una actividad de tratamiento, su versión
  congela también el estado de las medidas asignadas.
- **Comprobaciones de eficacia**: **Documentar la verificación** con
  **Resultado** (**Eficaz**, **Desviación** o **Sin efecto**), opcionalmente
  **Medida de seguimiento vencida** y **Desviación / acción de seguimiento**.
  La próxima revisión se fija en la fecha de la medida de seguimiento o, sin
  fecha, un año después; esa fecha aparece en la lista como **Revisión
  pendiente**.
- **Pruebas**: suba archivos con **Subir justificante**, opcionalmente con
  **Válido hasta (opcional)**. Las pruebas caducadas se marcan; el análisis
  de brechas avisa de las pruebas que caducan pronto o ya han caducado.

**Permiso:** consulta con el permiso de lectura; crear, versionar, liberar,
asignar, comprobar y subir pruebas con el permiso para el catálogo TOM.

## Análisis de brechas

**Protección de datos** → **Incidencias y revisión** → **Análisis de
brechas**. El análisis comprueba mediante reglas si faltan o caducan
contratos, evaluaciones y pruebas:

- encargados del tratamiento sin contrato de encargo,
- contratos de encargo que caducan pronto o ya han caducado (por defecto con
  30 días de antelación),
- corresponsables sin acuerdo de corresponsabilidad,
- actividades de tratamiento que requieren una EIPD sin EIPD concluida,
- actividades de tratamiento sin TOM asignadas,
- pruebas de TOM que caducan pronto o ya han caducado.

**Ejecutar análisis ahora** inicia una ejecución. Además, el análisis se
ejecuta automáticamente una vez al día en cuanto el análisis de brechas se ha
abierto una primera vez en su organización. Arriba, un semáforo muestra el
número de hallazgos por estado; debajo aparecen los hallazgos –primero los
abiertos– con **Requisito**, **Estado**, **Desencadenante** y **Referencia**
(enlace a la actividad, al contrato o al proveedor).

El análisis asigna **Falta** o **Caduca**. En **Decisión** usted fija a mano
**Disponible**, **En revisión**, **No aplicable**, **Desviación aceptada** o
**Reabierto**, cada vez con **Justificación** y **OK**. Para «No aplicable» y
«Desviación aceptada» la justificación es obligatoria. Las ejecuciones
posteriores ya no modifican un hallazgo decidido a mano. Las brechas que el
propio análisis estableció y que ya no se producen pasan a **Disponible** en
la siguiente ejecución.

En el **Catálogo de requisitos**, al final de la página, define qué
comprobaciones se ejecutan: cada requisito puede renombrarse y desactivarse
con el interruptor; los requisitos desactivados se omiten. Las entradas de un
perfil sectorial llevan la indicación **Perfil sectorial**.

**Permiso:** consulta con el permiso de lectura; iniciar el análisis, decidir
y mantener el catálogo con el permiso para el análisis de brechas.

## Conservación y eliminación

**Protección de datos** → **Incidencias y revisión** → **Conservación y
eliminación**. El concepto de borrado propone eliminar los datos cuyo plazo
de conservación ha vencido; nada se elimina ni se anonimiza sin una
confirmación en dos pasos. Arriba figura la jurisdicción de su organización
(por ejemplo DE), de la que dependen los plazos.

- **Plazos por área**: para cada área de datos –por ejemplo el registro de
  auditoría, las candidaturas o la plataforma de aprendizaje– el **Plazo** en
  años o días y la **Base jurídica**. Las áreas con la indicación «solo
  registro, sin escaneo» solo documentan el plazo; para ellas no se generan
  propuestas.
- **Escanear ahora** busca registros con el plazo vencido y crea
  **Propuestas de eliminación**. El escaneo también se ejecuta
  automáticamente a intervalos regulares (por defecto, cada semana). Los
  registros bajo retención legal y las excepciones de la materia no reciben
  propuesta.
- Cada propuesta muestra **Área**, **Registro**, **Plazo vencido desde**,
  **Justificación** y **Estado**.

La eliminación se hace en dos pasos: primero **Confirmar** (estado
**confirmado**) o **Rechazar** (**rechazada**); después, en las propuestas
confirmadas, **Eliminar definitivamente**, de una en una o agrupadas por área
con **Eliminar los confirmados en …**. Según el área, el registro se elimina o
se anonimiza. Si entretanto se ha establecido una retención legal, se
rechazan la confirmación y la eliminación; en la eliminación agrupada esos
registros se omiten y se cuentan en el mensaje. Cada decisión queda
registrada.

**Permiso:** consulta con el permiso de lectura; escanear y decidir con el
permiso para el análisis de brechas.

## Retención legal

**Protección de datos** → **Incidencias y revisión** → **Retención legal**.
Una retención legal es una nota de bloqueo para procedimientos en curso de un
interesado o judiciales: mientras está activa, no se elimina ni se anonimiza
nada de la persona o del cliente.

La lista muestra primero las retenciones activas, con **Afectado** (nombre,
persona o cliente), **Número de expediente**, **Motivo** (legible solo para
quien tiene el permiso de decisión), **Establecido** (fecha y quién) y
**Estado** (**activo** o «levantado el …»).

**Establecer retención legal**: en **Tipo** elija **Persona** o **Cliente** y
después exactamente una persona de la organización o un cliente;
opcionalmente un **Número de expediente**. El **Motivo** es obligatorio (al
menos 10 caracteres) y se guarda cifrado.

Mientras la retención está activa no se generan propuestas de eliminación; se
rechazan las eliminaciones confirmadas, la anonimización y la eliminación de
cuentas o clientes, y se conservan los puntos de ubicación sin procesar de la
persona. Al fusionar clientes, la retención pasa al cliente de destino.

**Levantar retención legal** exige un **Motivo del levantamiento** (al menos
10 caracteres). Después vuelven a aplicarse el concepto de borrado y las
limpiezas; el motivo se conserva como prueba. El establecimiento y el
levantamiento constan en el registro de la persona o del cliente.

**Permiso:** consulta con el permiso de lectura; establecer y levantar con el
permiso para el análisis de brechas: quien decide sobre las eliminaciones
también las bloquea.

## Portal de interesados

**Protección de datos** → **Incidencias y revisión** → **Portal de
interesados** (título de la página **Gestionar el portal de interesados**).
Aquí configura el formulario público mediante el cual los interesados
presentan sus solicitudes; el tema sobre el portal de acceso describe cómo
funciona para la persona.

- Mientras no exista ningún portal, la página muestra un aviso; el primer
  **Guardar** crea el portal con un enlace aleatorio.
- **Enlace público**: publique este enlace en su política de privacidad; no
  puede deducirse del nombre de la organización. Al lado ve si el portal está
  **activo** o **inactivo**. **Rotar el enlace** crea un enlace nuevo tras una
  pregunta de confirmación; los enlaces ya publicados dejan de ser válidos.
- **Ajustes**: **Portal activo (accesible públicamente)** –desactivado al
  principio–, **Permitir archivos adjuntos**, **Texto introductorio
  (opcional)** e **Idioma predeterminado (opcional, p. ej. es)**.

Las solicitudes recibidas aparecen como caso en **Solicitudes de afectados**.
Allí se marcan como entrada por el portal; los datos de identidad cuentan como
autodeclaración no verificada.

**Permiso:** un permiso propio para gestionar el portal de interesados; sin
él, la entrada de menú no aparece.
