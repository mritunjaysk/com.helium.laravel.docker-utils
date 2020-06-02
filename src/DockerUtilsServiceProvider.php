<?php

namespace Helium\DockerUtils;

use Helium\DockerUtils\Commands\DockerStopCommand;
use Helium\DockerUtils\Commands\DockerUpdateCommand;
use Illuminate\Support\ServiceProvider;
use Helium\DockerUtils\Commands\DockerGenerateCommand;
use Helium\DockerUtils\Commands\DockerShellCommand;
use Helium\DockerUtils\Commands\DockerStartCommand;

class DockerUtilsServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if ($this->app->runningInConsole()) {
			$this->commands([
				DockerGenerateCommand::class,
				DockerShellCommand::class,
				DockerStartCommand::class,
				DockerStopCommand::class,
				DockerUpdateCommand::class
			]);
		}
	}
}