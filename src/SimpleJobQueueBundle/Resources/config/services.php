<?php

declare(strict_types=1);

use Issei\SimpleJobQueue\QueueInterface;
use Issei\SimpleJobQueue\ReporterInterface;
use Issei\SimpleJobQueueBundle\Command\RunCommand;
use Issei\SimpleJobQueueBundle\JobQueue\NullReporter;
use Issei\SimpleJobQueueBundle\QueueRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

assert(isset($container) && $container instanceof ContainerBuilder);

// Base
$container->register(QueueRegistry::class, QueueRegistry::class)
    ->setPublic(false)
;
$container->register(QueueInterface::class)
    ->setPublic(false)
;
$container->register(ReporterInterface::class, NullReporter::class)
    ->setPublic(false)
;

// Commands
$container->register(RunCommand::class, RunCommand::class)
    ->setArguments([
        new Reference(QueueRegistry::class),
        new Reference(ReporterInterface::class),
        new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
    ])
    ->addTag('console.command')
    ->addTag('monolog.logger', ['channel' => 'job'])
;
