<?php

namespace App;

use PDO;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $settings = require __DIR__ . '/../config/settings.php';
            $path = $settings['db']['path'];
            self::$instance = new PDO("sqlite:$path");
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec("PRAGMA foreign_keys = ON;");

            // Register SQLite functions for Haversine formula
            self::$instance->sqliteCreateFunction('acos', 'acos', 1);
            self::$instance->sqliteCreateFunction('cos', 'cos', 1);
            self::$instance->sqliteCreateFunction('sin', 'sin', 1);
            self::$instance->sqliteCreateFunction('radians', 'deg2rad', 1);
        }
        return self::$instance;
    }
}
