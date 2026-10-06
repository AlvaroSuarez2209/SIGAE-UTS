<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña inicial del Administrador
    |--------------------------------------------------------------------------
    |
    | Usada una sola vez por Database\Seeders\UserSeeder al crear la cuenta
    | Administrador inicial (admin@uts.edu.co) — nunca se guarda en el
    | repositorio. En local va en tu propio .env; en Render va en el panel
    | de variables de entorno del servicio. Sin este valor, el seeder falla
    | con un mensaje claro en vez de usar cualquier contraseña por defecto.
    |
    */

    'admin_initial_password' => env('ADMIN_INITIAL_PASSWORD'),

];
