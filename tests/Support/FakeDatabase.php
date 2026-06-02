<?php

namespace CodeDart\SlideCaptcha\Tests\Support;

class FakeDatabase
{
    public $rows = [];

    public function table($table)
    {
        return new FakeDatabaseTable($this, $table);
    }
}

class FakeDatabaseTable
{
    private $database;

    private $table;

    private $limit;

    private $since;

    public function __construct(FakeDatabase $database, $table)
    {
        $this->database = $database;
        $this->table = $table;
    }

    public function insert(array $row)
    {
        $row['table'] = $this->table;
        $this->database->rows[] = $row;

        return true;
    }

    public function orderBy($column, $direction)
    {
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = (int) $limit;

        return $this;
    }

    public function where($column, $operator, $value)
    {
        if ($column === 'occurred_at' && $operator === '>=') {
            $this->since = strtotime((string) $value);
        }

        return $this;
    }

    public function get()
    {
        $rows = array_filter($this->database->rows, function ($row) {
            if (! $this->since) {
                return true;
            }

            return strtotime((string) $row['occurred_at']) >= $this->since;
        });

        $rows = array_slice(array_values($rows), 0, $this->limit ?: 500);

        return array_map(function ($row) {
            return (object) $row;
        }, $rows);
    }
}
