<?php
declare(strict_types=1);

namespace GeekStuff\Dockerizer;

class Data
{
    const
        REPO_PHP_FPM = 'php-fpm',
        REPO_PHP_BUILDTOOLS = 'php-buildtools',
        REPO_NGINX_PHP = 'nginx-php';

    const ALL_REPOS = [
        self::REPO_PHP_FPM,
        self::REPO_PHP_BUILDTOOLS,
        self::REPO_NGINX_PHP,
    ];

    const
        FRAMEWORK_NONE = 'none',
        FRAMEWORK_LARAVEL = 'laravel',
        FRAMEWORK_SYMFONY = 'symfony';

    const ALL_FRAMEWORKS = [
        self::FRAMEWORK_NONE,
        self::FRAMEWORK_SYMFONY,
        self::FRAMEWORK_LARAVEL,
    ];

    public $repos = [
        self::REPO_PHP_FPM => 'geekstuffreal/php-fpm-alpine',
        self::REPO_PHP_BUILDTOOLS => 'geekstuffreal/php-buildtools-alpine',
        self::REPO_NGINX_PHP => 'geekstuffreal/nginx-php-alpine',
    ];

    public $tags = [
        self::REPO_PHP_FPM => [],
        self::REPO_PHP_BUILDTOOLS => [],
        self::REPO_NGINX_PHP => [],
    ];

    public $commonPhpTags = [];
}
