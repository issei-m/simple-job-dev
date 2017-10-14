<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Console\Command;

use Issei\SimpleJobQueue\Console\ConsoleAppProcessFactory;
use Issei\SimpleJobQueue\Console\OutputReporter;
use Issei\SimpleJobQueue\QueueInterface;
use Issei\SimpleJobQueue\ReporterChain;
use Issei\SimpleJobQueue\ReporterInterface;
use Issei\SimpleJobQueue\Worker;
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
     * @var QueueInterface
     */
    private $queue;

    /**
     * @var ReporterInterface
     */
    private $reporter;

    public function __construct(QueueInterface $queue, ReporterInterface $reporter, string $name = 'run')
    {
        $this->queue = $queue;
        $this->reporter = $reporter;

        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('worker-name', null, InputOption::VALUE_OPTIONAL, 'worker-name')
            ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'max-jobs', 4)
            ->addOption('max-runtime', null, InputOption::VALUE_OPTIONAL, 'max-runtime (in sec)', 60 * 15)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workerName = $input->getOption('worker-name') ?: 'worker-' . (string) posix_getpid();
        $output->writeln('Started worker: ' . $workerName);

        $maxJobs = (function ($v) { return is_numeric($v) ? (int) $v : -1; })($input->getOption('max-jobs'));
        if (1 > $maxJobs || 10 < $maxJobs) {
            throw new \InvalidArgumentException('The option [max-jobs] must be an int of 1-10.');
        }

        $maxRuntimeInSec = (function ($v) { return is_numeric($v) ? (int) $v : -1; })($input->getOption('max-runtime'));
        if (30 > $maxRuntimeInSec || 86400 < $maxRuntimeInSec) {
            throw new \InvalidArgumentException('The option [max-runtime] must be an int of 30-86400.');
        }

        $worker = new Worker(
            $workerName,
            $this->queue,
            new ReporterChain($this->reporter, new OutputReporter($output)),
            new ConsoleAppProcessFactory($this->getApplication()),
            $maxJobs
        );
        $worker->start($maxRuntimeInSec);
    }
}
