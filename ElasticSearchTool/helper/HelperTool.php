<?php

namespace App\Libs\ElasticSearchTool\helper;
/**
 * Class HelperTool
 * @package App\Libs\ElasticSearchTool\helper
 * 工具类
 */
class HelperTool
{
	/**
	 * @param string $key
	 * @return mixed|null
	 * 获取配置信息
	 */
	public static function config(string $key)
	{
		$config = require __DIR__ . '/../config/config.php';
		$key_set = explode('.', $key);
		return self::getKeyVal($config, $key_set);
	}

	/**
	 * @param $data
	 * @param $key
	 * @return mixed|null
	 */
	private static function getKeyVal($data, $key)
	{
		$val = null;
		foreach ($key as $item) {
			if (isset($data[$item])) {
				$val = $data[$item];
			}
			if (isset($val[$item])) {
				$val = $val[$item];
			}
		}
		return $val;
	}

}