---
title: "Exportación de tiempos y traspaso a nómina"
topic: exports.payroll
version: 1
audience: []
related:
    - admin.surcharge-rules
    - finance.transfers
    - glossary.core
---

La exportación de tiempos entrega a la nómina los datos mensuales
aprobados, de forma trazable y reproducible con rastro de auditoría. El
flujo típico: los empleados envían el mes («enviado»), la dirección del
equipo lo aprueba («aprobado») y tras la exportación el mes queda
**bloqueado**; la exportación pasa por «en preparación» → «lista» y se
marca como «transmitida» o «rechazada». Actualmente el perfil productivo
es la **exportación CSV genérica** (empleado, concepto salarial,
cantidad, unidad, período); el **perfil DATEV** es solo una preparación
y no un formato DATEV certificado. Solo se puede exportar si todas las
aprobaciones mensuales afectadas están aprobadas o bloqueadas, y cada
exportación lleva un **hash SHA-256** reproducible: tras correcciones se
crea una exportación nueva y la antigua se marca como «sustituida», sin
sobrescribir nada en silencio.
