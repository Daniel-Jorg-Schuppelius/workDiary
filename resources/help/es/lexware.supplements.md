---
title: "Complementos Lexware: tarifa, matriz de funciones y entrega"
topic: lexware.supplements
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - articles.lexoffice
---

En **Facturación → Complementos Lexware** registra la tarifa contratada de Lexware Office (S, M, L, XL o «Desconocida / contrato especial») con origen, fecha de confirmación y —en accesos de prueba— fecha de fin y tarifa siguiente confirmada. La página funciona sin conexión API.

**Matriz de funciones:** Por cada función ve si está **incluida en Lexware** en su tarifa, si workDiary la ofrece como **complemento** o si está **planificada** (ampliación). Con una tarifa desconocida no hay afirmación segura sobre Lexware; las funciones locales siguen utilizables según sus propios requisitos. En el primer paquete, workDiary complementa para S las facturas estándar/electrónicas, presupuestos y reclamaciones a partir de lo existente, y para S/M/L las **facturas periódicas** mediante planes de facturación.

**Activar un complemento:** Solo los complementos activados deliberadamente en el perfil de tarifa aparecen como «Disponible en workDiary». Los requisitos son el módulo «Ventas y facturación», la soberanía de facturación **workDiary** (un cliente facturado externamente no recibe una serie local; el cambio es un proceso aparte con fecha de efecto) y el permiso para ver facturas. La tarifa es una orientación, no una autorización; una tarifa superior no quita nada.

**Lista de entrega:** En «Lista de entrega Lexware» figuran los documentos emitidos de clientes facturados localmente en el periodo de cabecera elegido, con estado de factura, envío y entrega por separado. **Exportar** descarga el original congelado de cada documento como PDF con SHA-256 y una lista de asignación (CSV) en forma de paquete: una descarga para usted, no un supuesto formato de importación de Lexware. **Confirmar manualmente** valida la entrega con usuario, hora y nota. «Exportado» o «confirmado» nunca significa «contabilizado» ni «pagado»; la anulación y el abono siguen siendo documentos propios con referencia al original.

**Entrega automática:** La vía «automática» requiere una clave API propia (tarifa XL) y una vía de entrega comprobada; hasta entonces la exportación manual sigue siendo la vía estándar. Las entregas abiertas permanecen visibles al cambiar de tarifa.

**Permisos:** Ven las páginas todas las personas con «Listar facturas»; solo «Configuración financiera» cambia el perfil de tarifa; la exportación y la confirmación requieren «Exportar facturas».
