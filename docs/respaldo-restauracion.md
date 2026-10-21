# Respaldo y restauración — SIGAE-UTS

**Estado actual, para que quede explícito desde el principio:** este
repositorio **no tiene ningún mecanismo de respaldo automatizado**. No
hay un cron, un comando Artisan ni un servicio externo que copie la base
de datos o los archivos de evidencias por su cuenta — se buscó en
`routes/console.php`, `app/Console/Commands/`, `.github/` y
`docs/manual-tecnico.md` y no existe nada de esto hoy. Todo lo que sigue
es: (a) el procedimiento **manual** recomendado para hacerlo contra el
entorno de pruebas real (Render + Neon + Brevo), con comandos reales; y
(b) lo que la plataforma (Neon) ya ofrece por su cuenta, aclarado como
tal en cada punto. Ninguna parte de este documento implica que algo
pase automáticamente salvo que se diga explícitamente.

Este documento no modifica código, seeders ni datos — es exclusivamente
el procedimiento.

## (a) Base de datos PostgreSQL (Neon)

### Por qué el host directo, no el pooled

El resto de la aplicación usa el host **pooled** de Neon (`DB_HOST`,
PgBouncer en modo *transaction pooling*). `pg_dump`/`pg_restore`
necesitan mantener una sola sesión de backend coherente durante toda la
operación — el mismo motivo por el que `docker/start.sh` ya corre
`migrate --force` contra la conexión `pgsql_migrate` (`DB_HOST_MIGRATE`,
el host **sin** `-pooler`) en vez de la pooled (ver
`docs/manual-tecnico.md` §8.1). Un respaldo o una restauración deben
usar ese mismo host directo, por la misma razón.

### Variables (sin valores reales — sácalos del panel de Neon o de tu `.env`/panel de Render)

```bash
# Nunca pegues los valores reales en un archivo que se vaya a commitear.
export PGHOST="<host-directo-de-neon-sin--pooler>"   # = DB_HOST_MIGRATE
export PGPORT=5432
export PGDATABASE="<nombre-de-la-base>"               # = DB_DATABASE
export PGUSER="<usuario>"                             # = DB_USERNAME
export PGPASSWORD="<password>"                        # = DB_PASSWORD
export PGSSLMODE=require                              # Neon lo exige
```

Usar las variables `PG*` estándar de `libpq` evita repetir `--host`,
`--dbname`, etc. en cada comando — tanto `pg_dump` como `psql`/`pg_restore`
las leen automáticamente.

### Respaldo

```bash
# Formato "custom" (comprimido, restaurable selectivamente con pg_restore).
# --no-owner/--no-privileges: Neon administra sus propios roles; sin esto,
# una restauración contra un proyecto de Neon distinto (o una base nueva
# con otro propietario) fallaría al intentar reasignar el owner original.
pg_dump \
  --format=custom \
  --no-owner \
  --no-privileges \
  --file="sigae-uts_$(date +%Y%m%d_%H%M%S).dump"
```

Alternativa en SQL plano (más portable, más pesado, restaurable con
`psql` en vez de `pg_restore`):

```bash
pg_dump --format=plain --no-owner --no-privileges \
  --file="sigae-uts_$(date +%Y%m%d_%H%M%S).sql"
```

**Qué incluye**: todo el esquema (tablas de dominio + las de
infraestructura de Laravel: `cache`, `jobs`, `sessions`, etc. — ver
`docs/diccionario-datos.md`) y todos los datos, tal como existan en el
momento exacto de correr el comando. **Qué NO incluye**: los archivos
de evidencias (viven en el disco del contenedor, no en Postgres — ver
sección (b)).

### Restauración (siempre en una base nueva, nunca encima de la real)

```bash
# 1. Crear una base nueva para probar — NUNCA restaurar directamente
#    sobre la base que está en uso.
psql -d postgres -c "CREATE DATABASE sigae_uts_restore_test;"

# 2a. Si el volcado es --format=custom:
pg_restore \
  --dbname=sigae_uts_restore_test \
  --no-owner \
  --no-privileges \
  sigae-uts_<fecha>.dump

# 2b. Si el volcado es --format=plain (SQL):
psql -d sigae_uts_restore_test -f sigae-uts_<fecha>.sql
```

