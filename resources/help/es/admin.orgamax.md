---
title: "orgaMAX contabilidad"
topic: admin.orgamax
version: 2
keywords:
    - API de orgaMAX
    - integración contable
    - software de contabilidad
    - facturación
    - transferir facturas
    - sincronizar clientes
    - estado de pago
    - datos maestros
    - sincronización
audience:
    - admin
modules:
    - module.finance
related:
    - admin.integrations
    - admin.plugins
---

orgaMAX contabilidad se conecta como plugin por organización mediante
la OpenAPI oficial (no orgaMAX ERP). orgaMAX sigue siendo el sistema líder
para las capacidades activadas.

Conexión:

1. **Iniciar una intención de conexión** (modo piloto privado con clave/
   secreto API o extensión publicada con secreto de operador). WorkDiary
   genera una URL de retorno con token de estado.
2. Guardar la URL como URL de la extensión en orgaMAX y abrirla — orgaMAX
   añade el `iid`. Un `iid` ajeno sin intención válida nunca se vincula.
3. **Confirmar explícitamente** la cuenta detectada; el preflight de
   alcances bloquea si faltan permisos en lugar de activar parcialmente.

Liderazgo de datos por capacidad (clientes, proveedores, artículos,
facturación, pagos, gastos, documentos): lidera exactamente un sistema; el
estándar seguro es la revisión manual. Los datos maestros se asignan por la
bandeja de integración — sin datos en la sombra.

Facturación: los traspasos liberados (Finanzas → Traspasos, destino orgaMAX)
crean como máximo UN pedido orgaMAX (marcador de origen + conciliación en
lugar de reintentos ciegos). Convertir en factura, bloqueo irreversible,
envío y registro de pagos son acciones separadas, con permisos propios y
auditadas. Número, estado, pago y PDF provienen visiblemente de orgaMAX.

El sondeo se ejecuta con presupuesto y puntos de control (cada hora,
configurable); «Sincronizar ahora» respeta los mismos límites. El traspaso
de gastos/recibos permanece bloqueado hasta confirmar el piloto de recibos.

Espejo de comprobantes: si la facturación o los pagos están activos, la
sincronización refleja las facturas de orgaMAX con sus posiciones y, con la
capacidad de gastos, también los gastos como comprobantes de entrada. Aparecen
en el flujo de comprobantes, en la ficha de cliente y proveedor, en la
facturación, las partidas abiertas y las comisiones y en las compras de
suscripciones y licencias; las plantillas recurrentes figuran en la conciliación
de suscripciones y licencias (la API de orgaMAX no ofrece ritmo ni próxima
ejecución). Con la capacidad de artículos, los artículos de orgaMAX forman parte
del catálogo de artículos. Solo se leen datos — WorkDiary no devuelve los
gastos.

WorkDiary solo transfiere proveedores si la capacidad de proveedores está
activa y se ha elegido «WorkDiary lidera»; orgaMAX asigna entonces el número
de proveedor.
