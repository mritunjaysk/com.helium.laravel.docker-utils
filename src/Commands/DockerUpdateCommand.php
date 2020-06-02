<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;

class DockerUpdateCommand extends DockerCommand
{
	protected $signature = 'docker:update';

	protected $description = 'Updates global docker containers';

	public function handle()
	{
		$this->stopGlobalContainers();
		$this->pullGlobalContainers();
		$this->startupGlobalContainers();
	}
}