---
title: "Reclamaciones y garantía"
topic: claims.overview
version: 1
audience: []
related:
    - documents.manage
---

El módulo gestiona reclamaciones, casos de garantía, decisiones de
cortesía comercial y devoluciones como expedientes trazables — desde la
entrada, pasando por la evaluación y la decisión, hasta las
consecuencias en almacén, servicio y facturación.

**Expediente:** Cada reclamación recibe su propio número (REK-…),
plazos, responsables y vínculos con encargo, proyecto, activo, artículo,
número de serie, factura y proveedor. Los módulos especializados siguen
siendo rectores — el expediente vincula, no sobrescribe nada.

**Evaluación y decisión:** Tipo de derecho (garantía comercial, garantía
legal o contractual, cortesía comercial, daño de transporte, uso
indebido, error del proveedor) con justificación obligatoria. La base
fáctica (verificación de números de serie, plazos, fecha de denuncia de
defectos según § 377 HGB en el caso B2B) se congela como snapshot. Las
decisiones requieren una evaluación activa y son auditables;
deliberadamente no existe una decisión automática sobre el derecho.

**Devoluciones (RMA):** Las devoluciones reciben un número RMA, la
entrada de mercancía queda en cuarentena (stock bloqueado/de
inspección) y la inspección documenta el dictamen y el cotejo de números
de serie. La decisión de destino (reincorporación al stock, reparación,
devolución al proveedor, desguace, eliminación) se contabiliza de forma
idempotente a través del diario de almacén.

**Consecuencias comerciales:** La reducción de precio, el abono, la
anulación, la corrección, la factura sustitutiva o el reembolso se
proponen, se aprueban según el principio de los cuatro ojos y solo
entonces se ejecutan. Los documentos se generan en el módulo de
facturación (abono/anulación como borrador) con un indicador de motivo
estructurado — no existe un tipo de documento propio.

**Regreso contra el proveedor:** Derecho propio frente al proveedor
inicial, con referencia al pedido y a la factura de entrada, plazo de
respuesta y retorno de costes.

**Análisis:** El informe de calidad muestra la tasa, las causas, los
artículos afectados, los proveedores, los costes, la duración de
tramitación y los defectos recurrentes; los estados del informe pueden
congelarse como constancia.

**Portal de clientes:** Los clientes ven el estado de sus propios casos
y pueden remitir documentación adicional — las evaluaciones internas y
los importes permanecen invisibles.
