<?php
declare(strict_types = 1);

namespace GeekStuff\Dockerizer\Base;

class Bootstrap
{
    static public function start(string $rootDir): void
    {
        $config = Config::get();
        $config->rootDir = $rootDir;
    }
}
