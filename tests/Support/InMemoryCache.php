<?php

namespace CodeDart\SlideCaptcha\Tests\Support;

class InMemoryCache
{
    public $items = [];

    private $expires = [];

    public function get($key, $default = null)
    {
        if (! $this->has($key)) {
            return $default;
        }

        return $this->items[$key];
    }

    public function put($key, $value, $ttl = null)
    {
        $this->items[$key] = $value;
        $this->expires[$key] = $ttl ? time() + (int) $ttl : null;

        return true;
    }

    public function add($key, $value, $ttl = null)
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->put($key, $value, $ttl);
    }

    public function increment($key, $amount = 1)
    {
        $value = (int) $this->get($key, 0) + (int) $amount;
        $this->items[$key] = $value;

        return $value;
    }

    public function forget($key)
    {
        unset($this->items[$key], $this->expires[$key]);

        return true;
    }

    public function has($key)
    {
        if (! array_key_exists($key, $this->items)) {
            return false;
        }

        if (isset($this->expires[$key]) && $this->expires[$key] <= time()) {
            $this->forget($key);

            return false;
        }

        return true;
    }
}
