<?php

namespace Surcouf\Cookbook\Database;

use mysqli_stmt;

if (!defined('CORE2'))
    exit;

final class MysqliStmtParamBuilder
{

    public static function BindParams(mysqli_stmt $stmt, string $types, array $items): void
    {
        switch(count($items)) {
            case 0:
                return;
            case 1:
                self::Bind1($stmt, $types, $items);
                return;
            case 2:
                self::Bind2($stmt, $types, $items);
                return;
            case 3:
                self::Bind3($stmt, $types, $items);
                return;
            case 4:
                self::Bind4($stmt, $types, $items);
                return;
            case 5:
                self::Bind5($stmt, $types, $items);
                return;
            case 6:
                self::Bind6($stmt, $types, $items);
                return;
            case 7:
                self::Bind7($stmt, $types, $items);
                return;
            case 8:
                self::Bind8($stmt, $types, $items);
                return;
            case 9:
                self::Bind9($stmt, $types, $items);
                return;
            default:
                self::Bind10($stmt, $types, $items);
                return;
        }
    }

    private static function Bind1(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0]);
    }

    private static function Bind2(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1]);
    }

    private static function Bind3(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1], $items[2]);
    }

    private static function Bind4(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1], $items[2], $items[3]);
    }

    private static function Bind5(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1], $items[2], $items[3], $items[4]);
    }

    private static function Bind6(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1], $items[2], $items[3], $items[4], $items[5]);
    }

    private static function Bind7(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1], $items[2], $items[3], $items[4], $items[5], $items[6]);
    }

    private static function Bind8(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1], $items[2], $items[3], $items[4], $items[5], $items[6], $items[7]);
    }

    private static function Bind9(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1], $items[2], $items[3], $items[4], $items[5], $items[6], $items[7], $items[8]);
    }

    private static function Bind10(mysqli_stmt $stmt, string $types, array $items): void {
        $stmt->bind_param($types, $items[0], $items[1], $items[2], $items[3], $items[4], $items[5], $items[6], $items[7], $items[8], $items[9]);
    }

}