<?php
declare(strict_types=1);

namespace GeekStuff\Dockerizer\Command;

use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Twig\Error\Error as TwigError;

/**
 * This command initializes a docker buildtools box for initialization.
 * This is meant to be used once during initial project setup, and that's it.
 */
class Init extends Common
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('php-user-id', null, InputOption::VALUE_REQUIRED, 'PHP User ID', getenv('PHP_USER_ID'));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        if (! $this->isRoot()) {
            throw new Exception('Please run this as a docker root user.');
        }

        if (file_exists('/app/.empty')) {
            $output->writeln('Warning: You have not mounted the /app folder.');
            $output->writeln('         This means whatever you do here will be lost once you exit.');
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws ExceptionInterface
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);
        $this->detectTags();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws TwigError
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->userMessage($output);
        $this->createPhpUser($input, $output);
        $this->dropToUser($output);

        // At this point the script has ended or not yet but will not get here either way.
        // Seems pcntl_exec works nicely, except for getting control back after. Need more digging but will do for now.

        return static::SUCCESS;
    }

    protected function createPhpUser(InputInterface $input, OutputInterface $output): void
    {
        $output->write('# Checking if php user already exists: ');
        if (file_exists(sprintf('/home/%s', getenv('PHP_USER_NAME')))) {
            $output->writeln('yes.');
            return;
        }

        $output->writeln('no. creating it.');
        $process = new Process(['create-php-user', $input->getOption('php-user-id')]);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * @param OutputInterface $output
     * @throws TwigError
     */
    protected function userMessage(OutputInterface $output): void
    {
        $output->writeln($this->getTwig($this->getOtherTemplateDir())->render(
            'init-done.twig',
            [
                'is_empty' => $this->isAppEmpty(),
                'has_framework' => $this->detectFramework() !== $this->data::FRAMEWORK_NONE,
                'is_dockerized' => $this->anyTemplateFilesExists(),
                'apps' => $this->getAppsAndTools(),
            ]
        ));
    }

    protected function dropToUser(OutputInterface $output): void
    {
        $output->writeln('# Starting shell as php user.');
        $output->writeln('');
        pcntl_exec('/bin/su', [getenv('PHP_USER_NAME')]);
    }
}
