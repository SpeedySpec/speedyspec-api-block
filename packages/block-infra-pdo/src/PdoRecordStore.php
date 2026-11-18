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
     * On MySQL and MariaDB creating a table commits any transaction the host has open.
     *
     * @throws StorageException
     *   When the table cannot be created.
     *
     * @since 0.1.0
     */
    public function install(): void
    {
        $this->run($this->tableSql((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)));
    }

    /**
     * Writes the statement that creates the table for a PDO driver.
     *
     * MySQL needs two things said that the others do by default: a text type large enough for a document, and a
     * character set that holds every character and compares exactly, whatever the database's own default is.
     *
     * @param string $driver
     *   A PDO driver name, such as `mysql`, `pgsql` or `sqlite`.
     *
     * @since 0.1.0
     */
    public function tableSql(string $driver): string
    {
        $text = $driver === 'mysql' ? 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin' : 'TEXT';

        return sprintf(
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
        );
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

    /**
     * {@inheritDoc}
     *
     * No transaction is opened: each statement stands on its own, so a write can never be left half-committed
     * and joins whatever transaction the host has open. A key first put by two writers at once is inserted by
     * one and then replaced by the other.
     *
     * @since 0.1.0
     */
    public function put(string $collection, string $key, string $value): void
    {
        CollectionName::assert($collection);
        $hash = $this->hash($key);

        if ($this->exists($collection, $hash))
        {
            $this->update($collection, $hash, $value);

            return;
        }

        try
        {
            // The position is worked out inside the insert, so two writers cannot read the same one apart.
            $this->run(
                sprintf(
                    'INSERT INTO %1$s (collection, key_hash, record_key, record_value, position) '
                    . 'SELECT ?, ?, ?, ?, COALESCE(MAX(position), 0) + 1 FROM %1$s WHERE collection = ?',
                    $this->table,
                ),
                [$collection, $hash, base64_encode($key), $value, $collection],
            );
        }
        catch (StorageException $e)
        {
            if (!$this->exists($collection, $hash))
            {
                throw $e;
            }

            $this->update($collection, $hash, $value);
        }
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

    private function exists(string $collection, string $hash): bool
    {
        return $this->run(
            sprintf('SELECT 1 FROM %s WHERE collection = ? AND key_hash = ?', $this->table),
            [$collection, $hash],
        )->fetchColumn() !== false;
    }

    private function update(string $collection, string $hash, string $value): void
    {
        $this->run(
            sprintf('UPDATE %s SET record_value = ? WHERE collection = ? AND key_hash = ?', $this->table),
            [$value, $collection, $hash],
        );
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
        // A connection set to warn would otherwise raise a PHP warning as well, which a host may turn into an
        // exception of its own; the failure is reported once, here.
        set_error_handler(static fn (): bool => true);

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
        finally
        {
            restore_error_handler();
        }
    }
}
