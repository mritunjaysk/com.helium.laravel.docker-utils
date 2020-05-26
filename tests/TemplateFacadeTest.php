<?php

namespace Helium\DockerGenerator\Tests;

use Helium\DockerGenerator\TemplateFacade;
use Orchestra\Testbench\TestCase;

class TemplateFacadeTest extends TestCase
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

	public function testGenerate()
	{
		$this->assertTrue(true);

		$output = TemplateFacade::generate(
			__DIR__ . '/templates/Test.template',
			[
				'VALUE' => 'abc123'
			],
			__DIR__ . '/tmp/Test.txt'
		);

		$this->assertIsString($output);
		$this->assertEquals(
			$output,
			'This is a test template with value abc123'
		);

		$this->assertFileExists(__DIR__ . '/tmp/Test.txt');
		$this->assertStringEqualsFile(
			__DIR__ . '/tmp/Test.txt',
			'This is a test template with value abc123'
		);
	}
}