<?php

namespace Helium\DockerGenerator;

use Illuminate\Support\ServiceProvider;

class DockerGeneratorServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if ($this->app->runningInConsole()) {
			$this->commands([
				DockerGenerateCommand::class
			]);
		}
	}
}