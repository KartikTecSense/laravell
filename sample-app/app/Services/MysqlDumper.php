<?php

namespace App\Services;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

/**
 * Writes a plain-SQL dump of a MySQL database through PDO, so it reuses the
 * app's connection (including SSL) and needs no mysqldump binary.
 */
class MysqlDumper
{
    private const ROWS_PER_INSERT = 200;

    public function __construct(private Connection $db)
    {
        if (! in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Database backups only support MySQL/MariaDB connections.');
        }
    }

    /**
     * @param  callable(string): void  $write
     */
    public function dump(callable $write): void
    {
        $write(sprintf(
            "-- Dump of `%s` taken %s\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n",
            $this->db->getDatabaseName(),
            now()->toDateTimeString(),
        ));

        foreach ($this->objects('BASE TABLE') as $table) {
            $create = (array) $this->db->selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($table));
            $write('DROP TABLE IF EXISTS '.$this->quoteIdentifier($table).";\n".$create['Create Table'].";\n\n");
            $this->dumpRows($table, $write);
        }

        foreach ($this->objects('VIEW') as $view) {
            $create = (array) $this->db->selectOne('SHOW CREATE VIEW '.$this->quoteIdentifier($view));
            $write('DROP VIEW IF EXISTS '.$this->quoteIdentifier($view).";\n".$create['Create View'].";\n\n");
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * @return list<string>
     */
    private function objects(string $type): array
    {
        return array_map(
            fn ($row) => array_values((array) $row)[0],
            $this->db->select('SHOW FULL TABLES WHERE Table_type = ?', [$type]),
        );
    }

    /**
     * @param  callable(string): void  $write
     */
    private function dumpRows(string $table, callable $write): void
    {
        $pdo = $this->db->getPdo();
        $quoted = $this->quoteIdentifier($table);

        // Stream rows instead of loading the whole table into memory.
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        try {
            $statement = $pdo->query("SELECT * FROM {$quoted}", PDO::FETCH_NUM);
            $batch = [];

            while (($row = $statement->fetch()) !== false) {
                $batch[] = '('.implode(',', array_map(
                    fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    $row,
                )).')';

                if (count($batch) === self::ROWS_PER_INSERT) {
                    $write("INSERT INTO {$quoted} VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $write("INSERT INTO {$quoted} VALUES\n".implode(",\n", $batch).";\n");
            }

            $statement->closeCursor();
        } finally {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        $write("\n");
    }

    private function quoteIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
