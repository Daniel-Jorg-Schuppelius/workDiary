---
title: "Diseño de documentos"
topic: admin.document-design
version: 1
audience:
    - admin
related:
    - admin.branding
    - invoices.manage
---

El diseño de documentos adapta los PDF generados a la apariencia
de su organización: membrete, áreas de impresión y bloqueadas, bloques de
información y preajustes de estilo de tabla.

Flujo de trabajo:

1. **Subir un membrete** (PDF, JPG o PNG, A4 vertical) — un asset para la
   primera página y, opcionalmente, otro para las páginas siguientes. Los
   PDF se reducen a una página rasterizada segura y no interactiva; el
   original se conserva como prueba.
2. **Crear un perfil** y definir en el editor las áreas de impresión, la
   ventana de dirección, la línea de remitente y las áreas bloqueadas en
   milímetros — visual o numéricamente, también con teclado.
3. **Declarar los bloques de información**: `dinámico` (WorkDiary imprime),
   `proporcionado por el membrete` (con confirmación por versión de perfil)
   o `no aplicable`. Los bloques obligatorios de los tipos de documento
   asignados y los datos variables están protegidos.
4. **Generar un documento de prueba** por tipo de documento con textos
   largos, muchas posiciones y varios tipos impositivos; el preflight
   muestra solapamientos, bloques obligatorios faltantes y problemas de
   contraste.
5. **Activar la versión** — solo con un preflight sin errores. Las
   versiones activadas son inmutables; los cambios pasan por un nuevo
   borrador. Los documentos finalizados conservan su estado congelado.

Sin perfil se aplica el estándar del sistema (salida actual). Las facturas
ZUGFeRD/PDF-A-3 siguen siendo válidas tras aplicar el diseño — la factura
estructurada sigue siendo determinante.


Diseño base CI y herencia:

- El perfil estándar de la organización es tu **diseño base CI**. Las
  variantes para tipos de documento individuales (p. ej. oferta,
  factura, abono, reclamación) o familias enteras (ventas, compras,
  justificantes) **heredan** todas las secciones no sobrescritas — cada
  sección muestra si está heredada o sobrescrita; «restablecer al
  diseño base» elimina la sobrescritura. La variante más específica
  prevalece: tipo antes que familia antes que diseño base.
- La **vista previa PDF integrada** del editor renderiza mediante la
  misma canalización que la salida final; el tipo de documento y los
  datos de ejemplo (textos largos, muchas posiciones, varios tipos
  impositivos) son conmutables.
- **La familia tipográfica y el tamaño base** provienen de una lista
  curada compatible con PDF; los colores primario/de acento pueden
  **referenciar el branding de la organización** — los cambios del
  branding se aplican entonces automáticamente, sin copia de color en
  el perfil.
- Al activar, el diseño base se comprueba contra los bloques
  obligatorios de TODOS los tipos de documento personalizables; los
  formatos especiales genuinos (p. ej. etiquetas) declaran su
  restricción en el registro central de tipos de documento.
- Los **textos de cabecera/pie** de los documentos de venta (antes
  plantillas de factura) son una sección propia y heredable del perfil —
  versionada y congelada en los documentos finalizados. Los **diseños
  específicos de cliente** son perfiles regulares que se asignan en la
  ficha del cliente (panel «Diseño de documentos»); los perfiles marcados
  como «específico de cliente» solo actúan a través de esa asignación.
- **Retoques:** la herencia se aplica por grupo de ajustes (márgenes,
  ventana de dirección, zonas bloqueadas, líneas de cabecera/pie,
  tipografía, membrete, bloques, estilo de tabla, textos). Las
  **advertencias** del preflight bloquean la activación hasta confirmarlas
  conscientemente en el diálogo. También nuevo: **líneas de cabecera/pie**
  por página y todos los ajustes del estilo de tabla (rejilla, espaciados,
  colores, repetición de cabecera, énfasis de totales).
