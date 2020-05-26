<?php

namespace Helium\DockerGenerator;

use Illuminate\Console\Command;

class DockerGenerateCommand extends Command
{
	protected const MYSQL = 'MySQL';
	protected const POSTGRESQL = 'PostgreSQL';

	protected const DB_CHOICES = [
		self::MYSQL,
		self::POSTGRESQL
	];

	protected $signature = 'docker-generate';
	protected $description = 'Generates docker container for project.';

	protected function getTemplate(string $path, array $args = []): string
	{
		return TemplateFacade::generate(
			__DIR__ . "/../templates/$path",
			$args
		);
	}

	public function handle()
	{
		$this->line('Generating Docker Container');

		$dockerfile = [];
		$dockerCompose = [];

		/**
		 * STEP 1: Use PHP
		 */
		$this->line('Using PHP 7.3');
		//Do nothing

		/**
		 * STEP 2: Set container name;
		 */
		$dockerCompose['CONTAINER_NAME'] = $this->ask('Container name:');

		/**
		 * STEP 3: Set Dependencies
		 */
		$dockerfile['DEPENDENCIES'] = $this->getTemplate(
			'Dockerfile/partials/dependencies.template'
		);

		/**
		 * STEP 4: Set Database
		 */
		$db = $this->choice('Database:', self::DB_CHOICES, 0);

		$dockerCompose['DB_NAME'] = $this->anticipate('Database name:',
			$dockerfile['CONTAINER_NAME']);

		$dockerCompose['DB_USER'] = $this->anticipate('Database user:',
			$dockerfile['CONTAINER_NAME']);

		$dockerCompose['DB_PASSWORD'] = $this->anticipate('Database password:',
			$dockerfile['CONTAINER_NAME']);

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
		 * STEP 5: Set Extensions
		 */
		$dockerfile['EXTENSIONS'] = $this->getTemplate(
			'Dockerfile/partials/extensions.template'
		);

		/**
		 * STEP 6: MailHog
		 */
		if ($this->confirm('Are you using MailHog?'))
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
			'.'
		);
		TemplateFacade::generate(
			__DIR__ . '/../templates/docker-compose/docker-compose.yml.template',
			$dockerCompose,
			'.'
		);
	}
}