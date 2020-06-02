<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;

class DockerBuildCommand extends DockerCommand
{
	protected $signature = 'docker:build';

	protected $description = 'Rebuilds docker continers for project.';

	public function handle()
	{
		$this->buildProjectContainers();

		$this->info('Done');
	}
}