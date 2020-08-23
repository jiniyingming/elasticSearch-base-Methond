<?php
namespace ElasticSearchTool\helper;
class HelperTool
{
	public static function config(string $key): string
	{
		return __FILE__.$key;
	}

}