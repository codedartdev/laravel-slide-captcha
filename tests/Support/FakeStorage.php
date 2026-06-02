<?php

namespace CodeDart\SlideCaptcha\Tests\Support;

class FakeStorage
{
    public $puts = [];

    public function put($path, $contents, array $options = [])
    {
        $this->puts[] = [
            'path' => $path,
            'contents' => $contents,
            'options' => $options,
        ];

        return true;
    }
}
