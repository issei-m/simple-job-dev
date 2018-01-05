<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder();
        $tb->root('issei_simple_job_queue')
            ->beforeNormalization()
                ->ifTrue(function ($v) { return \is_array($v) && !\array_key_exists('queues', $v); })
                ->then(function (array $v) {
                    $normalized = [];

                    foreach (['reporter', 'scheduler'] as $nonQueueDirective) {
                        if (\array_key_exists($nonQueueDirective, $v)) {
                            $normalized[$nonQueueDirective] = $v[$nonQueueDirective];
                            unset($v[$nonQueueDirective]);
                        }
                    }

                    $normalized['queues'] = ['default' => $v];

                    return $normalized;
                })
            ->end()
            ->beforeNormalization()
                ->ifTrue(function ($v) { return \is_array($v) && !empty($v) && !\array_key_exists('default_queue', $v); })
                ->then(function (array $v) {
                    $v['default_queue'] = \key($v['queues']);

                    return $v;
                })
            ->end()
            ->children()
                ->scalarNode('default_queue')->isRequired()->end()
                ->append($this->getQueuesNode())
                ->append($this->getReporterNode())
                ->append($this->getSchedulerNode())
            ->end()
        ;

        return $tb;
    }

    private function getQueuesNode(): NodeDefinition
    {
        $node = (new TreeBuilder())->root('queues');
        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
                ->enumNode('type')->isRequired()->values(['rdb', 'redis'])->end()

                ->variableNode('params')->info('The construct parameters for the connection instance.')->end()
                ->scalarNode('connection_id')->info('The specific connection service id.')->end()

                ->scalarNode('process_factory')->info('The specific process factory service id.')->end()

                // for redis
                ->scalarNode('key')->end()

                // for rdb
                ->variableNode('schema')
                    ->validate()
                        ->ifTrue(function ($v) { return !\is_array($v); })
                        ->thenInvalid('Should be an array.')
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'redis' === $v['type'] && !isset($v['key']); })
                ->thenInvalid('The "key" option must be provided if "type" is redis.')
            ->end()
        ;

        return $node;
    }

    private function getReporterNode(): NodeDefinition
    {
        return (new TreeBuilder())->root('reporter')
            ->canBeEnabled()
            ->children()
                ->variableNode('params')
                    ->validate()
                        ->ifTrue(function ($v) { return !\is_array($v); })
                        ->thenInvalid('Should be an array.')
                    ->end()
                ->end()
                ->scalarNode('connection_id')->info('The specific connection service id.')->end()

                ->variableNode('schema')
                    ->validate()
                        ->ifTrue(function ($v) { return !\is_array($v); })
                        ->thenInvalid('Should be an array.')
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function getSchedulerNode(): NodeDefinition
    {
        return (new TreeBuilder())->root('scheduler')
            ->beforeNormalization()
                ->ifString()
                ->then(function ($v) { return ['type' => $v]; })
            ->end()
            ->canBeEnabled()
            ->children()
                ->enumNode('type')->values(['rdb', 'redis'])->isRequired()->end()

                ->variableNode('params')->info('The construct parameters for the connection instance.')->end()
                ->scalarNode('connection_id')->info('The specific connection service id.')->end()

                // for redis
                ->scalarNode('key')->end()

                // for rdb
                ->scalarNode('table')->end()
                ->scalarNode('key_column')->end()
                ->scalarNode('time_column')->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'redis' === $v['type'] && !isset($v['key']); })
                ->thenInvalid('The "key" option must be provided if "type" is redis.')
            ->end()
        ;
    }
}
