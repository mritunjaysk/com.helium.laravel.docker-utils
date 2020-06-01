<?php

namespace Helium\DockerUtils\Commands;

use Helium\DockerUtils\Commands\Base\DockerCommand;

class DockerShellCommand extends DockerCommand
{
	protected $signature = 'docker:shell';

	protected $description = 'Enters into the project docker shell.';
}