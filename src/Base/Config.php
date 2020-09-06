<?php

namespace GeekStuff\Dockerizer\Base;

class Config
{
    /** @var string  */
    public $appName = 'dockerizer';

    /** @var string */
    public $appVersion = 'alpha';

    /** @var string */
    public $appDir = '/app';

    /** @var string */
    public $rootDir;

    /** @var self */
    private static $config;

    public static function get(): self
    {
        if (! self::$config) {
            self::$config = new self;
        }

        return self::$config;
    }

    private function __construct()
    {
    }
}
