<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Storage;

use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTemplateException;
use SpeedySpec\Block\Domain\Exceptions\StorageException;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Keeps saved templates as serialized text in a record store, so one repository works over any storage.
 *
 * @since 0.1.0
 */
class StoredBlockTemplateRepository implements BlockTemplateRepositoryInterface
{
    /**
     * @param string $collection
     *   The record collection templates are kept in.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly RecordStoreInterface $store,
        private readonly BlockTemplateSerializerInterface $serializer,
        private readonly string $collection = 'templates',
    )
    {
    }

    public function save(BlockTemplate $template): void
    {
        $this->store->put($this->collection, $template->name, $this->serializer->serialize($template));
    }

    public function remove(string $name): void
    {
        $this->store->delete($this->collection, $name);
    }

    /**
     * {@inheritDoc}
     *
     * @throws StorageException
     *   When the stored record is not a template.
     *
     * @since 0.1.0
     */
    public function find(string $name): ?BlockTemplate
    {
        $stored = $this->store->get($this->collection, $name);

        if ($stored === null)
        {
            return null;
        }

        try
        {
            return $this->serializer->parse($stored);
        }
        catch (InvalidBlockTemplateException $e)
        {
            throw new StorageException(
                sprintf('The stored template "%s" cannot be read: %s', $name, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws StorageException
     *   When any stored record is not a template; a list with a silent gap would hide lost work.
     *
     * @since 0.1.0
     */
    public function all(): array
    {
        $templates = [];

        foreach ($this->store->keys($this->collection) as $name)
        {
            $template = $this->find($name);

            if ($template !== null)
            {
                $templates[] = $template;
            }
        }

        return $templates;
    }
}
