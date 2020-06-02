<?php

namespace Helium\DockerUtils\Commands\Base;

use Illuminate\Console\Command;

class DockerCommand extends Command
{
	protected const EXPECTED_SERVICES = [
		'postgres',
		'mysql',
		'adminer',
		'mailhog'
	];

	/**
	 * Check that helium/docker-dev-base is installed as a global composer package.
	 * If not, attempt to install it.
	 */
	protected function installDockerDevBase()
	{
		$this->info('Checking for shared containers...');

		if (!file_exists(getenv('HOME') . '/.docker/com.helium.docker.dev-base'))
		{
			shell_exec('mkdir ~/.docker 2> /dev/null');

			$this->info('Cloning helium/docker-dev-base...');

			shell_exec('cd ~/.docker && git clone git@bitbucket.org:teamhelium/com.helium.docker.dev-base.git 2> /dev/null');

			$this->info('Successfully cloned helium/docker-dev-base');
		}
		else
		{
			$this->info('Updating shared containers...');

			shell_exec('cd ~/.docker/com.helium.docker.dev-base && git pull 2> /dev/null');

			$this->info('Successfully updated shared containers');
		}
	}

	/**
	 * Check that docker is running.
	 * If not, attempt to start it.
	 */
	protected function startupDocker()
	{
		$this->info('Checking Docker status...');

		$statusCommand = 'docker stats --no-stream 2> /dev/null';

		exec($statusCommand, $output, $return);

		if ($return != 0)
		{
			$this->info('Starting Docker...');

			$startCommand = 'open --background -a Docker 2> /dev/null';
			exec($startCommand, $output, $return);

			if ($return != 0)
			{
				$this->error('Failed to start Docker');
				$this->info('Please make sure Docker is installed and try again');

				exit($return);
			}

			$start_time = time();

			do {
				if (time() - $start_time > 60)
				{
					$this->error('Failed to start Docker');
					$this->info('Please start Docker and try again');

					exit($return);
				}

				exec($statusCommand, $output, $return);
			} while ($return != 0);

			$this->info('Successfully started Docker');
		}
		else
		{
			$this->info('Docker already started');
		}
	}

	/**
	 * Check all running containers for expected list.
	 * If not, attempt to start them.
	 */
	protected function startupSharedContainers()
	{
		$this->info('Checking status of shared containers...');

		$command = 'docker ps';

		foreach (self::EXPECTED_SERVICES as $service)
		{
			$command .= " --filter \"name=$service\"";
		}

		$status = shell_exec($command);
		$parts = explode("\n", $status);

		//+2 for header and trailing whitespace
		if (count($parts) != count(self::EXPECTED_SERVICES) + 2)
		{
			$this->info('Starting shared containers...');

			$command = 'cd ~/.docker/com.helium.docker.dev-base && docker-compose up -d 2> /dev/null';

			exec($command, $output, $return);

			if ($return != 0)
			{
				$this->error('Failed to start shared containers');
				$this->info('Please start your shared container set and try again');

				exit($return);
			}

			$this->info('Successfully started shared containers');
		}
		else
		{
			$this->info('Shared containers already started');
		}
	}

	/**
	 * Build project containers
	 */
	protected function buildProjectContainers()
	{
		$this->info('Building project containers (this may take a while)...');

		$command = 'docker-compose build 2> /dev/null';

		exec($command, $output, $return);

		if ($return != 0)
		{
			$this->error('Failed to build project containers');
			$this->info('Please build and start your project containers manually');

			exit($return);
		}

		$this->info('Successfully finished building project containers');
	}

	/**
	 * Check if project containers are running.
	 * If not, attempt to start them.
	 */
	protected function startupProjectContainers()
	{
		$this->info('Starting project containers...');

		$command = 'docker-compose up -d 2> /dev/null';

		exec($command, $output, $return);

		if ($return != 0)
		{
			$this->error('Failed to start project containers');

			exit($return);
		}

		$this->info('Successfully started project containers');
	}
}