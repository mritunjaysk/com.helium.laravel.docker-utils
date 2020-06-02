<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;

class DockerStopCommand extends DockerCommand
{
	protected $signature = 'docker:stop';

	protected $description = 'Stops docker continers for project.';

	public function handle()
	{
		$this->stopProjectContainers();

		$this->info('Done');
	}
}