<?php

declare(strict_types=1);

namespace App\Command\Database;

use App\Command\AbstractBaseCommand;
use App\Exception\OrigamiExceptionInterface;
use App\Helper\CurrentContext;
use App\Middleware\Binary\Docker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RestoreCommand extends AbstractBaseCommand
{
    /** {@inheritdoc} */
    protected static $defaultName = 'origami:database:restore';

    private CurrentContext $currentContext;
    private Docker $docker;

    public function __construct(CurrentContext $currentContext, Docker $docker, ?string $name = null)
    {
        parent::__construct($name);

        $this->currentContext = $currentContext;
        $this->docker = $docker;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Restores an archive of the volume used for the database');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $environment = $this->currentContext->getEnvironment($input);
            $this->currentContext->setActiveEnvironment($environment);

            if ($output->isVerbose()) {
                $this->printEnvironmentDetails($environment, $io);
            }

            $this->docker->resetDatabaseVolume();
            $this->docker->restoreDatabaseVolume();
            $this->docker->restartServices();
        } catch (OrigamiExceptionInterface $exception) {
            $io->error($exception->getMessage());
            $exitCode = Command::FAILURE;
        }

        return $exitCode ?? Command::SUCCESS;
    }
}
