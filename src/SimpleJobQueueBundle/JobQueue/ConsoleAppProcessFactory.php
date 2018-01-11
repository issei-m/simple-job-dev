<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle\JobQueue;

use Issei\SimpleJobQueue\ExceptionInterface;
use Issei\SimpleJobQueue\ProcessFactoryInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class ConsoleAppProcessFactory implements ProcessFactoryInterface
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var string
     */
    private $env;

    private static $phpExecutable;

    public function __construct(Application $app, string $env = null)
    {
        $this->app = $app;
        $this->env = $env;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException|ExceptionInterface when the command cannot be found in the given application.
     */
    public function createProcess(string $command, iterable $arguments): Process
    {
        if (!$this->app->has($command)) {
            $message = sprintf('The command named "%s" could not be found in app "%s".', $command, $this->app->getName());

            throw new class($message) extends \RuntimeException implements ExceptionInterface {};
        }

        $arrayArguments = \is_array($arguments) ? $arguments : iterator_to_array($arguments);

        if ($this->env) {
            $arrayArguments[] = '--env=' . $this->env;
        }

        $phpExecutable = self::findPhpExecutable();

        return new Process(
            \array_merge([
                basename($phpExecutable),
                $this->guessConsoleFile(),
                $command,
            ], $arrayArguments),
            \dirname($phpExecutable)
        );
    }

    private static function findPhpExecutable(): string
    {
        if (!self::$phpExecutable) {
            self::$phpExecutable = (new PhpExecutableFinder())->find() ?: 'php';
        }

        return self::$phpExecutable;
    }

    private function guessConsoleFile(): string
    {
        $backtrace = debug_backtrace();
        $entryPoint = end($backtrace);

        if (!$entryPoint['object'] instanceof $this->app && 'run' === $entryPoint['function']) {
            $message = sprintf('The head of call stack must be the console file which making app "%s" itself run.', $this->app->getName());

            throw new class($message) extends \RuntimeException implements ExceptionInterface {};
        }

        return $entryPoint['file'];
    }
}
