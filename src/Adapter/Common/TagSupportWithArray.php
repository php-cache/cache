<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common;

/**
 * This trait could be used by adapters that do not have a native support for lists.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
trait TagSupportWithArray
{
    /**
     * Get a value from the storage.
     */
    abstract public function getDirectValue(string $name): mixed;

    abstract public function setDirectValue(string $name, mixed $value): bool;

    protected function appendListItem(string $name, string $value): bool
    {
        $data = $this->getDirectValue($name);
        if (!\is_array($data)) {
            $data = [];
        }
        $data[] = $value;

        return $this->setDirectValue($name, $data);
    }

    /**
     * @return list<string>
     */
    protected function getList(string $name): array
    {
        $data = $this->getDirectValue($name);
        if (!\is_array($data)) {
            return [];
        }

        $values = [];
        foreach ($data as $value) {
            if (\is_string($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    protected function removeList(string $name): bool
    {
        return $this->setDirectValue($name, []);
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $data = $this->getList($name);
        foreach ($data as $i => $value) {
            if ($key === $value) {
                unset($data[$i]);
            }
        }

        return $this->setDirectValue($name, $data);
    }
}
