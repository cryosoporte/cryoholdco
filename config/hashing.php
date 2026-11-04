<?php

// Solo se sobrescriben estas opciones; el resto viene de la configuración por defecto de Laravel.
return [

    // Las contraseñas de ICTC.USERS son bcrypt costo 10 y las comparte el sistema anterior.
    // Con rehash_on_login, Laravel reescribiría USERS.PASSWORD al iniciar sesión (y solo en
    // la fila de una empresa), así que se desactiva.
    'rehash_on_login' => false,

];
