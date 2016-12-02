<?php

/**
 * Parserus for PunBB
 *
 * sva_parserus
 * Copyright (C) 2016 Visman (mio.visman@yandex.ru)
 * License http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

class ParserusEx extends Parserus
{
    private static $exFlagSing = true;
    // Class instance
    private static $exInstance;

    // Start of life
    public function __construct() {
        if (self::$exFlagSing) {
            trigger_error('This is the singleton.', E_USER_ERROR);
        }

        parent::__construct(ENT_HTML5);

        $bbcode = include __DIR__ . '/defaultBBCode.php';
        $this->setBBCodes($bbcode);
    }

    // The end
    public function __destruct() {
    }

    // Singleton
    public static function singleton() {
        if (!isset(self::$exInstance)) {
            $c = __CLASS__;
            self::$exFlagSing = false;
            self::$exInstance = new $c;
            self::$exFlagSing = true;
        }

        return self::$exInstance;
    }

    // Clone forbiden
    public function __clone() {
        trigger_error('Clone is forbiden.', E_USER_ERROR);
    }


}
