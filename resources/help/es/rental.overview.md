---
title: "Alquiler de equipos"
topic: rental.overview
version: 3
keywords:
    - arrendador
    - apartamento vacacional
    - arrendamiento
    - alquiler de maquinaria
    - alquiler de herramientas
    - renta de equipos
    - parque de alquiler
    - contrato de alquiler
    - fianza
    - reservar equipo
    - devolución
    - acta de entrega
    - calendario de disponibilidad
    - tarifa de alquiler
    - telemática
audience: []
modules:
    - module.rental
related:
    - claims.overview
---

El módulo gestiona el alquiler de equipos y máquinas como expedientes
trazables — desde la reserva y la entrega hasta la devolución, la fianza
y la facturación.

**Parque de equipos:** un perfil de alquiler hace alquilable un activo
(grupo, tiempos de margen, accesorios, tarifa predeterminada).

**Disponibilidad:** el calendario muestra reservas, alquileres y ventanas
de mantenimiento. Las dobles reservas y los equipos bloqueados se
impiden de forma visible.

**Expediente de alquiler:** cada operación recibe un número (VER-…); la
versión de la tarifa aplicada se congela como instantánea.

**Entrega y devolución:** protocolos separados documentan estado,
accesorios, contadores, fotos y firma; la devolución lleva la decisión
posterior (limpieza, reparación/bloqueo, reclamación).

**Facturación:** las partidas se aprueban y facturan localmente o se
transfieren al sistema de facturación principal. La fianza es una
operación financiera separada.

**Lugar de uso y geocerca:** Cuando llegan posiciones de equipos por
importación (por ejemplo, de una exportación telemática), WorkDiary
comprueba durante un alquiler si el equipo está en el lugar de uso: en la
ubicación del alquiler con el radio fijado en la configuración o, si no,
dentro de las geocercas del cliente. Si lo abandona, se notifica a la
persona responsable y a la jefatura de equipo.

## Viviendas y alquiler vacacional

El perfil **Arrendadores** recomienda el módulo de alquiler. La vista utiliza el período global y muestra también viviendas libres. Los arrendamientos guardan inquilino, inicio, fin opcional, alquiler, gastos y fianza. Un arrendamiento confirmado bloquea hasta el último día incluido, indefinidamente si no hay fin. Indique la fecha al salir; cancelar elimina toda la ocupación.

Aceptar una solicitud vacacional crea una reserva firme. La tarifa diaria representa el precio por noche y la limpieza final es opcional. Se comprueban capacidad, estancia mínima y horarios. Publique la vivienda y habilite las solicitudes para el cliente. Alquileres y fianzas son datos administrativos del MVP, sin facturación automática.

El perfil incluye además tres procedimientos (**Entrega de vivienda**, **Inspección de salida**, **Cambio de huéspedes**), el **acta de entrega de la vivienda** con lecturas de contadores y llaves, plantillas de contrato para el arrendamiento de vivienda con y sin renta indexada y para las condiciones de alquiler de un apartamento vacacional, con plazos como la liquidación anual de gastos, y campos propios de la vivienda (superficie, habitaciones, planta, mascotas, certificado energético). Un arrendamiento solicitado se confirma, rechaza o cancela; uno confirmado se puede cancelar. Los arrendamientos cancelados o rechazados no se modifican.
