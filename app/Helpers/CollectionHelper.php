<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class CollectionHelper
{
    /**
     * Optimized unique method that avoids O(n^2) complexity
     *
     * @param Collection $collection
     * @param callable|string|null $key
     * @param bool $strict
     * @return Collection
     */
    public static function fastUnique(Collection $collection, $key = null, $strict = false): Collection
    {
        if (is_null($key) && $strict === false) {
            return new Collection(array_unique($collection->all(), SORT_REGULAR));
        }

        // Create callback function based on the key parameter
        if (is_string($key)) {
            $callback = function ($item) use ($key) {
                return data_get($item, $key);
            };
        } elseif (is_callable($key)) {
            $callback = $key;
        } else {
            $callback = function ($item) {
                return $item;
            };
        }

        $exists = [];
        $result = [];

        foreach ($collection as $itemKey => $item) {
            $id = $callback($item, $itemKey);

            if ($strict) {
                $existsKey = self::getStrictKey($id);
                if (isset($exists[$existsKey])) {
                    continue;
                }
                $exists[$existsKey] = true;
            } else {
                $existsKey = self::getNonStrictKey($id);
                if (isset($exists[$existsKey])) {
                    continue;
                }
                $exists[$existsKey] = true;
            }

            $result[$itemKey] = $item;
        }

        return new Collection($result);
    }

    /**
     * Get key for strict comparison
     */
    protected static function getStrictKey($value): string
    {
        if (is_object($value)) {
            return spl_object_hash($value);
        }

        if (is_scalar($value)) {
            return (string) $value . ':' . gettype($value);
        }

        return 'complex:' . md5(serialize($value));
    }

    /**
     * Get key for non-strict comparison
     */
    protected static function getNonStrictKey($value): string
    {
        if (is_object($value)) {
            return spl_object_hash($value);
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'complex:' . md5(serialize($value));
    }
}
