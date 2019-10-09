<?php

declare(strict_types=1);

namespace App\Tests\Middleware\Binary;

use App\Entity\Environment;
use App\Helper\ProcessFactory;
use App\Middleware\Binary\DockerCompose;
use App\Validator\Constraints\ConfigurationFiles;
use App\Validator\Constraints\DotEnvExists;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \App\Middleware\Binary\DockerCompose
 */
final class DockerComposeTerminalTest extends TestCase
{
    private $validator;
    private $processFactory;
    private $location;
    private $environment;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->prophesize(ValidatorInterface::class);
        $this->processFactory = $this->prophesize(ProcessFactory::class);

        $this->location = sys_get_temp_dir().'/origami/DockerComposeTerminalTest';
        mkdir($this->location.'/var/docker', 0777, true);

        $this->environment = new Environment();
        $this->environment->setName('foo');
        $this->environment->setLocation(sys_get_temp_dir().'/origami/DockerComposeTerminalTest');
        $this->environment->setType('magento2');
        $this->environment->setActive(true);

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/../../../src/Resources/magento2/', $this->location.'/var/docker');
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->location)) {
            $filesystem = new Filesystem();
            $filesystem->remove($this->location);
        }

        $this->location = null;
    }

    public function testItOpensTerminalOnGivenServiceWithSpecificUser(): void
    {
        $this->validator->validate(Argument::any(), new DotEnvExists())
            ->shouldBeCalledOnce()
            ->willReturn(new ConstraintViolationList())
        ;
        $this->validator->validate(Argument::any(), new ConfigurationFiles())
            ->shouldBeCalledOnce()
            ->willReturn(new ConstraintViolationList())
        ;

        $process = $this->prophesize(Process::class);
        $process->isSuccessful()->shouldBeCalledOnce()->willReturn(true);

        $environmentVariables = [
            'COMPOSE_FILE' => $this->location.'/var/docker/docker-compose.yml',
            'COMPOSE_PROJECT_NAME' => $this->environment->getType().'_'.$this->environment->getName(),
            'DOCKER_PHP_IMAGE' => 'default',
            'PROJECT_LOCATION' => $this->location,
        ];

        $this->processFactory->runForegroundProcess(['docker-compose', 'exec', '-u', 'www-data:www-data', 'php', 'sh', '-l'], $environmentVariables)
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $dockerCompose = new DockerCompose($this->validator->reveal(), $this->processFactory->reveal());
        $dockerCompose->setActiveEnvironment($this->environment);

        static::assertTrue($dockerCompose->openTerminal('php', 'www-data:www-data'));
    }

    public function testItOpensTerminalOnGivenServiceWithoutSpecificUser(): void
    {
        $this->validator->validate(Argument::any(), new DotEnvExists())
            ->shouldBeCalledOnce()
            ->willReturn(new ConstraintViolationList())
        ;
        $this->validator->validate(Argument::any(), new ConfigurationFiles())
            ->shouldBeCalledOnce()
            ->willReturn(new ConstraintViolationList())
        ;

        $process = $this->prophesize(Process::class);
        $process->isSuccessful()->shouldBeCalledOnce()->willReturn(true);

        $environmentVariables = [
            'COMPOSE_FILE' => $this->location.'/var/docker/docker-compose.yml',
            'COMPOSE_PROJECT_NAME' => $this->environment->getType().'_'.$this->environment->getName(),
            'DOCKER_PHP_IMAGE' => 'default',
            'PROJECT_LOCATION' => $this->location,
        ];

        $this->processFactory->runForegroundProcess(['docker-compose', 'exec', 'php', 'sh', '-l'], $environmentVariables)
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $dockerCompose = new DockerCompose($this->validator->reveal(), $this->processFactory->reveal());
        $dockerCompose->setActiveEnvironment($this->environment);

        static::assertTrue($dockerCompose->openTerminal('php'));
    }
}