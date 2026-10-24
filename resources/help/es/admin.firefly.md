---
title: "Firefly III como fuente de conciliación"
topic: admin.firefly
version: 1
keywords:
    - Firefly III
    - movimientos de cuenta
    - cobro
    - conciliación
    - concepto
    - token de acceso personal
audience:
    - admin
related:
    - admin.plugins
    - admin.integrations
    - admin.scheduler
---

La página **Firefly III** lee las entradas y salidas de su instalación de Firefly
III y muestra a qué factura o factura recibida pertenece un movimiento. Encontrará
la página en el menú del sistema (el engranaje **Sistema**) en **Plugins** →
**Firefly III**, en cuanto el plugin esté activo. WorkDiary no escribe nada en
Firefly III ni contabiliza pagos a partir de los movimientos.

## Configurar el acceso

1. En Firefly III, cree un **token de acceso personal** en **Opciones** →
   **Perfil** → **OAuth**.
2. En la página, abra **Credenciales** en el menú ⋯ e introduzca la **dirección**
   (sin /api) y el token. Si Firefly III funciona en su propia red, active además
   **Permitir direcciones privadas**; solo surte efecto si el operador lo permite.
3. Pulse **Comprobar conexión**. WorkDiary muestra la instancia, la versión y la
   cuenta.

## Qué se concilia

Cada ejecución lee los movimientos del **periodo** (60 días hasta hoy por defecto,
ajustable en **Credenciales**). Las transferencias entre cuentas propias quedan
fuera.

- **Entrada:** si el concepto, las notas, la referencia o el identificador SEPA de extremo a extremo contienen exactamente un
  número de factura de su organización, la fila muestra esa factura.
- **Salida:** lo mismo con el número de una factura recibida.
- Si una transacción tiene un importe en una segunda moneda, figura bajo el
  importe.
- Una transacción dividida (split) aparece como una fila por cada parte.
- Si coinciden varios documentos, el movimiento queda **Ambiguo**; sin
  coincidencia, **Sin asignar**. El importe y la fecha por sí solos nunca asignan.

Lo que falta en Firefly III dentro de un periodo leído por completo desaparece de
la lista. Una ejecución interrumpida queda **incompleta** y no elimina nada.

## Cuándo se sincroniza

Cada hora; cambie la frecuencia en **Trabajos programados**, **Sincronizar ahora**
inicia al momento. **Desconectar** detiene la sincronización.

## Errores habituales

- La comprobación falla con 401: el token es erróneo o ha caducado.
- La comprobación indica una dirección interna: la dirección apunta a su propia
  red, pero **Permitir direcciones privadas** está desactivado o el operador no lo
  permite.
