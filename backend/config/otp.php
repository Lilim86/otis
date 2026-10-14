<?php

return [
    // Si es true, /api/auth/token (solo usuario y contraseña) se rechaza y el acceso
    // por API exige el código OTP enviado por correo.
    'requerido' => env('OTP_REQUERIDO', true),

    'digitos' => 6,
    'minutos_vigencia' => 5,
    'max_intentos' => 5,
];
