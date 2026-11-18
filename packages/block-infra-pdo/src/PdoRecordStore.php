<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Pdo;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\Exceptions\StorageException;
use SpeedySpec\Block\Domain\ValueObjects\CollectionName;

/**
 * Keeps records in one table of a database reached through PDO.
 *
 * A record is identified by its collection and the hash of its key, so a key of any length or content fits an
 * indexed column and is compared exactly whatever the database's collation. The key itself is kept in base64 for
 * listing. Only portable SQL is used, so the same store runs on SQLite, MySQL, MariaDB and PostgreSQL.
 *
 * @since 0.1.0
 */
class PdoRecordStore implements RecordStoreInterface
{
    /**
     * @param PDO $pdo
     *   The connection; its error mode is neither relied on nor changed.
     * @param string $table
     *   The table records are kept in: letters, digits and underscores, at most 64 characters.
     *
     * @throws InvalidArgumentException
     *   When the table name is not a plain identifier; it is written into SQL, so nothing else is accepted.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly PDO $pdo, private readonly string $table = 'block_records')
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $table) !== 1)
        {
            throw new InvalidArgumentException(sprintf('"%s" is not a usable table name.', $table));
        }
    }

    /**
     * Creates the table if it does not exist; safe to call on every start.
     *
     * @throws StorageException
     *   When the table cannot be created.
     *
     * @since 0.1.0
     */
    public function install(): void
    {
        // MySQL's TEXT holds 64 KB, less than a large document; every other supported database's TEXT is unbounded.
        $text = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'LONGTEXT' : 'TEXT';

        $this->run(sprintf(
            'CREATE TABLE IF NOT EXISTS %s ('
            . 'collection VARCHAR(64) NOT NULL, '
            . 'key_hash CHAR(64) NOT NULL, '
            . 'record_key %s NOT NULL, '
            . 'record_value %s NOT NULL, '
            . 'position INTEGER NOT NULL, '
            . 'PRIMARY KEY (collection, key_hash))',
            $this->table,
            $text,
            $text,
        ));
    }

    public function get(string $collection, string $key): ?string
    {
        CollectionName::assert($collection);

        $value = $this->run(
            sprintf('SELECT record_value FROM %s WHERE collection = ? AND key_hash = ?', $this->table),
            [$collection, $this->hash($key)],
        )->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function put(string $collection, string $key, string $value): void
    {
        CollectionName::assert($collection);

        $this->transaction(function () use ($collection, $key, $value): void {
            $hash = $this->hash($key);
            $exists = $this->run(
                sprintf('SELECT 1 FROM %s WHERE collection = ? AND key_hash = ?', $this->table),
                [$collection, $hash],
            )->fetchColumn();

            if ($exists !== false)
            {
                $this->run(
                    sprintf('UPDATE %s SET record_value = ? WHERE collection = ? AND key_hash = ?', $this->table),
                    [$value, $collection, $hash],
                );

                return;
            }

            $last = $this->run(
                sprintf('SELECT MAX(position) FROM %s WHERE collection = ?', $this->table),
                [$collection],
            )->fetchColumn();

            $this->run(
                sprintf(
                    'INSERT INTO %s (collection, key_hash, record_key, record_value, position) VALUES (?, ?, ?, ?, ?)',
                    $this->table,
                ),
                [$collection, $hash, base64_encode($key), $value, (int) $last + 1],
            );
        });
    }

    public function delete(string $collection, string $key): void
    {
        CollectionName::assert($collection);

        $this->run(
            sprintf('DELETE FROM %s WHERE collection = ? AND key_hash = ?', $this->table),
            [$collection, $this->hash($key)],
        );
    }

    public function keys(string $collection): array
    {
        CollectionName::assert($collection);

        $rows = $this->run(
            sprintf('SELECT record_key FROM %s WHERE collection = ? ORDER BY position, key_hash', $this->table),
            [$collection],
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map(static fn (mixed $key): string => (string) base64_decode((string) $key, true), $rows);
    }

    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Runs a statement and reports any failure the same way, whichever error mode the connection uses.
     *
     * @param list<string|int> $parameters
     *
     * @throws StorageException
     *
     * @since 0.1.0
     */
    private function run(string $sql, array $parameters = []): PDOStatement
    {
        try
        {
            $statement = $this->pdo->prepare($sql);

            if ($statement === false || !$statement->execute($parameters))
            {
                $error = ($statement === false ? $this->pdo : $statement)->errorInfo();

                throw new StorageException(sprintf('The record table "%s" failed: %s', $this->table, $error[2] ?? ''));
            }

            return $statement;
        }
        catch (PDOException $e)
        {
            throw new StorageException(
                sprintf('The record table "%s" failed: %s', $this->table, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * Runs several statements as one change, joining a transaction the host already opened rather than ending it.
     *
     * @throws StorageException
     *
     * @since 0.1.0
     */
    private function transaction(callable $change): void
    {
        if ($this->pdo->inTransaction())
        {
            $change();

            return;
        }

        try
        {
            $this->pdo->beginTransaction();
            $change();
            $this->pdo->commit();
        }
        catch (PDOException | StorageException $e)
        {
            if ($this->pdo->inTransaction())
            {
                $this->pdo->rollBack();
            }

            throw $e instanceof StorageException ? $e : new StorageException(
                sprintf('The record table "%s" failed: %s', $this->table, $e->getMessage()),
                0,
                $e,
            );
        }
    }
}
