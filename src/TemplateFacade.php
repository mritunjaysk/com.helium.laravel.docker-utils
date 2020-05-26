<?php

namespace Helium\DockerGenerator;

class TemplateFacade
{
	public static function generate(string $templatePath, array $args = [],
		string $outputPath = null): string
	{
		$template = file_get_contents($templatePath);

		foreach ($args as $key => $value)
		{
			$template = preg_replace("/{{{$key}}}/", $value, $template);
		}

		if ($outputPath)
		{
			self::writeFile($outputPath, $template);
		}

		return $template;
	}

	public static function writeFile(string $outputPath, string $value)
	{
		$file = fopen($outputPath, 'w');

		if (!$file)
		{
			die("Unable to create file $outputPath");
		}

		fwrite($file, $value);

		fclose($file);
	}
}