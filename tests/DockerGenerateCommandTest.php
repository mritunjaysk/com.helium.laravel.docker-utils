<?php

namespace Helium\DockerGenerator\Tests;

use Helium\DockerGenerator\TemplateFacade;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

class DockerGenerateCommandTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		if (!file_exists(__DIR__ . '/tmp')) {
			mkdir(__DIR__ . '/tmp');
		}
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		if (file_exists(__DIR__ . '/tmp')) {
			array_map('unlink', glob(__DIR__ . '/tmp/*.*'));
			rmdir(__DIR__ . '/tmp');
		}
	}

	protected function getApplicationProviders($app)
	{
		return [
			'Helium\DockerGenerator\DockerGeneratorServiceProvider'
		];
	}

	public function testHandle()
	{
		Artisan::call('docker-generate');
	}
}