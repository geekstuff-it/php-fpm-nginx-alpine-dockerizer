<?php
declare(strict_types=1);

namespace GeekStuff\Dockerizer\Command;

use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Error\Error as TwigError;

class Dockerize extends Common
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Description')
            ->setHelp('Help!!')
            //->addArgument('foo', InputArgument::OPTIONAL, 'The directory')
            ->addOption(
                'fpm-from', null, InputOption::VALUE_REQUIRED,
                'php-fpm-alpine source image without tag',
                'geekstuffreal/php-fpm-alpine'
            )
            ->addOption(
                'fpm-version', null, InputOption::VALUE_REQUIRED,
                'The tag to use for php-fpm base image. https://hub.docker.com/r/geekstuffreal/php-fpm-alpine/tags',
                $this->getFpmLatestTagForCurrentPhpVersion()
            )
            ->addOption(
                'nginx-from', null, InputOption::VALUE_REQUIRED,
                'nginx-php-alpine source image without tag',
                'geekstuffreal/nginx-php-alpine'
            )
            ->addOption(
                'nginx-version', null, InputOption::VALUE_REQUIRED,
                'The tag to use for nginx-php base image. https://hub.docker.com/r/geekstuffreal/nginx-php-alpine/tags',
                $this->getNginxLatestTag()
            )
            ->addOption(
                'composer-version', null, InputOption::VALUE_REQUIRED,
                'Composer version',
                $this->getComposerVersion()
            )
            ->addOption(
                'ide-servername', null, InputOption::VALUE_REQUIRED,
                'This represents PHP_IDE_CONFIG=serverName=<ValueYouProvide>. A necessary config for easier xdebug in IDEs like PHPStorm',
                'php-docker-dev'
            )
            ->addOption(
                'timezone', null, InputOption::VALUE_REQUIRED,
                'Timezone to use',
                'UTC'
            )
            ->addOption(
                'framework', null, InputOption::VALUE_REQUIRED,
                'The framework templates to use. (will try to auto detect)'
            )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        if (is_null($input->getOption('framework'))) {
            $framework = $this->detectFramework();

            if ($framework !== $this->data::FRAMEWORK_NONE) {
                $input->setOption('framework', $framework);
            }
        }
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

        $output->write('# Template files already exists? ');
        $templateFilesExist = $this->anyTemplateFilesExists();
        $output->writeln($templateFilesExist ? 'yes' : 'no');
        if ($templateFilesExist) {
            $files = $this->getDockerizeTemplateFiles();
            $list = [];
            foreach ($files as $file) {
                $list[] = $file->getFilenameWithoutExtension();
            }
            $output->writeln('Error: Refusing to continue when any of the files we want to write exists:');
            $output->writeln('  '.implode(', ', $list));
            $output->writeln('');

            return static::FAILURE;
        }

        $this->writeFiles($input, $output);
        $this->doneMessage($output);

        return static::SUCCESS;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     * @throws TwigError
     * @throws Exception
     */
    protected function writeFiles(InputInterface $input, OutputInterface $output): bool
    {
        $output->writeln('# Write template files');
        $fs = new Filesystem();

        $params = [
            'FPM_FROM' => $input->getOption('fpm-from'),
            'FPM_VERSION' => $input->getOption('fpm-version'),
            'NGINX_FROM' => $input->getOption('nginx-from'),
            'NGINX_VERSION' => $input->getOption('nginx-version'),
            'IDE_SERVERNAME' => $input->getOption('ide-servername'),
            'TIMEZONE' => $input->getOption('timezone'),
            'COMPOSER_VERSION' => $input->getOption('composer-version'),
        ];

        $twig = $this->getTwig($this->getDockerizeTemplateDir());
        foreach ($this->getDockerizeTemplateFiles() as $templateFile) {
            $newFile = sprintf('/app/%s', $templateFile->getFilenameWithoutExtension());
            if ($fs->exists($newFile)) {
                throw new Exception("Destination file already exists");
            }

            $fs->dumpFile($newFile, $twig->render($templateFile->getFilename(), $params));
        }

        return true;
    }

    /**
     * @param OutputInterface $output
     * @return bool
     * @throws TwigError
     */
    protected function doneMessage(OutputInterface $output): bool
    {
        $notesFile = '/app/notes-dockerizer.md';
        $notesFileBasename = basename($notesFile);
        $message = $this->getTwig($this->getOtherTemplateDir())->render(
            'dockerize-done.twig',
            [
                'FILE_LIST' => $this->getDockerizeTemplateFiles(),
                'NOTES_FILENAME' => $notesFileBasename
            ]
        );

        $output->writeln($message);

        // write those notes in /app folder
        if (file_exists($notesFile)) {
            if (trim(file_get_contents($notesFile)) !== trim($message)) {
                $output->writeln(sprintf('WARNING: %s already exists and differs. The notes above were not saved.', $notesFileBasename));
            }
        } else {
            (new Filesystem())->dumpFile($notesFile, $message);
        }

        return true;
    }
}
