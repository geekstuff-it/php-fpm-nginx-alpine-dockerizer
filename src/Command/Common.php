<?php
declare(strict_types=1);

namespace GeekStuff\Dockerizer\Command;

use GeekStuff\Dockerizer\Base\Config;
use GeekStuff\Dockerizer\Data;
use Exception;
use Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Throwable;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

abstract class Common extends Command
{
    /** @var Data */
    protected $data;

    /** @var Config */
    protected $config;

    protected function configure(): void
    {
        $this->config = Config::get();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->data = new Data;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        return Command::SUCCESS;
    }

    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    protected function detectTags(): void
    {
        $client = HttpClient::create();
        foreach ($this->data::ALL_REPOS as $repo) {
            $tags = [];
            $repository = $this->data->repos[$repo];
            $url = sprintf('https://registry.hub.docker.com/v2/repositories/%s/tags?page_size=1024&ordering=last_updated', $repository);
            $response = $client->request('GET', $url);
            if ($response->getStatusCode() !== 200) {
                throw new Exception(sprintf('Exception during curl call for repo %s', $repository));
            }

            $json = json_decode($response->getContent());
            foreach ($json->results as $each) {
                $tags[] = $each->name;
            }

            // keep only our latest revisions for this init purpose
            // sort by number of dots first (highest first), then by natural decreasing order

            $this->sortTags($tags);
            $this->data->tags[$repo] = $tags;
        }

        $this->data->commonPhpTags = array_intersect(
            $this->data->tags[$this->data::REPO_PHP_FPM],
            $this->data->tags[$this->data::REPO_PHP_BUILDTOOLS]
        );
    }

    /**
     * @param array $tags
     */
    protected function sortTags(array &$tags): void
    {
        $normalizeVersionTag = function ($tag) {
            $out = '';
            $parts = explode('.', $tag);
            for ($i = 0; $i < 3; $i++) {
                $part = isset($parts[$i])
                    ? $parts[$i]
                    : 0;
                $out .= str_pad((string) $part, 3, '0', STR_PAD_RIGHT);
            }

            return $out;
        };

        $normalizeVersion = function ($tag) use ($normalizeVersionTag) {
            $master = '000000';
            $latest = '999999';
            $matches = [];
            switch (true) {
                case $tag === 'latest':
                    return '40'.str_repeat($latest, 2);
                case $tag === 'master':
                    return '10'.str_repeat($master, 2);
                case preg_match('/(\d+(?:\.\d+)+)-master/', $tag, $matches):
                    return '20'.$normalizeVersionTag($matches[1]).$master;
                case preg_match('/(\d+(?:\.\d+)+)-v(\d+(?:\.\d+)+)/', $tag, $matches):
                    $detailLevel = (string) substr_count($tag, '.');
                    return "3$detailLevel".$normalizeVersionTag($matches[1]).$normalizeVersionTag($matches[2]);
                default:
                    return '00'.'000000';
            }
        };

        usort($tags, function ($tagA, $tagB) use ($normalizeVersion) {
            return -strcmp($normalizeVersion($tagA), $normalizeVersion($tagB));
        });
    }

    protected function printPrettyTitle(OutputInterface $output, string $title)
    {
        $output->writeln(PHP_EOL.PHP_EOL.$this->getPrettyTitle($title).PHP_EOL);
    }

    protected function getPrettyTitle(string $title)
    {
        return str_pad("  $title  ", 50, '#', STR_PAD_BOTH);
    }

    protected function getTwig(string $templateDir): TwigEnvironment
    {
        static $twigs = [];
        if (! isset($twigs[$templateDir])) {
            $twigs[$templateDir] = new TwigEnvironment(new TwigFilesystemLoader($templateDir, $this->config->rootDir));
        }

        return $twigs[$templateDir];
    }

    protected function isRoot(): bool
    {
        return trim(`whoami`) === 'root';
    }