Para restaurar **sobre la base real** (un incidente real, no una
prueba), el mismo `pg_restore`/`psql` apuntando a `PGDATABASE` en vez de
`sigae_uts_restore_test` — pero solo después de confirmar la restauración
de prueba (ver checklist) y avisando que la aplicación quedará
inconsistente mientras dura (recomendado: activar el modo de
mantenimiento de Laravel, `php artisan down`, antes de empezar).

### Lo que Neon ya ofrece por su cuenta (plataforma, no este repositorio)

Neon mantiene su propio historial de cambios para "point-in-time
restore" dentro de una ventana que depende del plan contratado — **no
se afirma aquí un número de días concreto**, porque no es algo que este
repositorio controle ni pueda verificar por código; confírmalo en el
panel de Neon del proyecto real antes de asumir cuánto tiempo atrás se
puede volver sin tener un `pg_dump` propio. Esto es una red de
seguridad de la plataforma, **no** sustituye tener tus propios volcados
guardados fuera de Neon (ver (c) — qué pasa si la cuenta de Neon se
pierde o se suspende).

## (b) Archivos de evidencias

### Dónde viven hoy (según `config/filesystems.php`)

```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),   // storage/app/private
    'serve' => true,
    ...
],
```

`App\Models\EvidenceFile::disk()` usa explícitamente este disco
`'local'` — es donde `EvidenceWorkspace::saveDraft()` guarda cada
archivo adjunto (`evidence/{version_id}/{nombre-aleatorio}`). No es un
disco público: no hay URL directa, toda descarga pasa por
`EvidenceFileDownloadController` (verifica permisos con
`EvidenceFilePolicy` en cada solicitud).

### ⚠️ Riesgo real en Render (disco efímero)

Render, en su plan gratuito, **no garantiza que el disco del contenedor
sea persistente entre reinicios**. El propio proyecto ya documenta este
riesgo para otro caso (el comando `db:seed-if-empty`, ver
`docs/manual-tecnico.md`): *"el contenedor puede recrearse desde la
imagen al 'despertar' — no hay garantía de que sea el mismo disco de
antes de dormirse"*. Esto aplica exactamente igual a
`storage/app/private`: **cualquier archivo de evidencia subido durante
la vida de un contenedor puede perderse por completo** la próxima vez
que Render reinicie o redespliegue ese servicio, sin ningún aviso
dentro de la aplicación. A diferencia de la base de datos (que vive en
Neon, un servicio aparte), **no hay ninguna persistencia real de
archivos en este despliegue de pruebas**. Esto no es un bug que se
pueda corregir sin agregar infraestructura nueva (ej. un volumen
persistente de pago, o mover el disco a S3/un bucket compatible — el
driver `s3` ya existe en `config/filesystems.php`, pero sus credenciales
están vacías en `.env.example` y ningún código del proyecto lo usa hoy)
— se documenta aquí como lo que es: una limitación conocida del entorno
de pruebas actual, no de la aplicación en sí.

### Cómo copiarlos (mientras el contenedor esté arriba)

**Opción A — con terminal del servicio (pestaña "Shell" de Render, si
tu plan la ofrece):**

```bash
# Desde la shell del servicio en Render:
tar -czf /tmp/evidencias_$(date +%Y%m%d).tar.gz -C storage/app/private evidence
# Descarga ese archivo con la opción de descarga de la propia shell web
# de Render (o `scp`/`render ssh` si tu plan y la CLI de Render lo
# permiten) — confirma qué ofrece tu plan actual, no se asume aquí.
```

**Opción B — sin terminal (plan sin esa pestaña, o sin acceso a ella):**
no existe hoy ningún endpoint ni comando en el proyecto para exportar
todos los archivos de una vez — la única vía que sí existe es la
descarga individual, autenticado, por
`/evidence-files/{id}/download` (un archivo a la vez). Es una
**recomendación**, no algo implementado: si el volumen de archivos crece
lo suficiente para que esto sea impráctico, valdría la pena construir un
comando Artisan dedicado que empaquete y descargue todo en un `.zip` —
fuera del alcance de esta tarea (no se modifica código aquí).

### Cómo restaurarlos

```bash
# Subir el .tar.gz de vuelta (vía la misma shell del servicio) y extraerlo
# en el mismo punto exacto:
tar -xzf evidencias_<fecha>.tar.gz -C storage/app/private
```

