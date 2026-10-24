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

    /*
    |--------------------------------------------------------------------------
    | Ciclo de demostración (Database\Seeders\DemoCycleSeeder)
    |--------------------------------------------------------------------------
    |
    | DEMO_CYCLE_TEACHER_EMAIL: el correo del docente YA existente sobre el
    | que se crean los entregables de prueba — nunca un valor hardcodeado en
    | el seeder (sería el correo real de una persona en el repositorio). Sin
    | este valor, el seeder falla con un mensaje claro. El líder de la
    | demostración SÍ es sintético y se crea solo (lider.demo@sigae-uts.local,
    | ver DemoCycleSeeder::DEMO_LEADER_EMAIL) — no identifica a nadie real.
    |
    */

    'demo_cycle_teacher_email' => env('DEMO_CYCLE_TEACHER_EMAIL'),

    'demo_cycle_leader_password' => env('DEMO_CYCLE_LEADER_PASSWORD'),

];
