<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;
use Helium\DockerUtils\Facades\TemplateFacade;
use Illuminate\Support\Str;

class DockerGenerateCommand extends DockerCommand
{
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

	protected function saveConfig(array $input)
	{
		$config = [
			'CONTAINER_NAME' => $input['CONTAINER_NAME'],
			'HOSTNAME' => $input['HOSTNAME'],
			'DB' => $input['DB'],
			'DB_DATABASE' => $input['DB_DATABASE'],
			'DB_USER' => $input['DB_USER'],
			'DB_PASSWORD' => $input['DB_PASSWORD']
		];

		$json = json_encode($config, JSON_PRETTY_PRINT);

		TemplateFacade::writeFile('./docker_utils_config.json', $json);
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

	public function handle()
	{
		$input = $this->getInput();

		$this->saveConfig($input);

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