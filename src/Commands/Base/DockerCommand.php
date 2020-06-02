<?php

namespace Helium\DockerUtils\Commands\Base;

use Helium\DockerUtils\Facades\TemplateFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DockerCommand extends Command
{
	protected const MYSQL = 'mysql';
	protected const POSTGRESQL = 'pgsql';

	protected const DB_CHOICES = [
		self::MYSQL,
		self::POSTGRESQL
	];

	protected const EXPECTED_SERVICES = [
		'nginx',
		'postgres',
		'mysql',
		'adminer',
		'mailhog'
	];

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
	 * Check that helium/docker-dev-base is installed as a global composer package.
	 * If not, attempt to install it.
	 */
	protected function installGlobalContainers()
	{
		$this->info('Checking for global containers...');

		if (!file_exists(getenv('HOME') . '/.docker/com.helium.docker.dev-base'))
		{
			shell_exec('mkdir ~/.docker 2> /dev/null');

			$this->info('Cloning helium/docker-dev-base...');

			shell_exec('cd ~/.docker && git clone https://bitbucket.org/teamhelium/com.helium.docker.dev-base.git 2> /dev/null');

			$this->info('Successfully cloned helium/docker-dev-base');
		}
		else
		{
			$this->pullGlobalContainers();
		}
	}

	/**
	 * Pull updates to the helium/docker-dev-base repository.
	 */
	protected function pullGlobalContainers()
	{
		$this->info('Updating global containers...');

		shell_exec('cd ~/.docker/com.helium.docker.dev-base && git fetch origin --quiet && git reset --hard origin/master 2> /dev/null');

		$this->info('Successfully updated global containers');
	}

	/**
	 * Check all running containers for expected list.
	 * If not, attempt to start them.
	 */
	protected function startupGlobalContainers()
	{
		$this->info('Checking status of global containers...');

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
			$this->info('Starting global containers...');

			$command = 'cd ~/.docker/com.helium.docker.dev-base && docker-compose up -d 2> /dev/null';

			exec($command, $output, $return);

			if ($return != 0)
			{
				$this->error('Failed to start shared containers');
				$this->info('Please start your shared container set and try again');

				exit($return);
			}

			sleep(10);

			$this->info('Successfully started global containers');
		}
		else
		{
			$this->info('Global containers already started');
		}
	}

	/**
	 * Shut down global containers.
	 */
	protected function stopGlobalContainers()
	{
		$this->info('Shutting down global containers...');

		foreach (self::EXPECTED_SERVICES as $service)
		{
			shell_exec("docker stop $service 2> /dev/null");
		}

		$this->info('Successfully shut down global containers');
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

	/**
	 * Shut down project containers.
	 */
	protected function stopProjectContainers(?bool $shutdownGlobal = null)
	{
		if (is_null($shutdownGlobal))
		{
			$shutdownGlobal = $this->confirm('Shut down global containers too?', false);
		}

		$this->info('Shutting down project containers...');

		shell_exec('docker-compose down 2> /dev/null');

		$this->info('Successfully shut down project containers');

		if ($shutdownGlobal)
		{
			$this->stopGlobalContainers();
		}
	}

	protected function getPhpContainerName(): string
	{
		$output = shell_exec('docker-compose ps | grep _php');

		if (empty($output))
		{
			$this->error('Could not enter the docker container shell');
			$this->warn('Is your container running?');
			$this->warn('Try running `php artisan docker:start`');

			exit(1);
		}

		return preg_split('/\s+/', $output)[0];
	}

	protected function updateEnv(array $input)
	{
		$envVars = [
			'APP_NAME' => $input['CONTAINER_NAME'],
			'APP_URL' => 'http://' . $input['HOSTNAME'],
			'DB_CONNECTION' => $input['DB'],
			'DB_HOST' => $input['DB'],
			'DB_PORT' => $input['DB'] == self::MYSQL ? 3306 : 5432,
			'DB_DATABASE' => $input['DB_DATABASE'],
			'DB_USERNAME' => $input['DB_USER'],
			'DB_PASSWORD' => $input['DB_PASSWORD']
		];

		if ($input['MAILHOG'])
		{
			$envVars = array_merge($envVars, [
				'MAIL_MAILER' => 'smtp',
				'MAIL_HOST' => 'mailhog',
				'MAIL_PORT' => 1025,
				'MAIL_USERNAME' => 'null',
				'MAIL_PASSWORD' => 'null',
				'MAIL_ENCRYPTION' => 'null',
				'MAIL_FROM_ADDRESS' => $input['CONTAINER_NAME'] . '@heliumservices.com',
				'MAIL_FROM_NAME' =>'${APP_NAME}'
			]);
		}

		$env = file_get_contents('.env');
		$envExample = file_get_contents('.env.example');

		foreach ($envVars as $key => $value)
		{
			if (Str::of($env)->contains($key))
			{
				$env = preg_replace("/$key=.*/", "$key=$value", $env);
			}
			else
			{
				$env .= "$key=$value\n";
			}

			if (Str::of($envExample)->contains($key))
			{
				$envExample = preg_replace("/$key=.*/", "$key=$value", $envExample);
			}
			else
			{
				$envExample .= "$key=$value\n";
			}
		}

		TemplateFacade::writeFile('.env', $env);
		TemplateFacade::writeFile('.env.example', $envExample);
	}

	protected function updateHosts(array $input)
	{
		$line = '127.0.0.1 ' . $input['HOSTNAME'];

		if (empty($input['SUDO_PASSWORD']))
		{
			$this->warn('No sudo password given, could not update /etc/hosts');
			$this->info('Please add the following line to your /etc/hosts file:');
			$this->info($line);
		}
		else
		{
			$this->info('Updating /etc/hosts...');

			$command = "echo '{$input['SUDO_PASSWORD']}' | sudo -S -- sh -c \"echo \\\"{$line}\\\" >> /etc/hosts\" 2> /dev/null";

			exec($command, $output, $return);

			if ($return != 0)
			{
				$this->error('Could not update /etc/hosts');
				$this->info('Please add the following line to your /etc/hosts file:');
				$this->info($line);
			}
			else
			{
				$this->info('Successfully updated /etc/hosts');
			}
		}
	}

	protected function createDatabase(array $input)
	{
		$this->info('Creating database...');

		switch ($input['DB'])
		{
			case self::MYSQL:
				$this->warn('Creating database for MySQL is not set up yet');
				$this->warn("Please create the database {$input['DB_DATABASE']}");
				return;
				break;
			case self::POSTGRESQL:
				$command = "docker exec -it pgsql bash -c \"echo \\\"SELECT 'CREATE DATABASE {$input['DB_DATABASE']}' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '{$input['DB_DATABASE']}')\gexec\\\" | psql -U pgsql && echo \\\"SELECT 'CREATE DATABASE {$input['DB_DATABASE']}_test' WHERE NOT EXISTS(SELECT FROM pg_database WHERE datname = '{$input['DB_DATABASE']}_test')\gexec\\\" | psql -U pgsql\"";

				exec($command, $output, $return);
				break;
			default:
				$this->error('Invalid database system');
				$this->warn("Please create the database {$input['DB_DATABASE']}");
		}

		if ($return != 0)
		{
			$this->error('Failed to create database');
			$this->warn("Please create the database {$input['DB_DATABASE']}");

			exit($return);
		}

		$this->info('Successfully created database');
	}

	protected function createDatabaseCredentials(array $input)
	{
		$this->info('Creating database credentials...');

		switch ($input['DB'])
		{
			case self::MYSQL:
				$this->warn('Creating database credentials for MySQL is not set up yet');
				$this->warn("Please create the database user {$input['DB_USER']}");
				return;
				break;
			case self::POSTGRESQL:
				$command = "docker exec -it pgsql bash -c \"echo \\\"REASSIGN OWNED BY {$input['DB_USER']} TO pgsql; DROP OWNED BY {$input['DB_USER']}; DROP ROLE IF EXISTS {$input['DB_USER']}; CREATE ROLE {$input['DB_USER']} LOGIN PASSWORD '{$input['DB_PASSWORD']}'; GRANT ALL PRIVILEGES ON DATABASE {$input['DB_DATABASE']} TO {$input['DB_USER']}; GRANT ALL PRIVILEGES ON DATABASE {$input['DB_DATABASE']}_test TO {$input['DB_USER']};\\\" | psql -U pgsql\"";

				exec($command, $output, $return);
				break;
			default:
				$this->error('Invalid database system');
				$this->warn("Please create the database user {$input['DB_USER']}");
		}

		if ($return != 0)
		{
			$this->error('Failed to create database credentials');
			$this->warn("Please create the database user {$input['DB_USER']}");

			exit($return);
		}

		$this->info('Successfully created database credentials');
	}
}