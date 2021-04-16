<?php

namespace Closure\Extensions;





use think\model\Collection;

class CollectionExtension extends Collection
{
    /**
     * @param string $primary
     * @param string $parent
     * @param string $children
     * @return array
     */
    public function toTree($primary = 'id', $parent = 'parent', $children = 'children')
    {
        $data = $this->toArray();

        if (! isset($data[0][$parent])) {
            return [];
        }
        $items = array();
        foreach ($data as $v) {
            $items[$v[$primary]] = $v;
        }
        $tree = array();
        foreach ($items as $item) {
            if (isset($items[$item[$parent]])) {
                $items[$item[$parent]][$children][] = &$items[$item[$primary]];
            } else {
                $tree[] = &$items[$item[$primary]];
            }
        }
        return parent::make($tree);
    }

    public function each(callable $callback)
    {
        foreach ($this as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    public function map(callable $callback)
    {
        return  array_map($callback,$this->items);
    }

    public  function pluck( $value, $key = null)
    {
        $results = [];

        [$value, $key] = static::explodePluckParameters($value, $key);

        foreach ($this->items as $item) {
            $itemValue = data_get($item, $value);

            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = data_get($item, $key);

                if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                    $itemKey = (string) $itemKey;
                }

                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    protected static function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }
}