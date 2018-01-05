<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class RemoveInternalParametersPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $container->getParameterBag()->remove('issei_simple_job_queue.default_queue');
    }
}
