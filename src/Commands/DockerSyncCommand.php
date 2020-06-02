<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;

class DockerSyncCommand extends DockerCommand
{
	protected $signature = 'docker:sync';

	protected $description = 'Updates global docker containers';

	protected function getInput(): array
	{
		$input = [];

		$input['SUDO_PASSWORD'] = $this->secret('sudo password (required to update /etc/hosts file)', '');

		return $input;
	}

	public function handle()
	{
		$input = $this->getInput();

		$config = json_decode(file_get_contents('./docker_utils_config.json'), true);

		$input = array_merge($input, $config);

		$this->updateHosts($input);
		$this->startupDocker();
		$this->stopGlobalContainers();
		$this->installGlobalContainers();
		$this->startupGlobalContainers();
		$this->createDatabase($input);
		$this->createDatabaseCredentials($input);
		$this->stopProjectContainers(false);
		$this->buildProjectContainers();
		$this->startupProjectContainers();
	}
}