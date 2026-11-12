---
title: "Leads"
topic: sales.leads
version: 6
keywords:
    - prospectos
    - clientes potenciales
    - captación de clientes
    - embudo de ventas
    - pipeline de ventas
    - CRM
    - cualificar lead
    - convertir en cliente
    - seguimiento comercial
    - Calendly
    - control de duplicados
audience: []
modules:
    - module.vertrieb
related:
    - contacts.manage
---

Un **lead** es un interesado **antes** del estado de cliente — por
recomendación, web, feria o llamada. La ficha del lead guarda contacto, fuente
y necesidad; la cualificación pasa por las **notas de comunicación** con
seguimiento, igual que en un cliente.

**Leads a partir de reservas de citas.** Si alguien sin ficha de cliente reserva
por Calendly, puede crearse un lead con la fuente «Reserva de cita». Cada
organización lo activa en los ajustes de Calendly; por defecto está desactivado.
Si puede tratarse de un cliente existente, no se crea ningún lead y la reserva
espera en la bandeja de asignación. Una nueva reserva de la misma dirección se
vincula al lead abierto.

## El pipeline

**Nuevo → Contactado → Cualificado → Convertido / Descartado.** Un lead
descartado puede recalificarse; uno convertido vive después como cliente.

## Convertir — con control de duplicados

Antes de crear, la ficha muestra los **posibles clientes existentes** (nombre,
correo). Vincular es la opción equivalente — un segundo maestro de clientes
por la puerta trasera es justo lo que la limpieza de duplicados repara luego
con esfuerzo. La ficha del lead permanece tras la conversión y apunta al
cliente: la historia de adquisición pertenece a la adquisición.

## Plazo de conservación

Los leads son datos personales **sin contrato**. Los no convertidos se
anonimizan **6 meses después del último contacto** — mediante el escaneo de
conservación del módulo de protección de datos (cola de revisión, sin borrado
silencioso). La métrica del pipeline (fuente, estado, periodo) queda, la
persona desaparece.

## Ficha

Los leads tienen campos propios (Administración › Campos propios), etiquetas y
adjuntos. Al anonimizar el lead tras el plazo de supresión se eliminan también
los adjuntos, los campos propios y las etiquetas. Los documentos de la gestión
documental se vinculan solo al cliente.

## Cockpit

La ficha del lead es el cockpit del interesado: cabecera con estado, origen y
etiquetas, debajo contacto y necesidad, las notas de comunicación con
seguimiento, los campos propios, los adjuntos, las ofertas creadas desde el
lead y el historial. «Oferta desde el lead» crea una oferta con los datos de
contacto; las ofertas aparecen con su estado en la ficha, y el informe de leads
las cuenta por origen y conversión. Como en cliente, proveedor y proyecto, los
paneles de otros módulos muestran solo lo que existe para el lead — un módulo
sin datos del lead no aparece. El historial lista las entradas de auditoría del
lead, ofertas incluidas; las reservas de cita se vinculan al lead abierto.

Quien puede abrir la página «Solicitudes de cita» ve en el panel **Citas** las citas reservadas y solicitadas del lead (por ejemplo, de Calendly) con fecha, lugar y estado; el clic abre las solicitudes de cita.

## Último contacto

El plazo cuenta desde el **último contacto**. Avanza con cada nota de
comunicación en el lead (con su fecha), con cada modificación de contenido, con
cada cambio de estado y con cada presupuesto creado desde el lead. Cambiar solo
la persona responsable no cuenta como contacto.
