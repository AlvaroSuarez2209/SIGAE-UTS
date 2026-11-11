<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disco de soportes (evidencias, archivos de referencia, plantillas)
    |--------------------------------------------------------------------------
    |
    | Punto 1 del cuadro de seguimiento: el disco de Render es efímero —
    | cualquier archivo subido se pierde en cada despliegue. App\Models\
    | EvidenceFile::disk() y App\Models\DeliverableReferenceFile::disk()
    | (y los 3 puntos de subida: EvidenceWorkspace, Deliverable::
    | attachReferenceFile(), DeliverableTemplate::attachReferenceFile())
    | usan este nombre de disco en vez de "local" fijo, para poder pasar a
    | "s3" (Neon Object Storage) solo cambiando ATTACHMENTS_DISK en el
    | entorno — nunca en código. Local y pruebas se quedan en "local" por
    | defecto (nada que configurar ahí). Ver docs/manual-tecnico.md.
    |
    */

    'attachments_disk' => env('ATTACHMENTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Logo institucional configurable (Prioridad 3)
        |--------------------------------------------------------------------------
        |
        | Público a propósito, a diferencia del disco "local" (privado, usado
        | por las evidencias): el logo lo necesita el login sin sesión, los
        | correos salientes y el PDF — todos fuera de cualquier control de
        | autenticación. root apunta directo dentro de public/, igual que ya
        | sirven hoy los logos estáticos — así no depende de `storage:link`
        | (que este proyecto no tiene configurado ni corre en Render).
        */
        'institution' => [
            'driver' => 'local',
            'root' => public_path('images/institution'),
            // Absoluta, no relativa ("/images/institution"): los correos
            // (reset-password, status-notification) necesitan una URL
            // completa para que el logo cargue fuera de cualquier contexto
            // de navegador — un cliente de correo no tiene "origen actual"
            // contra el cual resolver una ruta relativa.
            'url' => env('APP_URL').'/images/institution',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Neon Object Storage (compatible con S3) — punto 1
        |--------------------------------------------------------------------------
        |
        | use_path_style_endpoint = true por defecto (a diferencia del "false"
        | de fábrica de Laravel): Neon exige direccionamiento "path-style"
        | (https://endpoint/bucket/key), no "virtual-hosted-style"
        | (https://bucket.endpoint/key) — ver docs/manual-tecnico.md.
        |
        | Deliberadamente SIN 'visibility' aquí: Neon no implementa ACL, y
        | agregar 'visibility' (público o privado) solo haría que Laravel
        | intente mandar una cabecera x-amz-acl en cada subida — Neon la
        | rechaza. Quitar la clave del arreglo NO basta por sí sola (el SDK
        | de AWS igual manda "x-amz-acl: private" por defecto, verificado
        | con un cliente S3 real, no solo con Storage::fake); lo que de
        | verdad evita la cabecera es el middleware de
        | AppServiceProvider::stripAclFromNeonUploads(), que quita el
        | parámetro ACL de cada comando antes de firmarlo, sin importar
        | qué visibilidad se pida. Firma SigV4: la usa el SDK de AWS por
        | defecto, nada que configurar aquí.
        */
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
