<?php

declare(strict_types=1);

namespace App\Tests\Service\Middleware\Binary;

use App\Service\CurrentContext;
use App\Service\Middleware\Binary\Docker;
use App\Service\Wrapper\ProcessFactory;
use App\Tests\TestEnvironmentTrait;
use Iterator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @covers \App\Service\Middleware\Binary\Docker
 */
final class DockerTest extends TestCase
{
    use ProphecyTrait;
    use TestEnvironmentTrait;

    public function testItRetrievesBinaryVersion(): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $process = $this->prophesize(Process::class);
        $process->getOutput()
            ->shouldBeCalledOnce()
            ->willReturn('docker version')
        ;

        $processFactory->runBackgroundProcess(Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process)
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertSame('docker version', $docker->getVersion());
    }

    /**
     * @dataProvider provideDockerComposeScenarios
     *
     * @param string[] $action
     */
    public function testItExecutesDockerComposeInstruction(string $function, array $action): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext->getProjectName()
            ->shouldBeCalledTimes(2)
            ->willReturn($projectName)
        ;

        $command = array_merge(['docker', 'compose'], $this->getDefaultDockerComposeOptions($projectName), $action);

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcess($command, Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->{$function}());
    }

    public function provideDockerComposeScenarios(): Iterator
    {
        // @see \App\Middleware\Binary\Docker::pullServices
        yield 'pull' => [
            'pullServices',
            ['pull'],
        ];

        // @see \App\Middleware\Binary\Docker::buildServices
        yield 'build' => [
            'buildServices',
            ['build', '--pull', '--parallel'],
        ];

        // @see \App\Middleware\Binary\Docker::fixPermissionsOnSharedSSHAgent
        yield 'permissions' => [
            'fixPermissionsOnSharedSSHAgent',
            ['exec', '-T', 'php', 'bash', '-c', 'chown www-data:www-data /run/host-services/ssh-auth.sock'],
        ];

        // @see \App\Middleware\Binary\Docker::startServices
        yield 'start' => [
            'startServices',
            ['up', '--build', '--detach', '--remove-orphans'],
        ];

        // @see \App\Middleware\Binary\Docker::stopServices
        yield 'stop' => [
            'stopServices',
            ['stop'],
        ];

        // @see \App\Middleware\Binary\Docker::restartServices
        yield 'restart' => [
            'restartServices',
            ['restart'],
        ];

        // @see \App\Middleware\Binary\Docker::showServicesStatus
        yield 'status' => [
            'showServicesStatus',
            ['ps'],
        ];

        // @see \App\Middleware\Binary\Docker::removeServices
        yield 'uninstall' => [
            'removeServices',
            ['down', '--rmi', 'local', '--volumes', '--remove-orphans'],
        ];
    }

    public function testItDisplaysResourceUsage(): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext
            ->getProjectName()
            ->shouldBeCalledTimes(2)
            ->willReturn($projectName)
        ;

        $action = ['ps --quiet | xargs docker stats'];
        $command = implode(' ', array_merge(['docker', 'compose'], $this->getDefaultDockerComposeOptions($projectName), $action));

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcessFromShellCommandLine($command, Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->showResourcesUsage());
    }

    /**
     * @dataProvider provideDockerComposeLogsScenarios
     */
    public function testItDisplaysLogs(?int $tail = null, ?string $service = null): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext
            ->getProjectName()
            ->shouldBeCalledTimes(2)
            ->willReturn($projectName)
        ;

        $action = ['logs', '--follow', sprintf('--tail=%s', $tail ?? 0)];
        if ($service) {
            $action[] = $service;
        }
        $command = array_merge(['docker', 'compose'], $this->getDefaultDockerComposeOptions($projectName), $action);

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcess($command, Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->showServicesLogs($tail, $service));
    }

    public function provideDockerComposeLogsScenarios(): Iterator
    {
        yield 'no·modifiers' => [];
        yield 'tail only' => [42, null];
        yield 'tail and service' => [42, 'php'];
        yield 'service only' => [null, 'php'];
    }

    public function testItOpensTerminalWithSpecificUser(): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext
            ->getProjectName()
            ->shouldBeCalledOnce()
            ->willReturn($projectName)
        ;

        $command = "docker exec -it --user=www-data:www-data {$projectName}-php-1 bash --login";

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcessFromShellCommandLine($command, Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->openTerminal('php', 'www-data:www-data'));
    }

    public function testItOpensTerminalWithoutSpecificUser(): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext
            ->getProjectName()
            ->shouldBeCalledOnce()
            ->willReturn($projectName)
        ;

        $command = "docker exec -it {$projectName}-php-1 bash --login";

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcessFromShellCommandLine($command, Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->openTerminal('php'));
    }

    public function testItDumpsMysqlDatabase(): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext
            ->getProjectName()
            ->shouldBeCalledOnce()
            ->willReturn($projectName)
        ;

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcessFromShellCommandLine(Argument::containingString('> /path/to/dump_file.sql'), Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->dumpMysqlDatabase('/path/to/dump_file.sql'));
    }

    public function testItRestoresMysqlDatabase(): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext
            ->getProjectName()
            ->shouldBeCalledOnce()
            ->willReturn($projectName)
        ;

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcessFromShellCommandLine(Argument::containingString('< /path/to/dump_file.sql'), Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->restoreMysqlDatabase('/path/to/dump_file.sql'));
    }

    public function testItDumpsPostgresDatabase(): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext
            ->getProjectName()
            ->shouldBeCalledOnce()
            ->willReturn($projectName)
        ;

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcessFromShellCommandLine(Argument::containingString('> /path/to/dump_file.sql'), Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->dumpPostgresDatabase('/path/to/dump_file.sql'));
    }

    public function testItRestoresPostgresDatabase(): void
    {
        $currentContext = $this->prophesize(CurrentContext::class);
        $processFactory = $this->prophesize(ProcessFactory::class);
        $installDir = '/var/docker';

        $environment = $this->createEnvironment();
        $projectName = "{$environment->getType()}_{$environment->getName()}";
        $process = $this->prophesize(Process::class);

        $currentContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $currentContext
            ->getProjectName()
            ->shouldBeCalledOnce()
            ->willReturn($projectName)
        ;

        $process
            ->isSuccessful()
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $processFactory
            ->runForegroundProcessFromShellCommandLine(Argument::containingString('< /path/to/dump_file.sql'), Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($process->reveal())
        ;

        $docker = new Docker($currentContext->reveal(), $processFactory->reveal(), $installDir);
        static::assertTrue($docker->restorePostgresDatabase('/path/to/dump_file.sql'));
    }

    /**
     * @return string[]
     */
    private function getDefaultDockerComposeOptions(string $projectName): array
    {
        return [
            "--file={$this->location}/var/docker/docker-compose.yml",
            "--project-directory={$this->location}",
            "--project-name={$projectName}",
        ];
    }
}
