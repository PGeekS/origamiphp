<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Exception\InvalidEnvironmentException;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * @codeCoverageIgnore
 */
class EnvironmentCollection implements Countable, IteratorAggregate
{
    /**
     * @param EnvironmentEntity[] $values
     */
    public function __construct(private array $values = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->values);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->values);
    }

    /**
     * Tries to add the given environment into the collection.
     *
     * @throws InvalidEnvironmentException
     */
    public function add(EnvironmentEntity $environment): void
    {
        foreach ($this->values as $entity) {
            if ($entity->getName() === $environment->getName()) {
                throw new InvalidEnvironmentException('An environment with the same name already exists.');
            }

            if ($entity->getLocation() === $environment->getLocation()) {
                throw new InvalidEnvironmentException('An environment at the same location already exists.');
            }
        }

        $this->values[] = $environment;
    }

    /**
     * Tries to remove the given environment from the collection.
     */
    public function remove(EnvironmentEntity $environment): void
    {
        foreach ($this->values as $key => $entity) {
            if ($entity->getLocation() === $environment->getLocation()) {
                unset($this->values[$key]);
            }
        }

        sort($this->values);
    }
}
