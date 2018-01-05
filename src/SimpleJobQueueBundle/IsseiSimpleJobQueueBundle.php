<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle;

use Issei\SimpleJobQueueBundle\DependencyInjection\Compiler\AddScheduleToSchedulerPass;
use Issei\SimpleJobQueueBundle\DependencyInjection\Compiler\RemoveInternalParametersPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class IsseiSimpleJobQueueBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddScheduleToSchedulerPass());
        $container->addCompilerPass(new RemoveInternalParametersPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
