# Logo institucional — pendiente

Esta carpeta está preparada para recibir el logo real de UTS. Va dentro de
`public/` (no de `resources/`) porque es un archivo estático que el navegador
debe poder pedir directamente por URL — Vite solo procesa `app.css`/`app.js`,
así que cualquier imagen fuera de esos entry points no sería servible si
viviera en `resources/`.

Mientras tanto, el login usa un isotipo placeholder (cuadrado con degradado
`brand-primary` → `brand-secondary`, levemente rotado, con una "S") y el
nombre del programa en texto ("Ingeniería de Sistemas" / "UTS").

Cuando se disponga del logo, colocar aquí:

| Archivo | Uso | Formato / dimensiones esperadas |
|---|---|---|
| `logo-full.svg` | Login (protagonista, centrado) | SVG preferido; si es PNG, transparente y ≥512×512 (isotipo cuadrado) o ~800×240 (lockup horizontal) |
| `logo-mark.svg` | Sidebar / navbar (uso pequeño) | SVG preferido; si es PNG, transparente y ≥128×128 (isotipo) o ≥320×80 (lockup) |

Y en `favicon-source.png` (esta misma carpeta): PNG cuadrado transparente
≥512×512, a partir del cual se generan los tamaños estándar de favicon
(reemplazando el `public/favicon.svg` provisional).

El login (`resources/views/layouts/guest.blade.php`) ya detecta
automáticamente si `public/images/logo/logo-full.svg` existe: si está,
lo muestra tal cual (sin el contenedor rotado, salvo que encaje bien
visualmente — ajustar entonces a mano); si no existe, sigue mostrando el
placeholder actual. Al recibir el archivo real, verificar que el formato y
las dimensiones coincidan con lo esperado antes de integrarlo definitivamente
en el sidebar (`layouts/app.blade.php`) y el favicon.
