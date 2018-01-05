<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle\Command;

use Issei\SimpleJobSchedule\Scheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class ScheduleCommand extends Command
{
    /**
     * @var Scheduler
     */
    private $scheduler;

    public function __construct(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('issei_simple_job_queue:schedule')
            ->addOption('max-runtime', null, InputOption::VALUE_OPTIONAL, 'max-runtime (in sec)', 60 * 60)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $maxRuntimeInSec = (function ($v) { return is_numeric($v) ? (int) $v : -1; })($input->getOption('max-runtime'));
        if (30 > $maxRuntimeInSec || 86400 < $maxRuntimeInSec) {
            throw new \InvalidArgumentException('The option [max-runtime] must be an int of 60-86400.');
        }

        $this->scheduler->daemon($maxRuntimeInSec);
    }
}
