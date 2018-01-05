<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle\DependencyInjection\Compiler;

use Issei\SimpleJobSchedule\Scheduler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class AddScheduleToSchedulerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(Scheduler::class)) {
            return;
        }

        $defaultQueueName = $container->getParameterBag()->get('issei_simple_job_queue.default_queue');

        foreach ($container->findTaggedServiceIds('issei_simple_job_queue.schedule', true) as $scheduleId => $args) {
            $queueName = $args['queue'] ?? $defaultQueueName;

            $container->getDefinition(Scheduler::class)
                ->addMethodCall('addSchedule', [
                    new Reference($scheduleId),
                    new Reference('issei_simple_job_queue.queue.' . $queueName),
                ])
            ;
        }
    }
}
