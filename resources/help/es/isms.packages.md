---
title: "Paquetes de auditoría y enlaces para auditores"
topic: isms.packages
version: 2
keywords:
    - acceso para auditores
    - enlace de auditor
    - evidencias de auditoría
    - dossier de evidencias
    - instantánea de datos
    - congelar datos
    - comprobación de integridad
    - verificar hash
    - acceso de solo lectura
    - exportación de auditoría
audience: []
modules:
    - module.isms
related:
    - isms.audits
    - isms.conformity
    - isms.overview
    - glossary.core
---

Los **Paquetes de auditoría** congelan el estado de los datos del SGSI a
una fecha de referencia como instantánea, como base fiable para los
auditores externos.

Proceso habitual:

1. **Crear paquete**: título, fecha de referencia, alcance y,
   opcionalmente, norma y edición como filtro. El paquete empieza como
   «Borrador».
2. **Finalizar**: genera la instantánea JSON con hash SHA-256 y deja
   constancia de quién finalizó y cuándo.
3. **Verificar integridad**: compara en cualquier momento el archivo con
   el hash guardado.
4. **Crear enlace de auditor**: acceso de duración limitada (1–90 días),
   revocable en cualquier momento. El enlace abre una **vista web de
   solo lectura** del paquete finalizado, navegable y con el hash
   SHA-256 en la portada; desde allí se puede descargar el archivo JSON
   del paquete. Siempre se muestra el estado **congelado** de la
   finalización, nunca los registros en curso.

Contenido del paquete: DdA, registro de riesgos (últimas evaluaciones
netas aprobadas), lista de medidas con sus vínculos, estado de
conformidad, auditorías con hallazgos y acciones correctivas, revisiones
por la dirección aprobadas, inventario de software.

Riesgos y acciones irreversibles:

- **Los paquetes finalizados son inmutables**: la edición y la
  eliminación están bloqueadas.
- La fecha de referencia es la fecha de informe documentada; el estado
  de los datos corresponde al **momento de la finalización** (sin
  reconstrucción retroactiva).
- El **enlace de auditor completo se muestra una sola vez** (al
  crearlo); después solo es posible revocarlo.

Permisos: la consulta requiere permisos de lectura del SGSI; la creación
y la gestión requieren permisos de gestión del SGSI. La vista del
auditor y la descarga funcionan mediante un enlace protegido, sin cuenta
de WorkDiary.
