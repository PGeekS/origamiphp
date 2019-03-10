<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DefaultCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'origami:default';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Wrapper of the default "list" command');
        $this->setHidden(true);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $command = $this->getApplication()->find('list');
        $arguments = ['namespace' => 'origami'];

        $listInput = new ArrayInput($arguments);
        $command->run($listInput, $output);
    }
}