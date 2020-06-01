<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;

class DockerStartCommand extends DockerCommand
{
	protected $signature = 'docker:start';

	protected $description = 'Starts docker continers for project.';

	public function handle()
	{
		$this->startupDocker();
		$this->startupSharedContainers();
		$this->startupProjectContainers();

		$this->info('Done');
	}
}