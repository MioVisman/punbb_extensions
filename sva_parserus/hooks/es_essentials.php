<?php if (!defined('FORUM')) die();

/**
 * Parserus for PunBB
 *
 * sva_parserus
 * Copyright (C) 2016 Visman (mio.visman@yandex.ru)
 * License http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

spl_autoload_register(
    function($className)
    {
        $path = null;

        if ($className == 'Parserus') {
            $path = __DIR__ . '/../vendor/MioVisman/Parserus/Parserus.php';
        } else if ($className == 'ParserusEx') {
            $path = __DIR__ . '/../ParserusEx.php';
        }

        if (null !== $path && file_exists($path)) {
            include $path;
        }
    }
);
