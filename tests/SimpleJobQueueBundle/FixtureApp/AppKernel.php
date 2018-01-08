<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobQueueBundle\FixtureApp;

use Issei\SimpleJobQueueBundle\IsseiSimpleJobQueueBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class AppKernel extends Kernel
{
    use MicroKernelTrait;

    private $containerClassName;
    private $extraConfigs = [];

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new MonologBundle(),
            new IsseiSimpleJobQueueBundle(),
        ];
    }

    public function setContainerClassName(string $containerClassName): void
    {
        $this->containerClassName = $containerClassName;
    }

    protected function getContainerClass(): string
    {
        return $this->containerClassName ?: parent::getContainerClass();
    }

    public function addExtraConfig(string $configBaseName): void
    {
        $this->extraConfigs[] = $configBaseName;
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader): void
    {
        $c->setParameter('kernel.secret', 'secret');

        $loader->load(__DIR__ . '/config/config.yml');

        foreach ($this->extraConfigs as $configBaseName) {
            $loader->load(__DIR__ . '/config/extra/' . $configBaseName);
        }
    }

    public function getCacheDir(): string
    {
        return __DIR__ . '/../../../var/cache';
    }

    public function getLogDir(): string
    {
        return __DIR__ . '/../../../var/logs';
    }
}