    protected function detectFramework(): string
    {
        if (file_exists($this->config->rootDir.'/symfony.lock')) {
            return $this->data::FRAMEWORK_SYMFONY;
        } else {
            return $this->data::FRAMEWORK_NONE;
        }
    }

    protected function getDockerizeTemplateDir(): string
    {
        return $this->config->rootDir.'/templates/dockerizer';
    }

    protected function getOtherTemplateDir(): string
    {
        return $this->config->rootDir.'/templates/message';
    }

    /**
     * @return Generator|SplFileInfo[]
     */
    protected function getDockerizeTemplateFiles()
    {
        static $files = null;
        if (is_null($files)) {
            $files = [];
            $finder = new Finder();
            $finder
                ->ignoreDotFiles(false)
                ->files()
                ->in($this->getDockerizeTemplateDir());

            // check if there are any results
            if ($finder->hasResults()) {
                foreach ($finder as $file) {
                    $files[] = $file;
                }
            }
        }

        foreach ($files as $file) {
            yield $file;
        }
    }

    protected function anyTemplateFilesExists(): bool
    {
        foreach ($this->getDockerizeTemplateFiles() as $file) {
            if (file_exists('/app/'.$file->getFilenameWithoutExtension())) {
                return true;
            }
        }

        return false;
    }

    protected function isAppDirMounted(): bool
    {
        return file_exists('/app/.empty');
    }

    protected function isAppEmpty(): bool
    {
        $finder = new Finder();
        $finder
            ->ignoreDotFiles(false)
            ->depth(0)
            ->in('/app');

        return ! $finder->hasResults();
    }

    protected function getAppsAndTools(): array
    {
        $apps = [];

        $apps['php'] = ['name' => 'php', 'version' => PHP_VERSION];

        $matches = [];
        if (preg_match('/Composer version (\d+(?:\.\d+)+).*/', $this->getComposerVersion(), $matches)) {
            $apps['composer'] = ['name' => 'composer', 'version' => $matches[1]];
        }

        $matches = [];
        if (preg_match('/Symfony CLI version v(\d+(?:\.\d+)+).*/', $this->getSymfonyVersion(), $matches)) {
            $apps['symfony'] = ['name' => 'symfony cli', 'version' => $matches[1]];
        }

        return $apps;
    }

    protected function getComposerVersion(): ?string
    {
        $matches = [];
        if (preg_match('/Composer version (\d+(?:\.\d+)+).*/', trim(shell_exec('composer --version --no-ansi')), $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function getSymfonyVersion(): ?string
    {
        $matches = [];
        if (preg_match('/Symfony CLI version v(\d+(?:\.\d+)+).*/', trim(shell_exec('symfony version --no-ansi')), $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function getNginxLatestTag(): ?string
    {
        $tags = $this->getDockerHubTags(
            'geekstuffreal/nginx-php-alpine',
            '-v'
        );

        return $tags ? array_shift($tags) : 'latest';
    }

    protected function getFpmLatestTagForCurrentPhpVersion(): ?string
    {
        $tags = $this->getDockerHubTags(
            'geekstuffreal/php-fpm-alpine',
            sprintf('%s.%s', PHP_MAJOR_VERSION, PHP_MINOR_VERSION)
        );

        return $tags ? array_shift($tags) : 'latest';
    }

    protected function getDockerHubTags(string $repo, string $pattern): ?array
    {
        $client = HttpClient::create();
        $url = sprintf(
            'https://registry.hub.docker.com/v2/repositories/%s/tags?page_size=1024&%s',
            $repo,
            $pattern ? "name=$pattern" : ""
        );

        try {
            $response = $client->request('GET', $url);
            $content = $response->getContent();
            $tags = [];
            foreach (json_decode($content)->results as $tag) {
                $tags[] = $tag->name;
            }
            $this->sortTags($tags);

            return $tags;
        } catch (Throwable $e) {
            return null;
        }
    }
}
