---
title: "Diccionario"
topic: admin.text-corrections
version: 1
audience:
    - admin
---

El **diccionario** corrige automáticamente errores ortográficos
recurrentes — de forma determinista y sin IA. Cada entrada es un par
«incorrecto → correcto».

- **Efecto**: al construir los textos de posición generados (traspasos de
  facturación, borradores de factura, vista previa de factura). Los
  registros de tiempo permanecen sin cambios.
- **Coincidencia**: solo palabras o frases completas, sin distinguir
  mayúsculas; se conserva la grafía de la corrección (MAYÚSCULAS siguen en
  MAYÚSCULAS, el inicio de frase se escribe con mayúscula).
- **Aprendizaje**: cuando un texto de posición se corrige manualmente, la
  aplicación detecta sustituciones de palabras 1:1 y ofrece «recordarlas» —
  solo se añaden tras confirmación, nunca en silencio. Estas entradas
  aparecen como «Aprendido».
- **Desactivar en lugar de eliminar**: una entrada desactivada no tiene
  efecto, pero sigue siendo trazable.

La gestión requiere el permiso de configuración financiera, porque las
entradas modifican el contenido de las facturas.
