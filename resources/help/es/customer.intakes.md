---
title: "Entradas de clientes"
topic: customer.intakes
version: 1
keywords:
    - solicitud de cliente
    - solicitud del portal
    - pedido de impresión
    - solicitud de TI
    - tramitar solicitud
    - rechazar solicitud
    - pregunta al cliente
    - vincular presupuesto
    - enlace de subida
    - Nextcloud
    - archivos del cliente
audience: []
related:
    - customer.queries
    - print.orders
---

Los clientes envían solicitudes de impresión o de TI con archivos en el portal de clientes, en **Solicitudes y pedidos**. Cada entrada recibe un número de referencia y aparece aquí con cliente, tipo de servicio, estado, fecha deseada y responsable. Las entradas abiertas siempre se muestran; las cerradas se limitan al periodo de la cabecera.

Así trabaja con una entrada:

- **Asignar** asume la entrada; pasa a «En curso».
- **Hacer una consulta** envía al cliente una pregunta con archivos. La entrada espera entonces su respuesta; la respuesta o los archivos adicionales reanudan el trabajo.
- **Nota interna** se queda en la empresa: el cliente nunca ve la nota ni sus archivos.
- **Presupuesto**: cree un presupuesto nuevo o vincule uno existente del cliente. Tras la aprobación y el envío, el cliente decide en el portal; vincule de nuevo una versión revisada: un consentimiento anterior nunca vale para otra versión.
- **Transferir** crea la orden de impresión o el ticket tras la aceptación. Una aceptación parcial exige un ajuste documentado del alcance. Las transferencias repetidas o simultáneas no crean un segundo expediente.
- **Rechazar** exige un motivo que el cliente leerá. Ya no es posible una vez aceptado el presupuesto.

Tras la transferencia, el expediente operativo es determinante; el cliente ve su estado. Con **Abrir envío de archivos** puede adjuntar más archivos a la entrada transferida. Si falla un correo al cliente, la entrada muestra un aviso; los datos y decisiones guardados no se ven afectados.

**Enlace de subida (Nextcloud):** Si el plugin de Nextcloud está configurado con credenciales propias para el canal de subida, el cliente abre en la entrada un enlace de subida con contraseña. WorkDiary incorpora los archivos nuevos cada 15 minutos o con «Recoger ahora», los comprueba como las subidas del portal y revoca el enlace tras la última incorporación en cuanto la entrada deja de aceptar archivos o el enlace caduca. Los errores se muestran en el enlace y en el historial; los archivos permanecen además en Nextcloud.
