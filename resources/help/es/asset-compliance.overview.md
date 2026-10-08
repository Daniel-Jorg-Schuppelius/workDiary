---
title: "Equipos de medición y calibración"
topic: asset-compliance.overview
version: 2
keywords:
    - metrología
    - gestión de equipos de medida
    - certificado de calibración
    - verificación periódica
    - inspección reglamentaria
    - vencimiento de inspecciones
    - informe de inspección
    - prueba eléctrica
    - ITV
    - bloqueo de equipo
    - ISO 17025
audience: []
modules:
    - module.asset_compliance
related:
    - rental.overview
    - asset-finance.overview
---

El módulo gestiona equipos sujetos a inspección: verificación,
calibración, inspecciones reglamentarias, ITV, comprobación eléctrica,
mantenimiento del fabricante y controles internos — con evidencias y
bloqueos de uso.

**Perfiles de inspección (catálogo):** las plantillas globales se
sobrescriben con perfiles de la organización con el mismo código
(intervalo, preaviso, tolerancia, periodo de gracia, efecto de bloqueo).

**Obligaciones:** asignar un perfil a un activo crea una obligación con
vencimiento y responsable. Las inspecciones vencidas avisan; tras el
periodo de gracia el sistema bloquea mediante el modelo de bloqueo
compartido — alquiler, planificación y uso leen el mismo estado.

**Protocolos y certificados:** valores medidos contra límites
congelados, resultado, validez, firma y certificado de calibración
opcional. Las evidencias son inmutables — correcciones versionadas.

**Excepciones** limitadas en el tiempo, justificadas y auditadas.
**Inspectores externos** mediante acceso limitado. **Matriz de
referencia normativa** sin promesa de conformidad.

## Calendario de inspecciones

Menú **Equipo de inspección** → **Calendario de inspecciones**; la página
también está disponible como pestaña en las demás páginas de equipos de
inspección. La lista muestra las citas de inspección abiertas
(**Planificado**, **Anunciado**, **En curso**) ordenadas por vencimiento. Con
el filtro de estado también ve las citas realizadas, incumplidas o
canceladas. Columnas: **Vence** (con la fecha planificada, si la hay),
**Activo**, **Perfil de inspección**, **Inspector / organismo de inspección**
y **Estado**.

**Planificar cita de inspección** (al final de la página): elija la
**Obligación de inspección** –la lista muestra activo, perfil y próximo
vencimiento–, **Vence el** (obligatorio), opcionalmente **Planificado el**,
**Inspector interno** u **Organismo de inspección externo**, y después
**Planificar cita**. Una cita nueva empieza como **Planificado**. En las
citas con un organismo externo, **Invitar acceso** invita al organismo
mediante un acceso de duración limitada.

**Registrar inspección** está disponible en las citas abiertas y abre el acta
de inspección:

- **Resultado**: **Superado**, **Superado con reservas** o **No superado**.
- **Realizado el (vacío = ahora)** y **Válido hasta (vacío = intervalo)**: sin
  indicación, el comprobante vale un intervalo de inspección desde la
  realización. Una inspección no superada no recibe fecha de validez.
- Un valor medido por cada requisito del perfil; al lado figuran los valores
  límite.
- **Decisión posterior**: **Ninguna / aprobación**, **Recalibración
  (bloqueante)**, **Reparación (bloqueante)**, **Uso restringido** (solo se
  anota, no bloquea), **Bloqueo**, **Baja** (también bloquea) o **Abrir
  reclamación** (crea una reclamación si su organización usa el módulo de
  reclamaciones y garantía), además de la **Justificación de la medida**.
- **Certificado / comprobante de inspección** (desplegable): número de
  certificado, emisor (obligatorio en cuanto se introduce un número), fecha
  de emisión, validez, rango de medición, tolerancia y un documento del que se
  guarda el hash.
- **Firma (nombre)**, **Coste de la inspección (neto, €)** y **Observación**.

