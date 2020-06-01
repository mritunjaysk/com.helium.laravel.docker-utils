<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;

class DockerExecCommand extends DockerCommand
{
	protected $signature = 'docker:exec';

	protected $description = 'Executes commands inside the project docker container.';

	public function handle()
	{
	}
}