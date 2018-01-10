<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle\Command;

use Issei\SimpleJobQueue\ReporterInterface;
use Issei\SimpleJobQueue\Worker;
use Issei\SimpleJobQueueBundle\JobQueue\ConsoleAppProcessFactory;
use Issei\SimpleJobQueueBundle\QueueRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class RunCommand extends Command
{
    /**
     * @var QueueRegistry
     */
    private $registry;

    /**
     * @var ReporterInterface
     */
    private $reporter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(QueueRegistry $registry, ReporterInterface $reporter, LoggerInterface $logger)
    {
        parent::__construct();

        $this->reporter = $reporter;
        $this->logger = $logger;
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('issei_simple_job_queue:run')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'The queue name')
            ->addOption('worker-name', null, InputOption::VALUE_OPTIONAL, 'The worker name')
            ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'The maximum jobs', 4)
            ->addOption('max-runtime', null, InputOption::VALUE_OPTIONAL, 'The max runtime (in sec)', 60 * 15)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queueName = $input->getOption('queue') ?: 'default';
        $workerName = $input->getOption('worker-name') ?: 'worker-' . (string) posix_getpid();

        $maxJobs = (function ($v) { return is_numeric($v) ? (int) $v : -1; })($input->getOption('max-jobs'));
        if (1 > $maxJobs || 10 < $maxJobs) {
            throw new \InvalidArgumentException('The option [max-jobs] must be an int of 1-10.');
        }

        $maxRuntimeInSec = (function ($v) { return is_numeric($v) ? (int) $v : -1; })($input->getOption('max-runtime'));
        if (30 > $maxRuntimeInSec || 86400 < $maxRuntimeInSec) {
            throw new \InvalidArgumentException('The option [max-runtime] must be an int of 30-86400.');
        }

        $queue = $this->registry->getQueue($queueName);
        $processFactory = $this->registry->getProcessFactoryFor($queue)
            ?? new ConsoleAppProcessFactory($this->getApplication(), $input->hasOption('env') ? $input->getOption('env') : null)
        ;

        (new Worker($workerName, $queue, $this->reporter, $processFactory, $maxJobs, $this->logger))->start($maxRuntimeInSec);
    }
}
