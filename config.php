<?php
declare(strict_types=1);

const APP_NAME = 'RuangHadir';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'absensi_xampp_app';
const DB_USER = 'root';
const DB_PASS = '';
const WORK_START = '08:00:00';

function db(bool $withoutDatabase = false): PDO
{
    static $connection;
    if (!$withoutDatabase && $connection instanceof PDO) {
        return $connection;
    }

    $database = $withoutDatabase ? '' : ';dbname=' . DB_NAME;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . $database . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    if (!$withoutDatabase) {
        $connection = $pdo;
    }

    return $pdo;
}