<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;
use Helium\DockerUtils\Facades\TemplateFacade;
use Illuminate\Support\Str;

class DockerGenerateCommand extends DockerCommand
{
	protected const MYSQL = 'mysql';
	protected const POSTGRESQL = 'pgsql';

	protected const DB_CHOICES = [
		self::MYSQL,
		self::POSTGRESQL
	];

	protected $signature = 'docker:generate';

	protected $description = 'Generates docker containers for project.';

	protected function getTemplate(string $path, array $args = []): string
	{
		return TemplateFacade::generate(
			__DIR__ . "/../../templates/$path",
			$args
		);
	}

	protected function getInput(): array
	{
		$input = [];

		$input['CONTAINER_NAME'] = $this->ask('Container name');
		$input['HOSTNAME'] = $this->ask('Hostname');
		$input['WEB_PORT'] = $this->ask('Web Port (Unique across all Docker containers) [80##]');
		$input['PHP_PORT'] = $this->ask('PHP Port (Unique across all Docker containers) [90##]');
		$input['DB'] = $this->choice('Database:', self::DB_CHOICES, 0);
		$input['DB_DATABASE'] = $this->anticipate('Database name',
			[$input['CONTAINER_NAME']]);
		$input['DB_USER'] = $this->anticipate('Database user',
			[$input['CONTAINER_NAME']]);
		$input['DB_PASSWORD'] = $this->anticipate('Database user',
			[$input['CONTAINER_NAME']]);
		$input['MAILHOG'] = $this->confirm('Are you using MailHog?', true);
		$input['ENV'] = $this->confirm('Would you like to update .env?', true);
		$input['SUDO_PASSWORD'] = $this->secret('sudo password (required to update /etc/hosts file)', '');

		return $input;
	}

	protected function generateDockerfile(array $input)
	{
		$config = [];

		$db = $input['DB'];
		$config['DATABASE'] = $this->getTemplate("Dockerfile/partials/$db.template");

		$config['MAILHOG'] = $input['MAILHOG'] ? $this->getTemplate(
			'Dockerfile/partials/mailhog.template'
		): '';

		TemplateFacade::generate(
			__DIR__ . '/../../templates/Dockerfile/Dockerfile.template',
			$config,
			'./Dockerfile'
		);
	}

	protected function generateDockerCompose(array $input)
	{
		$config = [];

		$config['PHP_PORT'] = $input['PHP_PORT'];
		$config['WEB_PORT'] = $input['WEB_PORT'];
		$config['CONTAINER_NAME'] = $input['CONTAINER_NAME'];

		TemplateFacade::generate(
			__DIR__ . '/../../templates/docker-compose/docker-compose.yml.template',
			$config,
			'./docker-compose.yml'
		);
	}

	protected function generateDockerConfig(array $input)
	{
		if (!file_exists('docker-config'))
		{
			mkdir('docker-config');
		}

		$config = [];

		TemplateFacade::generate(
			__DIR__ . '/../../templates/docker-config/Dockerfile_Nginx.template',
			[],
			'./docker-config/Dockerfile_Nginx'
		);

		TemplateFacade::generate(
			__DIR__ . '/../../templates/docker-config/entrypoint.sh.template',
			[],
			'./docker-config/entrypoint.sh'
		);

		TemplateFacade::generate(
			__DIR__ . '/../../templates/docker-config/nginx_custom_settings.conf.template',
			[],
			'./docker-config/nginx_custom_settings.conf'
		);

		$phpIniConfig = [
			'MAILHOG' => $input['MAILHOG'] ? $this->getTemplate(
				'docker-config/partials/php_ini-mailhog.template'
			): ''
		];
		TemplateFacade::generate(
			__DIR__ . '/../../templates/docker-config/php.ini.template',
			$phpIniConfig,
			'./docker-config/php.ini'
		);

		$siteConfConfig = [
			'HOSTNAME' => $input['HOSTNAME']
		];
		TemplateFacade::generate(
			__DIR__ . '/../../templates/docker-config/site.conf.template',
			$siteConfConfig,
			'./docker-config/site.conf'
		);

		TemplateFacade::generate(
			__DIR__ . '/../../templates/docker-config/uploads.ini.template',
			[],
			'./docker-config/uploads.ini'
		);
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

	protected function updateNginx(array $input)
	{
		$this->info('Updating global NGINX config...');

		$repoDir = getenv('HOME') . '/.docker/com.helium.docker.dev-base';
		$sitesDir = "$repoDir/config/sites-enabled";

		if (file_exists("$sitesDir/{$input['CONTAINER_NAME']}.conf"))
		{
			$this->info("{$input['CONTAINER_NAME']}.conf already exists");
			$this->info('Could not update global NGINX config');
		}
		else
		{
			$config = [
				'CONTAINER_NAME' => $input['CONTAINER_NAME'],
				'CONTAINER_PORT' => $input['WEB_PORT'],
				'EXTERNAL_PORT' => 80,
				'HOSTNAME' => $input['HOSTNAME']
			];

			TemplateFacade::generate(
				__DIR__ . '/../../templates/docker-utils/site.conf.template',
				$config,
				"$sitesDir/{$input['CONTAINER_NAME']}.conf"
			);

			$this->info('Pushing changes to repository...');

			$commands = [
				"cd $repoDir",
				'git pull --quiet',
				'git add .',
				"git commit -am \"Added {$input['CONTAINER_NAME']}.conf\" --quiet",
				'git push --quiet'
			];

			shell_exec(implode(' && ', $commands));

			$this->info('Successfully updated NGINX config');

			$this->info('Restarting global containers...');

			$command = "cd $repoDir && docker-compose restart 2> /dev/null";

			exec($command, $output, $return);

			if ($return != 0)
			{
				$this->error('Failed to restart global containers');
				$this->info('Please restart your global containers');
			}
			else
			{
				$this->info('Successfully restarted global containers');
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

	public function createDatabaseCredentials(array $input)
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

	public function handle()
	{
		$input = $this->getInput();

		$this->info('Generating Docker container...');

		$this->generateDockerfile($input);
		$this->generateDockerCompose($input);
		$this->generateDockerConfig($input);

		if ($input['ENV'])
		{
			$this->updateEnv($input);
		}

		$this->updateHosts($input);

		$this->installGlobalContainers();
		$this->startupDocker();
		$this->startupGlobalContainers();

		$this->updateNginx($input);
		$this->createDatabase($input);
		$this->createDatabaseCredentials($input);

		$this->buildProjectContainers();
		$this->startupProjectContainers();

		$this->info('Done');
	}
}