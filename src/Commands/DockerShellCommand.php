<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;

class DockerShellCommand extends DockerCommand
{
	protected $signature = 'docker:shell';

	protected $description = 'Enters into the project docker shell.';

	public function handle()
	{
		$containerName = $this->getPhpContainerName();

		passthru("docker exec -it $containerName bash -l");
	}
}