**Documentar la verificación** crea un comprobante inalterable y cierra la
cita como **Realizado**. Una inspección superada fija el próximo vencimiento
en un intervalo de inspección después de la realización. Superado sin medida
posterior levanta los bloqueos por inspecciones vencidas o no superadas; «No
superado» sin medida elegida bloquea el activo. Si el perfil exige un
certificado, se rechaza una inspección superada sin número de certificado.

**Permiso:** consulta con **Listar obligaciones de inspección y equipos de
medición**; planificación de citas con **Gestionar perfiles y obligaciones de
inspección**; registro de inspecciones con **Realizar inspecciones y registrar
evidencias**. **Invitar acceso** requiere uno de los dos últimos permisos.

## Órdenes de inspección

Menú **Equipo de inspección** → **Órdenes de inspección**. Aquí encarga
inspecciones vencidas a un proveedor de inspección, que no necesita cuenta de
usuario. La lista muestra **Denominación**, **Proveedor de inspección**, el
número de **Equipos**, **Estado** y **Precio ofertado**; **Abrir** lleva a la
orden.

Así transcurre una orden:

1. **Crear orden de inspección**: elija **Denominación**, **Proveedor de
   inspección** (entre sus proveedores), **Correo del proveedor** y al menos
   una cita de inspección en estado **Planificado** o **Anunciado**, y después
   **Enviar orden**. El proveedor recibe un correo con un enlace válido durante
   90 días. Las citas elegidas pasan a **Anunciado** y la orden queda en
   **Solicitado**.
2. Mediante el enlace, el proveedor presenta una oferta con precio, fecha
   prevista y observación; la orden pasa a **Oferta recibida**. En la vista de
   la orden elige **Aceptar oferta** (estado **Encargado**) o **Rechazar
   oferta** (vuelve a **Solicitado**; el proveedor puede presentar una oferta
   nueva).
3. Tras el encargo, el proveedor comunica por cada equipo el resultado, la
   fecha de inspección, la validez, el número de certificado y una
   observación, opcionalmente con el certificado como archivo. La orden pasa
   entonces a **Resultados comunicados**.
4. **Adoptar resultados** crea un comprobante de inspección por cada equipo
   comunicado, como una inspección del calendario de inspecciones, con el
   proveedor como inspector y emisor y con el certificado y su suma de
   comprobación. La orden queda después **Completado**; en la tabla los
   equipos muestran «adoptado».

**Anular orden** es posible mientras no se hayan comunicado resultados; las
citas anunciadas vuelven a **Planificado**. Si un perfil exige un certificado
y un resultado superado no tiene número de certificado, la adopción se
interrumpe con un mensaje.

**Permiso:** consultar la lista y la orden con **Listar obligaciones de
inspección y equipos de medición**; crear, decidir sobre la oferta y anular con
**Gestionar perfiles y obligaciones de inspección**; adoptar resultados con
**Realizar inspecciones y registrar evidencias**.

## Rondas de inspección

Pestaña **Rondas de inspección**, por ejemplo en el **Calendario de
inspecciones**. Una ronda de inspección es una lista prevista de inspecciones
vencidas de una ubicación o de un grupo, que usted recorre in situ escaneando.
La lista muestra **Denominación**, **Vencimiento hasta**, **Realizadas**
(inspecciones realizadas del total) y **Estado** (**Abierta** o **Cerrada**).

**Crear ronda**: **Denominación**, **Vencimiento hasta** (rellenado con hoy más
30 días) y opcionalmente **Ubicación**, **Grupo (categoría)**, **Perfil de
inspección** y **Cliente**. La ronda incluye todas las obligaciones de
inspección activas que vencen hasta esa fecha; los activos dados de baja
quedan fuera. Las obligaciones que vencen más tarde no se añaden. Si para la
selección no vence nada, no se crea la ronda.

