---
title: "Cambio de software contable"
topic: admin.accounting-migration
version: 3
keywords:
    - migración contable
    - migrar contabilidad
    - cambio de proveedor
    - Lexoffice
    - orgaMAX
    - sevDesk
    - easybill
    - migración de datos
    - simulación
    - prueba en seco
    - fecha de corte
    - funcionamiento en paralelo
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

El cambio de software contable lleva a una organización de un sistema al
siguiente de forma controlada. Origen y destino se eligen libremente entre
Lexoffice, orgaMAX, sevDesk y easybill, en ambas direcciones.
WorkDiary no copia a ciegas de un sistema a otro: asigna ambos sistemas
externos a los mismos objetos de negocio locales.

Pasos: planificar (áreas de datos + fecha de corte, un solo cambio por
organización a la vez) → análisis como simulación (no escribe en ningún
sistema externo) → decidir individualmente los registros ambiguos →
operación paralela (los documentos antiguos se liquidan en el sistema
origen) → conmutación (desde la fecha de corte los nuevos documentos se
crean exclusivamente en el sistema destino; el envío al origen queda
bloqueado técnicamente y la conmutación permanece bloqueada mientras haya
conflictos) → finalización con protocolo CSV.

En sevDesk y easybill:

- Los **datos maestros** se concilian mediante el vínculo de contacto: los
  clientes y proveedores ya vinculados con el programa (transferencia, envío
  de contacto o sincronización de contactos) cuentan como asignados.
- Los **artículos** no se pueden conciliar con sevDesk y easybill, porque
  WorkDiary solo refleja su maestro de artículos y no lo vincula con los
  artículos locales. En ese caso, desmarque el área. El formulario la marca
  («no con sevDesk, easybill»); si la planificación falla por ello, se
  conservan origen, destino y fecha de corte y el área queda desmarcada.
- El **histórico de documentos** procede del espejo de documentos. Ejecute
  la consulta de documentos antes del análisis y antes de la finalización
  para que los documentos antiguos abiertos queden completos.
- Las imágenes de los documentos siguen en el programa. Guárdelas mediante
  su exportación antes de que termine el contrato.

Principios: los documentos finalizados **nunca** se reconstruyen en el
sistema destino — permanecen localizables como histórico con número, estado
y origen. Cada paso queda registrado en una cadena de eventos a prueba de
manipulaciones.