Los nombres de archivo en disco (`stored_name`, columna de
`evidence_files`) son los que ya están dentro del `.tar.gz` — no hace
falta reconstruir ninguna ruta a mano, mientras la base de datos
restaurada (ver (a)) corresponda al mismo momento que este respaldo de
archivos (ver advertencia de consistencia en el checklist).

## (c) Protección de las copias

| Aspecto | Qué existe hoy | Qué es solo recomendación |
|---|---|---|
| **Dónde se guardan** | Nada — ningún volcado ni copia de archivos se genera ni se guarda automáticamente en ningún lugar | Guardar cada `.dump`/`.tar.gz` fuera de Render y de Neon (ej. el equipo del moderador/directora, un bucket privado), nunca solo en la máquina de una persona |
| **Quién accede** | El acceso a la base real ya está acotado por las credenciales de Neon (`DB_PASSWORD`) y a la shell del contenedor por el login de Render — ambos ya gestionados fuera de este repositorio | Restringir explícitamente quién tiene esas credenciales de Neon/Render a quien de verdad necesite poder respaldar/restaurar, no todo el equipo |
| **Cifrado** | Neon cifra en reposo y en tránsito dentro de su propia infraestructura (una garantía de la plataforma, no de este código) | Cifrar también cada `.dump`/`.tar.gz` una vez fuera de Neon, antes de guardarlo donde sea (ej. `gpg --symmetric` o el cifrado nativo del bucket donde se guarde) — nada en el repositorio hace esto hoy |
| **Retención** | Ninguna — no hay ninguna política de cuántas copias conservar ni por cuánto tiempo | Definir un número fijo (ej. conservar las últimas 4 semanales + el último de cada mes) y borrar lo demás, para no acumular copias indefinidamente ni perder visibilidad de cuál es la más reciente válida |
| **Rotación** | Ninguna | Nombrar cada copia con fecha (ya reflejado en los comandos de arriba, `$(date +%Y%m%d_%H%M%S)`) y aplicar la política de retención anterior de forma consistente, a mano o con un script simple |

## Lista de verificación para probar la restauración

1. Generar un respaldo de la base real (sección (a)) y, si aplica,
   de los archivos (sección (b)) — anotar la hora exacta.
2. Antes de restaurar, contar las filas de cada tabla de dominio en la
   base **original** (ver `docs/diccionario-datos.md` para la lista
   completa de tablas):
   ```sql
   SELECT 'users' AS tabla, count(*) FROM users
   UNION ALL SELECT 'evidences', count(*) FROM evidences
   UNION ALL SELECT 'evidence_versions', count(*) FROM evidence_versions
   UNION ALL SELECT 'evidence_files', count(*) FROM evidence_files
   UNION ALL SELECT 'deliverables', count(*) FROM deliverables
   UNION ALL SELECT 'audit_logs', count(*) FROM audit_logs;
   -- agrega el resto de tablas de dominio si quieres cobertura completa
   ```
3. Restaurar ese volcado en una base **nueva** (`sigae_uts_restore_test`,
   nunca la real) siguiendo la sección (a).
4. Correr la misma consulta de conteos contra `sigae_uts_restore_test`.
5. Si respaldaste archivos, extraer el `.tar.gz` en una carpeta de
   prueba (no sobre `storage/app/private` real) y elegir al azar un
   `evidence_files.stored_name` de la base restaurada — confirmar que
   ese archivo exacto existe dentro del `.tar.gz` extraído.

**Criterio de éxito**: los conteos del paso 2 y el paso 4 son
**idénticos**, tabla por tabla, y el archivo del paso 5 se encuentra y
abre correctamente (no está corrupto ni vacío). Si algún conteo no
coincide, el volcado no es confiable y no debe usarse para restaurar la
base real ante un incidente — hay que investigar antes de dar la
restauración por buena.

**Advertencia de consistencia**: el respaldo de la base de datos y el de
los archivos se generan en momentos distintos (dos comandos separados,
sección (a) y (b)) — si entre uno y otro un docente sube una evidencia
nueva, la base restaurada podría referenciar un `evidence_files.stored_name`
que no esté en el `.tar.gz` de archivos (o viceversa). Para una
restauración real ante un incidente, generar ambos respaldos lo más
cerca posible en el tiempo, idealmente con la aplicación en modo de
mantenimiento (`php artisan down`) mientras se generan los dos.
