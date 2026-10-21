# Inventario de datos personales — SIGAE-UTS

Insumo para RS-029 ("El sistema deberá aplicar tratamiento de datos
personales de acuerdo con la normativa colombiana y las políticas
institucionales aplicables"). Este documento **no evalúa cumplimiento
legal** — eso corresponde a la institución — solo describe, a partir del
código y las migraciones reales, qué datos existen, quién puede verlos y
qué controles ya están construidos frente a cuáles no. No se modificó
ningún código, seeder ni dato para escribirlo.

Convención usada abajo: **Existe** = verificado en el código actual.
**No existe hoy** = se buscó explícitamente y no se encontró ningún
mecanismo — no es una opinión, es el resultado de la búsqueda.

## 1. Qué datos personales almacena el sistema

| Tabla.columna | Dato | Finalidad |
|---|---|---|
| `users.name` | Nombre completo | Identificar a la persona en toda la interfaz (listados, informes, correos) |
| `users.document_number` | Número de documento | Identificador institucional único; exigido por la importación masiva y los formularios de Usuarios |
| `users.document_type` | Tipo de documento (CC/CE/TI/PA) | Clasificación del documento anterior |
| `users.email` | Correo institucional | Identificador de inicio de sesión y destino de las notificaciones |
| `users.password` | Contraseña (hash bcrypt) | Autenticación. Nunca se guarda en texto plano (cast `hashed`) |
| `users.program_unit_id` | Programa/unidad de adscripción | Agrupar a la persona para filtros de Coordinación/Informes — **informativo**, no determina distribución real |
| `users.is_active` | Estado de la cuenta | Controla el acceso; no es un dato personal en sí, pero es un dato *sobre* la persona |
| `evidences.exemption_reason` | Motivo de exención (texto libre) | Explicar por qué un docente no debe cumplir una entrega. **Puede contener datos sensibles** (ej. salud, licencia de maternidad — ambos aparecen como ejemplos reales en los comentarios del propio código, `Evidence::markExempt()`) — es texto libre sin ninguna restricción de contenido |
| `evidence_versions.description` | Texto de la evidencia (cuando el tipo de evidencia incluye "texto") | Contenido académico del docente — texto libre; puede contener datos personales incidentales si la persona los incluye, sin que el sistema lo module de ninguna forma |
| `observations.body` | Observación de una revisión (texto libre) | Explicar una aprobación/devolución — texto libre, mismo riesgo incidental que arriba |
| `evidence_files.*` (contenido del archivo) | Cualquier archivo que el docente adjunte como evidencia | El **contenido** de estos archivos está completamente fuera del control del sistema (puede incluir cualquier dato personal, propio o de terceros, según lo que el docente decida subir) — el sistema solo controla extensión/tamaño/cantidad, nunca el contenido |
| `audit_logs.ip_address` | Dirección IP de quien ejecutó la acción | Seguridad y trazabilidad (ver `docs/manual-tecnico.md` §5.16) |
| `audit_logs.metadata` | Valores anteriores/nuevos de campos modificados (`changes`/`previous`) | Trazabilidad de cambios — puede incluir valores anteriores de `name`, `document_type`, `program_unit_id`, etc. (nunca de `password`/`remember_token`, redactados explícitamente, ver `App\Models\Concerns\Auditable`) |
| `sessions.ip_address`, `sessions.user_agent` | IP y navegador de cada sesión activa | Mecanismo de sesión nativo de Laravel (`SESSION_DRIVER=database`) — nunca se muestra en ninguna pantalla de la aplicación |
| Plantilla de importación masiva (archivo subido, no persistido como tabla propia) | `tipo_documento`, `numero_documento`, `nombre_completo`, `correo_institucional`, `codigo_programa` | Mismos datos que `users`, en tránsito dentro de un archivo Excel/CSV que un Administrador sube — ver `App\Services\TeacherImport\TeacherImportService::REQUIRED_HEADERS` |

**Lo que el sistema NO almacena** (confirmado revisando `app/Models/User.php`
y el resto de migraciones): fecha de nacimiento, dirección, teléfono,
género, afiliación a seguridad social, ni ningún otro dato además de los
listados arriba. No hay tabla ni columna de "datos de contacto"
adicionales.

## 2. Quién puede verlos, según roles y policies reales

| Dato | Quién | Base en el código |
|---|---|---|
| `users.*` completo (incluye `document_number`/`document_type`) | Solo Administrador | `App\Policies\UserPolicy` (`viewAny`/`view`/`update`/`create` exigen `hasRole(Administrator)`); pantalla `admin/users` bajo `role:administrator` |
| `users.name`, `users.email` (propios) | La propia persona | `Profile.php` — nombre editable; correo de solo lectura |
| `users.document_number`/`document_type`/`program_unit_id` (propios) | La propia persona, solo lectura | `Profile.php`: "se muestran de solo lectura... si necesitas corregir alguno, debe hacerlo un Administrador" |
| `users.name` de **otras** personas (nunca `document_number`/`document_type`) | Administrador, Coordinación, Auditor (informes/paneles), Líder (solo de los docentes en su ámbito vigente) | Confirmado: `document_number` solo aparece en `user-form.blade.php` y `profile.blade.php` (grep sobre `resources/views/`) — ningún informe ni panel lo expone |
| `evidences.exemption_reason` | El propio docente, Administrador, Coordinación, y el Líder dentro de su ámbito | `EvidencePolicy::markExempt()`/`removeExemption()`; visible en pantalla según el manual de usuario, sección "Docente" y "Líder" |
| `evidence_versions.description`, archivos adjuntos | El propio docente, y quien pueda revisar esa evidencia (Administrador/Coordinación/Auditor siempre; Líder dentro de su ámbito) | `Evidence::isViewableBy()`/`isReviewableBy()` (`App\Models\Evidence`) |
| `audit_logs.*` (incluida la IP) | Administrador, Auditor | `routes/web.php`: `role:administrator,auditor` sobre `/admin/audit-logs`; de solo lectura, sin ninguna ruta de edición/borrado |
| `sessions.*` | Nadie desde la interfaz | No existe ninguna pantalla que lo muestre — solo accesible con acceso directo a la base de datos (Neon) |

## 3. Qué se registra en auditoría

`App\Models\AuditLog::record()` guarda, en cada entrada: el actor
(`user_id`, nulo si es el *scheduler* o un intento de login fallido), la
`action`, el registro afectado (`auditable_type`/`auditable_id`), la
**IP** (`request()->ip()`) y la fecha. Acciones reales confirmadas en el
código: `login`, `login_failed`, `login_blocked_inactive`, `logout`,
`created`, `updated` (automático vía el trait `Auditable`, en todos los
modelos que lo usan — `User`, `Evidence`, `Deliverable`, `AcademicPeriod`,
`Leadership`, `TeacherAssignment`, `InstitutionSettings`, los catálogos),
`evidence_marked_exempt`, `evidence_exemption_removed`,
`evidence_marked_overdue` (sin actor — lo ejecuta el *scheduler*).

**Qué NO se audita**: ningún acceso de solo **lectura** (ver una pantalla,
descargar un archivo, abrir un informe) — solo inicio/cierre de sesión y
las mutaciones (creación/edición/transiciones de evidencia) quedan
registradas. Ver también `docs/manual-tecnico.md` §5.16 para la base
legal que el proyecto ya documenta para la IP (Ley 1581 de 2012).

## 4. Retención — **no existe ninguna política hoy**

Se buscó explícitamente en `app/Console/Commands/`, `routes/console.php`
y todas las migraciones: **no hay ningún comando, tarea programada ni
mecanismo que borre o archive por antigüedad** ninguna de las tablas
anteriores. Esto incluye `audit_logs` (ya documentado así en
`docs/manual-tecnico.md` §5.16: *"el código no define ninguna política
de retención ni purga... toda entrada queda indefinidamente"*) y aplica
igual a `users`, `evidences` y el resto — nada se purga nunca.

La única excepción real, y es del framework, no de este proyecto: la
tabla `sessions` sí tiene un mecanismo de expiración/limpieza
(`SESSION_LIFETIME`, y el "sweep" aleatorio de Laravel, `lottery` en
`config/session.php`) — pero expira la *sesión*, no borra ningún dato
personal de `users`.

## 5. Qué sale por correo, y hacia qué servicios externos

**Contenido de los correos** (`app/Notifications/`, 14 clases, canal
`mail` únicamente — nunca el canal `database` de Laravel, ver
`docs/diccionario-datos.md`): nombre del destinatario, nombre del
entregable, actividad o compromiso transversal, periodo, y el estado o
motivo de la notificación (asignación, envío, aprobación, devolución,
vencimiento, exención/reverso, asignación de liderazgo, enlace para
restablecer contraseña). El `document_number`/`document_type` **nunca**
aparece en ningún correo (confirmado revisando `resources/views/mail/`
y `resources/views/emails/`).

**Servicios externos reales** (confirmados en `composer.json` y
`app/Providers/AppServiceProvider.php` — no se encontró ningún otro,
tampoco analítica ni rastreo de terceros):

| Servicio | Qué recibe | Rol |
|---|---|---|
| **Neon** (Postgres) | Absolutamente todo lo de la sección 1 — es donde vive la base de datos completa | Procesador de datos (alojamiento de la base) |
| **Render** | El código de la aplicación en ejecución (ve todo dato en tránsito/memoria) y, mientras el contenedor esté vivo, los archivos de evidencias en su disco efímero (ver `docs/respaldo-restauracion.md`, sección (b)) | Procesador de datos (alojamiento de la aplicación) |
| **Brevo** | Dirección de correo + nombre del destinatario + el contenido descrito arriba, por cada correo enviado — solo en el despliegue de Render (`MAIL_MAILER=brevo`); en local se usa Mailpit, que nunca sale de la máquina | Procesador de datos (envío de correo) |

No se encontró ningún otro destino externo (sin Sentry, sin analítica,
sin ningún otro proveedor en `composer.json`/`package.json`).

## 6. Lo que NO existe hoy (marcado explícitamente, no inferido)

- **Política de retención**: no existe (sección 4).
- **Mecanismo de consentimiento**: no existe. No hay autorregistro —
  todas las cuentas las crea un Administrador (`UserForm`/importación
  masiva) — así que no hay ninguna pantalla de "acepto el tratamiento de
  mis datos" en ningún punto del flujo, ni al crear la cuenta ni al
  iniciar sesión por primera vez.
- **Política de tratamiento de datos personales integral**: no existe
  como tal. Lo único que el repositorio documenta explícitamente sobre
  base legal es un párrafo acotado a la dirección IP en auditoría
  (`docs/manual-tecnico.md` §5.16, que cita la Ley 1581 de 2012) — no
  cubre el resto de los datos de la sección 1 (nombre, documento, correo,
  motivos de exención, contenido de evidencias).
- **Anonimización o seudonimización**: no existe para ningún dato en
  reposo. Lo único parecido que el código ya hace es evitar **nombres**
  en los *nombres de archivo* descargables de los informes (usa el ID
  del docente en vez de su nombre — ver `ReportBuilder`, campo
  `file_identifier`) — pero el **contenido** del informe sí incluye el
  nombre completo con normalidad.
- **Cifrado de datos personales en reposo, a nivel de aplicación**: no
  existe ningún cifrado de columna (`password` es un hash, no
  cifrado/reversible — es la única excepción real, y es por diseño).
  Lo que proteja los datos en reposo dentro de Neon depende de los
  controles de esa plataforma, no de este código.
- **Mecanismo para que una persona solicite la eliminación de sus datos**
  ("derecho al olvido"/habeas data): no existe ninguna función de borrado
  de una cuenta — solo desactivación (`is_active`), que deliberadamente
  **conserva** todo el histórico (RF-005/RS-024). No hay ninguna otra vía
  en el código para eliminar los datos personales de una persona.
