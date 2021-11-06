<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Database\QueryBuilder;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $createTable = <<<'SQL'
        CREATE TABLE IF NOT EXISTS `test` (
          `id` INTEGER PRIMARY KEY AUTOINCREMENT,
          `name` TEXT NOT NULL,
          `email` TEXT,
          `created_at` DATETIME
        )
        SQL;

        self::$pdo->exec($createTable);
    }

    public function test_insert_single(): void
    {
        $qb = new QueryBuilder(self::$pdo);
        $success = $qb
            ->inTable('test')
            ->insertSingle([
                'name' => 'Florent',
                'email' => 'flo@flo.fr',
                'created_at' => '2021-11-06 21:27:00',
            ]);

        self::assertTrue($success);

        $expected = "INSERT INTO 'test' ('name', 'email', 'created_at') VALUES (?, ?, ?)";
        self::assertSame($expected, $qb->toSql());
        self::assertSame('1', $qb->getPdo()->lastInsertId());

        $statement = self::$pdo->query('SELECT * FROM test');
        self::assertInstanceOf(PDOStatement::class, $statement);
        assert($statement instanceof PDOStatement); // for PHPStan

        $entry = $statement->fetch();
        self::assertSame('1', $entry['id']);
        self::assertSame('Florent', $entry['name']);
        self::assertSame('flo@flo.fr', $entry['email']);
        self::assertSame('2021-11-06 21:27:00', $entry['created_at']);
    }

    public function test_insert_many(): void
    {
        $qb = new QueryBuilder(self::$pdo);
        $success = $qb
            ->inTable('test')
            ->insertMany([
                [
                    'name' => 'Florent2',
                    'email' => 'flo@flo2.fr',
                ],
                [
                    'name' => 'Florent3',
                    'email' => 'flo@flo3.fr',
                ],
            ]);

        self::assertTrue($success);

        $expected = "INSERT INTO 'test' ('name', 'email') VALUES (?, ?), (?, ?)";
        self::assertSame($expected, $qb->toSql());

        $entries = $qb->reset()->selectMany();

        self::assertTrue(isset($entries[0]['id']));
        self::assertSame('Florent2', $entries[0]['name']);
        self::assertSame('flo@flo2.fr', $entries[0]['email']);

        self::assertTrue(isset($entries[0]['id']));
        self::assertSame($qb->getPdo()->lastInsertId(), $entries[1]['id']);
        self::assertSame('Florent3', $entries[1]['name']);
        self::assertSame('flo@flo3.fr', $entries[1]['email']);
    }

    public function test_update(): void
    {
        $this->test_insert_many();

        $qb = new QueryBuilder(self::$pdo);
        $success = $qb
            ->inTable('test')
            ->where('name', '=', 'Florent2')
            ->update([
                'email' => 'new email',
            ]);

        self::assertTrue($success);

        $expected = "UPDATE 'test' SET 'email' = ? WHERE name = ?";
        self::assertSame($expected, $qb->toSql());

        $entries = $qb->reset()->selectMany();
        self::assertCount(2, $entries);

        self::assertSame('Florent2', $entries[0]['name']);
        self::assertSame('new email', $entries[0]['email']);

        self::assertSame('Florent3', $entries[1]['name']);
        self::assertSame('flo@flo.fr', $entries[1]['email']);
    }

    public function test_delete(): void
    {
        $this->test_insert_many();

        $qb = new QueryBuilder(self::$pdo);
        $success = $qb
            ->fromTable('test')
            ->where('email', '=', 'flo@flo3.fr')
            ->delete();

        self::assertTrue($success);

        $expected = "DELETE FROM 'test' WHERE email = ? ";
        self::assertSame($expected, $qb->toSql());

        $entries = $qb->reset()->selectMany();
        self::assertCount(1, $entries);

        self::assertSame('Florent2', $entries[0]['name']);
        self::assertSame('flo@flo2.fr', $entries[0]['email']);
    }

    public function test_select_with_various_wheres(): void
    {
        $qb = new QueryBuilder(self::$pdo);
        $qb->inTable('test')
            ->where('name = stuff')
            ->where('email', ':email')
            ->where('id', '>=', 5);

        $expected = 'DELETE FROM test WHERE name = stuff AND email = :email AND id >= 5';
        self::assertSame($expected, $qb->toSql());

        // with nested clauses
        $qb = new QueryBuilder(self::$pdo);
        $qb->delete()
            ->fromTable('test')
            ->where('name = stuff')
            ->where(function (QueryBuilder $qb): void {
                $qb->where('other = stuff')
                    ->where('email', ':email');
            })
            ->where('id', '>=', 5);

        $expected = 'DELETE FROM test WHERE name = stuff AND (other = stuff AND email = :email) AND id >= 5';
        self::assertSame($expected, $qb->toSql());
    }

    public function test_where_single_array_argument(): void
    {
        $data = [
            'name' => 'stuff',
            'email' => 'the_email',
            'id' => 5,
        ];
        $qb = new QueryBuilder(self::$pdo);
        $qb->select()
            ->fromTable('test')
            ->where($data);

        $expected = 'SELECT * FROM test WHERE name = :name AND email = :email AND id = :id';
        self::assertSame($expected, $qb->toSql());
    }

    public function test_or_where(): void
    {
        $qb = new QueryBuilder(self::$pdo);
        $qb->delete()
            ->fromTable('test')
            ->orWhereNull('name')
            ->orWhere('email', ':email')
            ->where('id', '>=', 5);

        $expected = 'DELETE FROM test WHERE name IS NULL OR email = :email AND id >= 5';
        self::assertSame($expected, $qb->toSql());

        // with nested clauses
        $qb = new QueryBuilder(self::$pdo);
        $qb->delete()
            ->fromTable('test')
            ->whereNull('name')
            ->orWhere(function (QueryBuilder $qb): void {
                $qb->where('other = stuff')
                    ->orWhereNotNull('email');
            })
            ->where('id', '>=', 5);

        $expected = 'DELETE FROM test WHERE name IS NULL OR (other = stuff OR email IS NOT NULL) AND id >= 5';
        self::assertSame($expected, $qb->toSql());
    }

    public function test_join(): void
    {
        $qb = new QueryBuilder(self::$pdo);
        $qb->select()
            ->fromTable('test')
            ->join('otherTable')
            ->on('field', 'value')
            ->orOn('field2', '>', 5);

        $expected = "SELECT * FROM test INNER JOIN otherTable ON field = 'value' OR field2 > 5";
        self::assertSame($expected, $qb->toSql());

        // with nested clauses
        $qb = new QueryBuilder(self::$pdo);
        $qb->select()
            ->fromTable('test')
            ->join('otherTable')
            ->on('field', 'value')
            ->on(function (QueryBuilder $q): void {
                $q->orOn('field', 'value');
                $q->on('field3', 'value3');
            })
            ->orOn('field2', '>', 'value2');

        $expected = "SELECT * FROM test INNER JOIN otherTable ON field = 'value' AND (field = 'value' AND field3 = 'value3') OR field2 > 'value2'";
        self::assertSame($expected, $qb->toSql());

        // with multiple join  clauses
        $qb = new QueryBuilder(self::$pdo);
        $qb->select()
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
        self::assertSame($expected, $qb->toSql());

        // no on clause
        $qb = new QueryBuilder(self::$pdo);
        $qb->select()
            ->fromTable('test')
            ->join('otherTable');

        $this->expectException(\Exception::class);
        $qb->toSql();
    }

    public function test_all_other(): void
    {
        $qb = new QueryBuilder(self::$pdo);
        $qb->select('field as field2')
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
        self::assertSame($expected, $qb->toSql());
    }
}
