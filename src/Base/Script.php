<?php
declare(strict_types = 1);

namespace GeekStuff\Dockerizer\Base;

use Exception;
use Symfony\Component\Console\Application;

class Script
{
    /**
     * @param string $startCommand
     * @param string $class
     * @return int
     * @throws Exception
     */
    static public function start(string $startCommand, string $class): int
    {
        $config = Config::get();
        $application = new Application($config->appName, $config->appVersion);
        $command = new $class($startCommand);
        $application->add($command);
        $application->setDefaultCommand($command->getName(), true);

        return $application->run();
    }
}
