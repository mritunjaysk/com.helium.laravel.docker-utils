<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;
use Helium\DockerUtils\Facades\TemplateFacade;
use Illuminate\Support\Str;

class DockerGenerateCommand extends DockerCommand
{
	protected const MYSQL = 'MySQL';
	protected const POSTGRESQL = 'PostgreSQL';

	protected const DB_CHOICES = [
		self::MYSQL,
		self::POSTGRESQL
	];

	protected $signature = 'docker:generate';

	protected $description = 'Generates docker containers for project.';

	protected function getTemplate(string $path, array $args = []): string
	{
		return TemplateFacade::generate(
			__DIR__ . "/../templates/$path",
			$args
		);
	}

	public function handle()
	{
		$this->info('Generating Docker Container');

		$this->installDockerDevBase();
		$this->startupDocker();
		$this->startupSharedContainers();

		$dockerfile = [];
		$dockerCompose = [];

		/**
		 * STEP 1: Use PHP
		 */
		$this->line('Using PHP 7.3');
		//Do nothing

		/**
		 * STEP 2: Set container basics
		 */
		$dockerCompose['CONTAINER_NAME'] = $this->ask('Container name');
		$siteConf['HOSTNAME'] = $this->ask('Hostname');

		/**
		 * STEP 3: Set Database
		 */
		$db = $this->choice('Database:', self::DB_CHOICES, 0);

		$dockerCompose['DB_NAME'] = $this->anticipate('Database name',
			[$dockerCompose['CONTAINER_NAME']]);

		$dockerCompose['DB_USER'] = $this->anticipate('Database user',
			[$dockerCompose['CONTAINER_NAME']]);

		$dockerCompose['DB_PASSWORD'] = $this->anticipate('Database password',
			[$dockerCompose['CONTAINER_NAME']]);

		switch ($db)
		{
			case self::MYSQL:
				$dockerfile['DATABASE'] = $this->getTemplate(
					'Dockerfile/partials/mysql.template'
				);
				$dockerCompose['DATABASE'] = $this->getTemplate(
					'docker-compose/partials/mysql.template',
					$dockerCompose
				);
				$dockerCompose['DB_SERVICE'] = 'mysql';
				break;
			case self::POSTGRESQL:
				$dockerfile['DATABASE'] = $this->getTemplate(
					'Dockerfile/partials/postgres.template'
				);
				$dockerCompose['DATABASE'] = $this->getTemplate(
					'docker-compose/partials/postgres.template',
					$dockerCompose
				);
				$dockerCompose['DB_SERVICE'] = 'postgres';
				break;
			default:
				die('Invalid database. Exiting.');
		}

		/**
		 * STEP 4: MailHog
		 */
		$mailhog = $this->confirm('Are you using MailHog?', true);

		if ($mailhog)
		{
			$dockerfile['MAILHOG'] = $this->getTemplate(
				'Dockerfile/partials/mailhog.template'
			);
			$dockerCompose['MAILHOG'] = $this->getTemplate(
				'docker-compose/partials/mailhog.template'
			);
		}
		else
		{
			$dockerfile['MAILHOG'] = '';
			$dockerCompose['MAILHOG'] = '';
		}

		TemplateFacade::generate(
			__DIR__ . '/../templates/Dockerfile/Dockerfile.template',
			$dockerfile,
			'./Dockerfile'
		);

		TemplateFacade::generate(
			__DIR__ . '/../templates/docker-compose/docker-compose.yml.template',
			$dockerCompose,
			'./docker-compose.yml'
		);

		if (!file_exists('docker-config'))
		{
			mkdir('docker-config');
		}

		TemplateFacade::generate(
			__DIR__ . '/../templates/docker-config/Dockerfile_Nginx.template',
			[],
			'./docker-config/Dockerfile_Nginx'
		);

		TemplateFacade::generate(
			__DIR__ . '/../templates/docker-config/entrypoint.sh.template',
			[],
			'./docker-config/entrypoint.sh'
		);

		TemplateFacade::generate(
			__DIR__ . '/../templates/docker-config/nginx_custom_settings.conf.template',
			[],
			'./docker-config/nginx_custom_settings.conf'
		);

		TemplateFacade::generate(
			__DIR__ . '/../templates/docker-config/php.ini.template',
			[],
			'./docker-config/php.ini'
		);

		TemplateFacade::generate(
			__DIR__ . '/../templates/docker-config/site.conf.template',
			$siteConf,
			'./docker-config/site.conf'
		);

		TemplateFacade::generate(
			__DIR__ . '/../templates/docker-config/uploads.ini.template',
			[],
			'./docker-config/uploads.ini'
		);

		if ($this->confirm('Would you like to update .env?', true))
		{
			$envVars = [
				'DB_CONNECTION' => $dockerCompose['DB_SERVICE'],
				'DB_HOST' => $dockerCompose['DB_SERVICE'],
				'DB_DATABASE' => $dockerCompose['DB_NAME'],
				'DB_USERNAME' => $dockerCompose['DB_USER'],
				'DB_PASSWORD' => $dockerCompose['DB_PASSWORD'],
				'APP_URL' => 'http://' . $siteConf['HOSTNAME'],
				'APP_NAME' => $dockerCompose['CONTAINER_NAME']
			];

			if ($mailhog)
			{
				$envVars = array_merge($envVars, [
					'MAIL_MAILER' => 'smtp',
					'MAIL_HOST' => 'mailhog',
					'MAIL_PORT' => 1025,
					'MAIL_USERNAME' => 'null',
					'MAIL_PASSWORD' => 'null',
					'MAIL_ENCRYPTION' => 'null',
					'MAIL_FROM_ADDRESS' => $dockerCompose['CONTAINER_NAME'] . '@heliumservices.com',
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

		$this->line('Remember to add the following line to /etc/hosts');
		$this->line('127.0.0.1 ' . $siteConf['HOSTNAME']);

		$this->line('To install, run `docker-compose build`, then `docker-compse up -d`');
	}
}