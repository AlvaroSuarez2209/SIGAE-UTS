<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

/**
 * Única definición de la política de complejidad de contraseña, compartida
 * por las 3 rutas que crean o cambian una: "Mi perfil" (autoservicio),
 * "Usuarios" (un Administrador crea o resetea la de otra persona) y
 * "olvidé mi contraseña". Antes cada una mantenía su propia regla por
 * separado — UserForm solo exigía `min:8`, sin mayúscula/minúscula/número —
 * con el riesgo de que las 3 definiciones se desincronizaran en silencio.
 * Cualquier ajuste futuro a la política se hace en este único lugar.
 */
class PasswordPolicy
{
    public static function rules(): Password
    {
        return Password::min(8)->mixedCase()->numbers();
    }
}