En la ronda ve los indicadores **Realizadas**, **Faltan** y **Vencidas** y
todas las posiciones con activo, perfil, vencimiento y estado (**Abierta**,
**Vencida** o el resultado registrado).

- **Escanear objeto**: introduzca o escanee un código QR, n.º de instalación,
  n.º de inventario o número de serie y elija **Abrir**. En dispositivos con
  NFC aparece además **Leer etiqueta NFC**. Si para el objeto hay exactamente
  una inspección abierta, se abre el registro; si hay varias, elige el perfil
  de inspección.
- **Registrar inspección** (registro rápido): **Resultado**, **Observación** y
  **Firma (nombre)** (rellenada con su nombre), y después **Guardar
  inspección**. Así se crea el mismo comprobante inalterable que en el
  calendario de inspecciones, pero sin valores medidos ni certificado. «No
  superado» bloquea el activo. Si el perfil exige un certificado, registre una
  inspección superada en el calendario de inspecciones; el registro lo
  indica.
- **Cerrar ronda**: las inspecciones todavía abiertas quedan como faltantes;
  después ya no es posible registrar en la ronda.

**Permiso:** consulta con **Listar obligaciones de inspección y equipos de
medición**; crear, escanear, registrar y cerrar rondas con **Realizar
inspecciones y registrar evidencias**.

## Ruta de inspección

En el **Calendario de inspecciones** mediante el botón **Ruta de
inspección**. Así planifica como ruta las citas de inspección abiertas de un
inspector interno.

- Arriba elige el **Inspector** (rellenado: usted mismo) y **Vence hasta**
  (rellenado: hoy más 14 días).
- La tabla muestra las citas abiertas en las que esa persona figura como
  inspector interno y que todavía no están asignadas a una orden de trabajo,
  con vencimiento, activo, perfil de inspección y **Ubicación**. Todas las
  citas están preseleccionadas.
- Con **Fecha de la ruta** (rellenada: mañana) y **Planificar ruta**, cada
  cita elegida se convierte en una orden de trabajo para el inspector en la
  ubicación del equipo. La cita recibe la fecha de la ruta como fecha
  planificada, la planificación de rutas crea la ruta y optimiza el orden; a
  continuación se abre la ruta.
- Los equipos marcados **sin coordenadas** permanecen en la ruta, pero no
  entran en el cálculo del recorrido.
- Las rutas de inspección requieren el módulo de planificación. Sin este
  módulo, la página muestra un aviso y ningún botón para planificar.

**Permiso:** **Gestionar perfiles y obligaciones de inspección**.

## Informe de auditoría

Menú **Equipo de inspección** → **Informe de auditoría** (título de la página
**Informe de auditoría del sistema de inspección**). El período se elige en la
barra de filtros de la página; por defecto son los últimos tres meses.

- Estado actual, independiente del período: **Obligaciones de inspección**
  (obligaciones activas), **Vencido** (vencimiento más tolerancia superado),
  **Próximo a vencer** (dentro del preaviso del perfil) y **Bloqueado
  (inspecciones)** (bloqueos activos por inspecciones vencidas o no
  superadas).
- Referidos al período: **Inspecciones en el período**, **No superado**,
  **Tasa de inspección** (proporción de inspecciones superadas, también con
  reservas), **Certificados**, **Costes de inspección en el período** y los
  costes de hasta tres tipos de inspección.
- Tablas: **Obligaciones de inspección por tipo de inspección**,
  **Inspecciones por inspector (top 10)** y **Desviaciones (no superado)** con
  activo, momento y observación.

**Congelar la instantánea** guarda de forma inalterable los indicadores del
período elegido. Las diez últimas instantáneas aparecen en **Instantáneas
congeladas (P2)** con período, fecha de creación, número de obligaciones
vencidas y tasa de inspección.

**Permiso:** **Listar obligaciones de inspección y equipos de medición**;
también para congelar una instantánea.
