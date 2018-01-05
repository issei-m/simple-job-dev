<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobQueueBundle\FixtureApp\Command;

use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\QueueInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class EnqueueSOFCommand extends ContainerAwareCommand
{
    /**
     * @var QueueInterface
     */
    private $queue;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
        parent::__construct('app:enqueue-sof');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        for ($i = 0; $i < 100; $i++) {
            $this->queue->enqueue(new Job('app:success-or-failure', ['--sleep=' . (string) random_int(0, 3)]), new \DateTimeImmutable(sprintf('+%d sec', random_int(0, 15))));
        }
    }
}
