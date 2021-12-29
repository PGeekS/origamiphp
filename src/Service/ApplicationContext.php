<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\FilesystemException;
use App\Exception\InvalidConfigurationException;
use App\Exception\InvalidEnvironmentException;
use App\Service\Setup\Validator;
use App\Service\Wrapper\ProcessProxy;
use App\ValueObject\EnvironmentEntity;
use Symfony\Component\Console\Input\InputInterface;

class ApplicationContext
{
    private EnvironmentEntity $environment;

    public function __construct(
        private ApplicationData $applicationData,
        private ProcessProxy $processProxy,
        private Validator $validator
    ) {
    }

    /**
     * Attempts to load the environment to manage in different ways and throws an exception if this is not possible.
     *
     * @throws FilesystemException
     * @throws InvalidConfigurationException
     * @throws InvalidEnvironmentException
     */
    public function loadEnvironment(InputInterface $input): void
    {
        // 1. Try to load the currently running environment.
        $environment = $this->applicationData->getActiveEnvironment();

        // 2. Try to load the environment from the user input (i.e. BuildCommand, StartCommand, and UninstallCommand).
        if (!$environment instanceof EnvironmentEntity && $input->hasArgument('environment')) {
            $argument = $input->getArgument('environment');

            if (\is_string($argument) && $argument !== '') {
                $environment = $this->applicationData->getEnvironmentByName($argument);
            }
        }

        // 3. Try to load the environment from the current location.
        if (!$environment instanceof EnvironmentEntity) {
            $location = $this->processProxy->getWorkingDirectory();
            $environment = $this->applicationData->getEnvironmentByLocation($location);
        }

        // 4. Throw an exception is there is still no defined environment.
        if (!$environment instanceof EnvironmentEntity) {
            throw new InvalidEnvironmentException('An environment must be given, please consider using the install command instead.');
        }

        $this->checkEnvironmentConfiguration($environment);
        $this->environment = $environment;
    }

    /**
     * Retrieves the currently active environment.
     */
    public function getActiveEnvironment(): EnvironmentEntity
    {
        return $this->environment;
    }

    /**
     * Retrieves the project name based on the currently active environment data.
     */
    public function getProjectName(): string
    {
        return "{$this->environment->getType()}_{$this->environment->getName()}";
    }

    /**
     * Checks whether the environment has been installed and correctly configured.
     *
     * @throws InvalidConfigurationException
     */
    private function checkEnvironmentConfiguration(EnvironmentEntity $environment): void
    {
        if (!$this->validator->validateConfigurationFiles($environment)) {
            throw new InvalidConfigurationException('The environment is not configured, consider executing the "install" command.');
        }
    }
}