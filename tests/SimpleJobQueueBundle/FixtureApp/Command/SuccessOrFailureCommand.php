<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobQueueBundle\FixtureApp\Command;

use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobSchedule\ScheduleInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class SuccessOrFailureCommand extends Command implements ScheduleInterface
{
    protected function configure(): void
    {
        $this
            ->setName('app:success-or-failure')
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'In seconds', '0')
            ->addOption('exit-code', null, InputOption::VALUE_OPTIONAL, 'exit-code as-is or specific range like "min..max" (ex: 0..10)', '0')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exitCode = (function (string $numeric) {
            if (is_numeric($numeric)) {
                return (int) $numeric;
            }

            return 0 === preg_match('/^(\d+)\.\.(\d+)$/', $numeric, $m) ? 0 : random_int((int) $m[1], (int) $m[2]);
        })($input->getOption('exit-code'));

        $sleep = (int) $input->getOption('sleep');

        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if ($sleep > 0) {
            $output->writeln('Zzz');

            sleep(1);

            for ($i = $sleep; 0 < $i; --$i) {
                $counter = $i;

                if (0 === $i % 2) {
                    $errorOutput->writeln("<error>...${counter}</error>");
                } else {
                    $output->writeln("<info>...${counter}</info>");
                }

                sleep(1);
            }
        }

        if (0 === $exitCode) {
            $output->writeln('<info>Successful</info>');
        } else {
            $errorOutput->writeln('<error>Failed</error>');
        }

        return $exitCode;
    }

    public function shouldRun(\DateTimeInterface $lastScheduledAt): bool
    {
        return 5 <= $lastScheduledAt->diff(new \DateTimeImmutable('now'))->s;
    }

    public function createJob(): Job
    {
        return new Job('app:success-or-failure', ['--sleep=2']);
    }
}
