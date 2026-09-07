# Logo institucional — entregado

El logo real de UTS / Ingeniería de Sistemas ya fue entregado e integrado:

| Archivo | Uso | Dimensiones reales |
|---|---|---|
| `logo-full.png` | Login (protagonista, `layouts/guest.blade.php`) | 1600×480, PNG-RGBA transparente |
| `logo-mark.png` | Sidebar / navbar / drawer móvil (`layouts/app.blade.php`) | 1600×1600, PNG-RGBA transparente |
| `favicon-source.png` | Fuente para los favicons generados en `public/` | 1600×1600, PNG-RGBA transparente |

Los favicons ya generados a partir de `favicon-source.png` viven en
`public/favicon-{16,32,180,192,512}.png` (no en esta carpeta).

Ambos layouts (`guest.blade.php` y `app.blade.php`) detectan automáticamente
estos archivos por `file_exists()`; si algún día se reemplazan por versiones
`.svg` con el mismo nombre base, se preferirá el SVG automáticamente.

Los tonos `brand-primary` (`#00447e`) y `brand-secondary` (`#0a7a45`) en
`resources/css/app.css` se extrajeron por muestreo de píxeles de este logo
real — ver `docs/manual-diseno.md` para el detalle del proceso y las
decisiones de contraste/diferenciación tomadas.
