<?php

namespace Helium\DockerUtils;

use Illuminate\Support\ServiceProvider;
use Helium\DockerUtils\Commands\DockerGenerateCommand;
use Helium\DockerUtils\Commands\DockerExecCommand;
use Helium\DockerUtils\Commands\DockerShellCommand;
use Helium\DockerUtils\Commands\DockerStartCommand;

class DockerUtilsServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if ($this->app->runningInConsole()) {
			$this->commands([
				DockerGenerateCommand::class,
				DockerExecCommand::class,
				DockerShellCommand::class,
				DockerStartCommand::class
			]);
		}
	}
}