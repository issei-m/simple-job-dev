<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB\Schema;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
trait ReadOnlyPropertiesTrait
{
    private $properties;

    public function __construct(array $properties = [])
    {
        $this->properties = self::DEFAULT_PROPERTIES;

        foreach ($properties as $name => $value) {
            if (!array_key_exists($name, self::DEFAULT_PROPERTIES)) {
                throw new \InvalidArgumentException(sprintf('The property "%s" does not exist.', $name));
            }

            if (is_string($value) && '' === $value) {
                throw new \InvalidArgumentException('The value should be a non empty string.');
            }

            $this->properties[$name] = $value;
        }
    }

    public function __get(string $name)
    {
        if (!array_key_exists($name, self::DEFAULT_PROPERTIES)) {
            throw new \InvalidArgumentException(sprintf('The property "%s" does not exist.', $name));
        }

        return $this->properties[$name];
    }

    public function __set(string $name, $value)
    {
        throw new \BadMethodCallException('No properties can be overwritten.');
    }

    public function __isset(string $name)
    {
        return array_key_exists($name, self::DEFAULT_PROPERTIES);
    }
}
