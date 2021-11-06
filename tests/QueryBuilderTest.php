<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Database\QueryBuilder;
use PDO;
use PDOException;
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
        // act
        $qb = new QueryBuilder(self::$pdo);
        $success = $qb
            ->inTable('test')
            ->insertSingle([
                'name' => 'Florent',
                'email' => 'flo@flo.fr',
                'created_at' => '2021-11-06 21:27:00',
            ]);

        // assert
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
        // act
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

        // assert
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
        // arrange
        $qb = new QueryBuilder(self::$pdo);
        $qb
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

        // act
        $success = $qb
            ->reset()
            ->inTable('test')
            ->where('name', '=', 'Florent2')
            ->update([
                'email' => 'new email',
            ]);

        // assert
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
        // arrange
        $this->test_insert_many();

        // act
        $qb = new QueryBuilder(self::$pdo);
        $success = $qb
            ->fromTable('test')
            ->where('email', '=', 'flo@flo3.fr')
            ->delete();

        // assert
        self::assertTrue($success);

        $expected = "DELETE FROM 'test' WHERE email = ? ";
        self::assertSame($expected, $qb->toSql());

        $entries = $qb->reset()->selectMany();
        self::assertCount(1, $entries);

        self::assertSame('Florent2', $entries[0]['name']);
        self::assertSame('flo@flo2.fr', $entries[0]['email']);
    }

    // --------------------------------------------------

    private function seedForSelect(): QueryBuilder
    {
        $qb = new QueryBuilder(self::$pdo);
        $qb->inTable('test')->insertMany([
            [
                'name' => 'Florent1',
                'email' => 'flo@flo1.fr',
                'created_at' => '2021-09-06 21:27:00',
            ],
            [
                'name' => 'Flo2',
                'email' => 'flo@flo2.fr',
                'created_at' => '2021-10-06 21:27:00',
            ],
            [
                'name' => 'rent3',
                'email' => 'flo@flo3.fr',
                'created_at' => '2021-11-06 21:27:00',
            ],
        ]);

        return $qb;
    }

    public function test_simple_where(): void
    {
        // arrange
        $qb = $this->seedForSelect();

        // act & assert, several times
        // --------------------------------------------------
        $row = $qb
            ->reset()
            ->inTable('test')
            ->where('name', '=', 'stuff')
            ->selectSingle();

        $expected = "SELECT * FROM 'test' WHERE name = ? LIMIT 1 ";
        self::assertSame($expected, $qb->toSql());

        self::assertNull($row);

        // --------------------------------------------------
        $row = $qb
            ->reset()
            ->inTable('test')
            ->where('name', '=', 'rent3')
            ->selectSingle();

        $expected = "SELECT * FROM 'test' WHERE name = ? LIMIT 1 ";
        self::assertSame($expected, $qb->toSql());

        self::assertNotNull($row);
        self::assertSame('rent3', $row['name']);
        self::assertSame('flo@flo3.fr', $row['email']);

        // --------------------------------------------------
        $row = $qb
            ->reset()
            ->inTable('test')
            ->where('name', '=', 'rent3')
            ->where('email', 'like', '%flo3.fr')
            ->selectSingle();

        $expected = "SELECT * FROM 'test' WHERE name = ? AND email LIKE ? LIMIT 1 ";
        self::assertSame($expected, $qb->toSql());

        self::assertNotNull($row);
        self::assertSame('rent3', $row['name']);
        self::assertSame('flo@flo3.fr', $row['email']);

        // --------------------------------------------------
        $row = $qb
            ->reset()
            ->inTable('test')
            ->where('created_at', '>=', '2021-11-01')
            ->selectSingle();

        $expected = "SELECT * FROM 'test' WHERE created_at >= ? LIMIT 1 ";
        self::assertSame($expected, $qb->toSql());

        self::assertNotNull($row);
        self::assertSame('rent3', $row['name']);
        self::assertSame('flo@flo3.fr', $row['email']);

        // --------------------------------------------------
        $rows = $qb
            ->reset()
            ->inTable('test')
            ->whereBetween('created_at', '2021-10-01', '2021-12-01')
            ->selectMany(['name']);

        $expected = "SELECT name FROM 'test' WHERE created_at BETWEEN ? AND ? ";
        self::assertSame($expected, $qb->toSql());

        self::assertCount(2, $rows);

        self::assertFalse(isset($rows[0]['id']));
        self::assertFalse(isset($rows[0]['email']));
        self::assertFalse(isset($rows[0]['created_at']));

        self::assertSame('Flo2', $rows[0]['name']);
        self::assertSame('rent3', $rows[1]['name']);
    }

    public function test_nested_where(): void
    {
        // arrange
        $qb = $this->seedForSelect();

        // act & assert, several times
        // --------------------------------------------------
        $row = $qb
            ->reset()
            ->inTable('test')
            ->whereGroup(function (QueryBuilder $qb): void {
                $qb
                    ->whereNotIn('stuff', [1, 2])
                    ->orWhereGroup(fn ($qb) => $qb->where('field', '<=', 1)->where('field2', 'not like', 'stuf%'));
            })
            ->where('name', '=', 'stuff');

        try {
            $qb->selectSingle();
        } catch (PDOException $e) {
            // this is oK, some columns in the query do not exists
        }

        $expected = "SELECT * FROM 'test' WHERE (stuff NOT IN (?, ?) OR (field <= ? AND field2 NOT LIKE ?)) AND name = ? LIMIT 1 ";
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
        $qb
            ->fromTable('test')
            ->join('otherTable')->on('field', 'value')
            ->where('field', 'LIKE', '%value')
            ->orWhereNotNull('field')
            ->groupBy('field')
            ->having('field', 'value')
            ->orHaving('field2', 'value2')
            ->mostRecentFirst('field')
            ->limit(10)
            ->offset(5);

        $expected = "SELECT * FROM 'test' "
            . "INNER JOIN otherTable ON field = 'value' "
            . "WHERE field LIKE '%value' OR field IS NOT NULL "
            . 'GROUP BY field '
            . "HAVING field = 'value' OR field2 = 'value2' "
            . 'ORDER BY field DESC LIMIT 10 OFFSET 5';
        self::assertSame($expected, $qb->toSql());
    }
}
