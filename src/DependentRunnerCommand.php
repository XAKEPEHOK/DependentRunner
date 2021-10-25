<?php
/**
 * Date: 10/25/21 5:58 PM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\DependentRunner;

use Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DependentRunnerCommand extends Command
{

    private Redis $redis;
    private string $redisKey;

    public function __construct(DependencyConnection $connection, string $name = 'dependent:run')
    {
        parent::__construct($name);
        $this->redis = $connection->getRedis();
        $this->redisKey = $connection->getRedisKey();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $input->getArgument('cmd');
        $isSingle = $input->getArgument('single');

        if ($isSingle && $this->redis->exists($this->getSingleKey($command))) {
            $output->writeln("<error>Command marked as single and already running</error>");
            return self::INVALID;
        }

        $id = uniqid(md5($command), true);

        $output->write('Starting...');
        while (true) {
            $process = Process::fromShellCommandline($command);
            $this->lockForSingle($command);

            if ($this->isReady()) {
                $output->writeln('');
                $output->writeln('<info>Started: </info>' . $command);
                $output->writeln('');
                $process->start(function ($type, $buffer) use ($output) {
                    if (Process::ERR === $type) {
                        $output->write("<error>{$buffer}</error>");
                    } else {
                        $output->write($buffer);
                    }
                });
            } else {
                $this->redis->del($this->getRedisKey("process:{$id}"));
                $this->redis->sRem($this->getRedisKey("set"), $id);
                $output->write('.');
            }

            while ($process->isRunning() && $this->isReady()) {
                usleep(500000);
                $this->redis->set($this->getRedisKey("process:{$id}"), 1, 2);
                $this->redis->sAdd($this->getRedisKey("set"), $id);
                $this->lockForSingle($command);
            }

            if ($process->isTerminated()) {
                $this->unlockForSingle($command);
                return self::SUCCESS;
            }

            if (!$this->isReady() && $process->isStarted() && !$process->isTerminated()) {
                $process->stop();
                $output->writeln('<error>Process terminated and will be restarted by dependency</error>');
                $output->write('Restarting...');
            }

            usleep(500000);
        }
    }

    protected function lockForSingle(string $command): void
    {
        $this->redis->set($this->getSingleKey($command), 1, 2);
    }

    protected function unlockForSingle(string $command): void
    {
        $this->redis->del($this->getSingleKey($command));
    }

    protected function getSingleKey(string $command): string
    {
        $md5 = md5($command);
        return $this->getRedisKey("single:{$md5}");
    }

    protected function isReady(): bool
    {
        $isReady = $this->redis->get($this->getRedisKey('state'));
        if ($isReady === false) {
            return true;
        }
        return (bool) $isReady;
    }

    protected function getRedisKey(string $key): string
    {
        return $this->redisKey . ':' . $key;
    }

    protected function configure()
    {
        $this->addArgument(
            'cmd',
            InputArgument::REQUIRED,
            'Command, that will be run'
        );

        $this->addArgument(
            'single',
            InputArgument::OPTIONAL,
            'Prevent multiple run',
            0
        );
    }

}