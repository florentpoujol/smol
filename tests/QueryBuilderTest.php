<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Database\QueryBuilder;
use PDO;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private static PDO $spdo;

    private PDO $pdo;

    private QueryBuilder $builder;

    public static function setUpBeforeClass(): void
    {
        self::$spdo = new PDO('sqlite::memory:', null, null, [
            PDO::ERRMODE_EXCEPTION => true,
        ]);

        $createTable = <<<'SQL'
        CREATE TABLE IF NOT EXISTS `test` (
          `id` INTEGER PRIMARY KEY AUTOINCREMENT,
          `name` TEXT NOT NULL,
          `email` TEXT,
          `created_at` DATETIME
        )
        SQL;

        self::$spdo->exec($createTable);
    }

    protected function setUp(): void
    {
        $this->pdo = self::$spdo;

        $this->builder = new QueryBuilder($this->pdo);
    }

    public function test_insert(): void
    {
        $query = new QueryBuilder($this->pdo);
        $query
            ->insert(['name', 'email', 'created_at'])
            ->fromTable('test');

        $expected = 'INSERT INTO test (name, email, created_at) VALUES (:name, :email, :created_at)';
        self::assertSame($expected, $query->toSql());

        $data = [
            'name' => 'Florent',
            'email' => 'flo@flo.fr',
            'created_at' => 'NOW()',
        ];
        $id = $query->execute($data);
        self::assertSame('1', $id);

        $entry = $this->pdo->query('SELECT * FROM test')->fetch();
        self::assertSame('1', $entry['id']);
        self::assertSame('Florent', $entry['name']);

        // data passed to insert()
        $query = new QueryBuilder($this->pdo);
        $data = [
            'name' => 'Florent2',
            'created_at' => 'NOW()',
        ];
        $query->insert($data)->fromTable('test');

        $expected = 'INSERT INTO test (name, created_at) VALUES (:name, :created_at)';
        self::assertSame($expected, $query->toSql());

        $id = $query->execute();
        self::assertSame('2', $id);

        $entry = $this->pdo->query("SELECT * FROM test WHERE id = $id")->fetch();
        self::assertSame('2', $entry['id']);
        self::assertSame('Florent2', $entry['name']);

        // data passed to execute()
        $query = new QueryBuilder($this->pdo);
        $query->insert(['name', 'email', 'created_at'])->fromTable('test');

        $expected = 'INSERT INTO test (name, email, created_at) VALUES (:name, :email, :created_at)';
        self::assertSame($expected, $query->toSql());

        $data = [
            'Florent3',
            'email@email.com',
            'NOW()',
        ];
        $id = $query->execute($data);

        $expected = 'INSERT INTO test (name, email, created_at) VALUES (?, ?, ?)';
        self::assertSame($expected, $query->toSql());

        self::assertSame('3', $id);

        $entry = $this->pdo->query("SELECT * FROM test WHERE id = $id")->fetch();
        self::assertSame('3', $entry['id']);
        self::assertSame('Florent3', $entry['name']);
    }

    public function test_multi_insert(): void
    {
        $data = [
            [
                'name' => 'Florent4',
                'email' => 'flo@flo.fr',
            ],
            [
                'name' => 'Florent5',
                'email' => 'flo@flo.fr',
            ],
        ];
        $query = new QueryBuilder($this->pdo);
        $id = $query
            ->insert($data)
            ->fromTable('test')
            ->execute();

        $expected = 'INSERT INTO test (name, email) VALUES (?, ?), (?, ?)';
        self::assertSame($expected, $query->toSql());

        // $id = $query->execute($data);
        self::assertSame('5', $id);

        $entries = $this->pdo->query('SELECT * FROM test WHERE id >= 4');
        $entry = $entries->fetch();
        self::assertSame('4', $entry['id']);
        self::assertSame('Florent4', $entry['name']);
        $entry = $entries->fetch();
        self::assertSame('5', $entry['id']);
        self::assertSame('Florent5', $entry['name']);

        // data pass
        $data = [
            'Florent6',
            'Florent7',
            'Florent8',
        ];
        $query = new QueryBuilder($this->pdo);
        $id = $query
            ->insert('name')
            ->fromTable('test')
            ->execute($data);

        $expected = 'INSERT INTO test (name) VALUES (?), (?), (?)';
        self::assertSame($expected, $query->toSql());

        self::assertSame('8', $id);
    }

    public function test_where(): void
    {
        $query = new QueryBuilder($this->pdo);
        $query->delete()
            ->fromTable('test')
            ->where('name = stuff')
            ->where('email', ':email')
            ->where('id', '>=', 5);

        $expected = 'DELETE FROM test WHERE name = stuff AND email = :email AND id >= 5';
        self::assertSame($expected, $query->toSql());

        // with nested clauses
        $query = new QueryBuilder($this->pdo);
        $query->delete()
            ->fromTable('test')
            ->where('name = stuff')
            ->where(function (QueryBuilder $query): void {
                $query->where('other = stuff')
                    ->where('email', ':email');
            })
            ->where('id', '>=', 5);

        $expected = 'DELETE FROM test WHERE name = stuff AND (other = stuff AND email = :email) AND id >= 5';
        self::assertSame($expected, $query->toSql());
    }

    public function test_where_single_array_argument(): void
    {
        $data = [
            'name' => 'stuff',
            'email' => 'the_email',
            'id' => 5,
        ];
        $query = new QueryBuilder($this->pdo);
        $query->select()
            ->fromTable('test')
            ->where($data);

        $expected = 'SELECT * FROM test WHERE name = :name AND email = :email AND id = :id';
        self::assertSame($expected, $query->toSql());
    }

    public function test_or_where(): void
    {
        $query = new QueryBuilder($this->pdo);
        $query->delete()
            ->fromTable('test')
            ->orWhereNull('name')
            ->orWhere('email', ':email')
            ->where('id', '>=', 5);

        $expected = 'DELETE FROM test WHERE name IS NULL OR email = :email AND id >= 5';
        self::assertSame($expected, $query->toSql());

        // with nested clauses
        $query = new QueryBuilder($this->pdo);
        $query->delete()
            ->fromTable('test')
            ->whereNull('name')
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('other = stuff')
                    ->orWhereNotNull('email');
            })
            ->where('id', '>=', 5);

        $expected = 'DELETE FROM test WHERE name IS NULL OR (other = stuff OR email IS NOT NULL) AND id >= 5';
        self::assertSame($expected, $query->toSql());
    }

    public function test_join(): void
    {
        $query = new QueryBuilder($this->pdo);
        $query->select()
            ->fromTable('test')
            ->join('otherTable')
            ->on('field', 'value')
            ->orOn('field2', '>', 5);

        $expected = "SELECT * FROM test INNER JOIN otherTable ON field = 'value' OR field2 > 5";
        self::assertSame($expected, $query->toSql());

        // with nested clauses
        $query = new QueryBuilder($this->pdo);
        $query->select()
            ->fromTable('test')
            ->join('otherTable')
            ->on('field', 'value')
            ->on(function (QueryBuilder $q): void {
                $q->orOn('field', 'value');
                $q->on('field3', 'value3');
            })
            ->orOn('field2', '>', 'value2');

        $expected = "SELECT * FROM test INNER JOIN otherTable ON field = 'value' AND (field = 'value' AND field3 = 'value3') OR field2 > 'value2'";
        self::assertSame($expected, $query->toSql());

        // with multiple join  clauses
        $query = new QueryBuilder($this->pdo);
        $query->select()
            ->fromTable('test')
            ->join('otherTable')
            ->on('field', 'value')
            ->on(function (QueryBuilder $q): void {
                $q->orOn('field', 'value');
                $q->on('field3', 'value3');
            })
            ->orOn('field2', '>', 'value2')
            ->rightJoin('yetAnotherTable')
            ->on('field', 'value')
            ->on('field2', '>', 'value2');

        $expected = 'SELECT * FROM test '
            . "INNER JOIN otherTable ON field = 'value' AND (field = 'value' AND field3 = 'value3') OR field2 > 'value2' "
            . "RIGHT JOIN yetAnotherTable ON field = 'value' AND field2 > 'value2'";
        self::assertSame($expected, $query->toSql());

        // no on clause
        $query = new QueryBuilder($this->pdo);
        $query->select()
            ->fromTable('test')
            ->join('otherTable');

        $this->expectException(\Exception::class);
        $query->toSql();
    }

    public function test_all_other(): void
    {
        $query = new QueryBuilder($this->pdo);
        $query->select('field as field2')
            ->select('field')
            ->select('otherField')
            ->fromTable('test')
            ->join('otherTable')->on('field', 'value')
            ->where('field', 'LIKE', '%value')
            ->orWhereNotNull('field')
            ->groupBy('field')
            ->having('field', 'value')
            ->orHaving('field2', 'value2')
            ->orderBy('field', 'DESC')
            ->limit(10, 0)
            ->offset(5);

        $expected = 'SELECT field as field2, field, otherField FROM test '
            . "INNER JOIN otherTable ON field = 'value' "
            . "WHERE field LIKE '%value' OR field IS NOT NULL "
            . 'GROUP BY field '
            . "HAVING field = 'value' OR field2 = 'value2' "
            . 'ORDER BY field DESC LIMIT 10 OFFSET 5';
        self::assertSame($expected, $query->toSql());
    }

    public function test_update(): void
    {
        $query = new QueryBuilder($this->pdo);
        $data = [
            'field1' => 1,
            'field2' => 2,
        ];
        $actual = $query->update($data)
            ->fromTable('test')
            ->where('field3', '=', 0)
            ->toSql();

        $expected = "UPDATE test SET field1 = :field1, field2 = :field2 WHERE field3 = 0";
        self::assertSame($expected, $actual);
    }
}
