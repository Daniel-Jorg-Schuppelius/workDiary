---
title: "Imagen corporativa"
topic: admin.branding
version: 3
keywords:
    - marca blanca
    - white label
    - identidad corporativa
    - diseño corporativo
    - logotipo
    - subir logotipo
    - logotipo oscuro
    - colores de marca
    - datos de la empresa
    - pie de página
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
    - invoices.manage
---

En la imagen corporativa define la apariencia de la organización actual:
logotipos, nombre visible, datos de la empresa y colores. Los ajustes se
aplican a la interfaz web y a los documentos PDF generados (por ejemplo,
facturas) y se guardan por organización.

Los **logotipos** se suben en la parte superior de esta página, en la
tarjeta **Logotipos**, por separado del formulario de abajo; no hace
falta guardar el formulario para ello:

- **Logotipo (variante clara)** es el logotipo predeterminado.
- **Logotipo (variante oscura, opcional)** aparece en el esquema de
  color oscuro y en los PDF para los que elija la variante oscura. Si
  falta una de las dos variantes, se usa la otra; sin logotipo propio
  aparece el logotipo de WorkDiary.
- Elija un archivo y pulse **Subir**: la nueva imagen sustituye a la
  anterior de inmediato. **Eliminar** borra el logotipo tras una
  confirmación.
- Se admiten PNG, JPG y WEBP hasta el tamaño indicado bajo el campo
  (2 MB por defecto); SVG no se acepta.

En el formulario de abajo mantiene:

- **Datos maestros**: nombre visible de la aplicación (título del
  navegador y encabezado) y eslogan.
- **Contacto**: dirección, teléfono, correo electrónico y web.
- **Aspectos legales y pie de PDF**: NIF-IVA, número fiscal, titular de
  la cuenta, banco, IBAN, BIC, registro mercantil y el texto de pie de
  página de los documentos PDF.
  Indique el registro mercantil junto con el juzgado registral, por
  ejemplo «HRB 12345, Amtsgericht Musterstadt»; la factura electrónica lo
  incluye como identificador de registro de la organización (BT-30),
  congelado al emitir.
- **Colores**: color principal y color de acento; sustituyen los colores
  base de la interfaz.
- **Configuración de PDF por tipo de documento**: variante del logotipo
  (clara, oscura o sin logotipo) y si aparecen **Contacto en el
  encabezado** y **Pie de página**.

El diseño, el papel con membrete y el estilo de las tablas de los PDF se
ajustan en el diseño de documentos; el aviso al final de la página lleva
allí si tiene permiso para abrirlo.

Nota: IBAN y BIC se comprueban y se normalizan al guardar (sin espacios,
en mayúsculas). Los campos vaciados vuelven a los valores
predeterminados del sistema. Se requiere el permiso «Editar la marca»
(los administradores lo tienen siempre); solo modifica la imagen
corporativa de su propia organización.
