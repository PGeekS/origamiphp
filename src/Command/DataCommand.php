<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\InvalidEnvironmentException;
use App\Exception\OrigamiExceptionInterface;
use App\Service\ApplicationContext;
use App\Service\Middleware\Binary\Docker;
use App\Service\Wrapper\OrigamiStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DataCommand extends AbstractBaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'origami:data';

    private ApplicationContext $applicationContext;
    private Docker $docker;

    public function __construct(ApplicationContext $applicationContext, Docker $docker, ?string $name = null)
    {
        parent::__construct($name);

        $this->applicationContext = $applicationContext;
        $this->docker = $docker;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Shows the usage statistics of running environment');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OrigamiStyle($input, $output);

        try {
            $this->applicationContext->loadEnvironment($input);
            $environment = $this->applicationContext->getActiveEnvironment();

            if ($output->isVerbose()) {
                $this->printEnvironmentDetails($environment, $io);
            }

            if (!$this->docker->showResourcesUsage()) {
                throw new InvalidEnvironmentException('An error occurred while checking the resources usage.');
            }
        } catch (OrigamiExceptionInterface $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
