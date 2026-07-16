---
title: "Gestión de dominios"
topic: domains.overview
version: 1
audience: []
related:
    - admin.domain-provider
    - contacts.manage
---

El módulo gestiona los dominios de una cuenta de DomainReselling conectada
como una cartera trazable: desde la asignación de cliente y el plazo,
pasando por los servidores de nombres/DNS, hasta la renovación, la
transferencia y los apuntes. La conexión en sí se configura en
«DomainReselling» dentro del área de administración.

**Cartera:** La vista general enumera cada dominio con cliente, vencimiento,
modo de renovación, registrador, bloqueo de transferencia y actualidad de
los datos. Los indicadores superiores muestran el vencimiento en 90 días,
los modos de riesgo (autoexpire/autodelete), los dominios sin asignación de
cliente y los casos de sincronización/conflicto. Se filtra por nombre de
dominio, TLD, actualidad, modo de renovación y corredor de vencimiento.

**Asignación de cliente:** Cada dominio puede asignarse a un cliente
(internamente mediante su identificador Sqid). Los dominios sin asignar
permanecen visibles en el indicador para mantener la cartera completa.

**Vista de detalle:** La página del dominio reúne resumen, servidores de
nombres y DNS, facturas, cronología y acciones. «Actualizar» concilia el
estado del proveedor para ese dominio concreto.

**DNS:** La zona se lee bajo demanda; los registros pueden reemplazarse o
modificarse de forma selectiva. Tras una escritura, el sistema detecta
desviaciones (conflicto de DNS) y las hace visibles en lugar de
sobrescribirlas. Los registros MX/SRV exigen una prioridad.

**Registro:** Antes de registrar se comprueba la disponibilidad. Un registro
necesita un cliente, un handle de contacto propietario, al menos dos
servidores de nombres y una confirmación de precio explícita.

**Plazo y transferencia:** Establecer el modo de renovación, renovar
manualmente, activar o liberar el bloqueo de transferencia e iniciar una
transferencia entrante se ejecutan como comandos registrados con historial
de estado (borrador → enviado → confirmado).

**Acciones de alto riesgo:** La eliminación, el push a otro usuario, el
trade (cambio de titular), la transferencia saliente y la asignación de
objeto están bloqueadas: exigen volver a escribir el nombre del dominio y
una aprobación a cuatro ojos. Las acciones enviadas aparecen para aprobación
o rechazo; el estado del proveedor se concilia tras la ejecución (los
conflictos se marcan).

**Apuntes e informes:** La vista de apuntes es un diario de solo lectura, no
una factura fiscal. Los informes reúnen el corredor de vencimiento, la
previsión de costes de renovación, la asignación de cliente, los modos de
riesgo y la cobertura de facturas.

**Revendedores/subusuarios:** La vista de revendedores muestra la jerarquía
de subusuarios con cartera, saldos y nivel, y permite la asignación de
cliente por subusuario.
