<?php

namespace App\Libs\ElasticSearchTool\helper;

use RuntimeException;

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

	/**
	 * @param $array
	 * @param $key
	 * @return array
	 * @internal
	 */
	public static function array_pluck($array, $key): array
	{
		return array_map(static function ($v) use ($key) {
			return is_object($v) ? $v->$key : $v[$key];
		}, $array);
	}

	/**
	 * @param string $content
	 * @return string
	 * 过滤特殊字符
	 */
	public static function replaceSpecialChar(string $content): string
	{
		return $content;
		$replace = array('◆', '♂', '）', '=', '+', '$', '￥bai', '-', '、', '、', '：', ';', '！', '!', '/');
		return str_replace($replace, '', $content);
	}

	public static function array_depth($array): int
	{
		if (!is_array($array)) {
			return 0;
		}
		$max_depth = 1;
		foreach ($array as $value) {
			if (is_array($value)) {
				$depth = self::array_depth($value) + 1;
				if ($depth > $max_depth) {
					$max_depth = $depth;
				}
			}
		}
		return $max_depth;
	}

	/**
	 * @param array $math_set
	 * @param float $relativity_per 相关度百分比
	 * @return string 设置搜索排序公式
	 * 设置搜索排序公式
	 */
	public static function getSortCalculationValue(array $math_set, float $relativity_per): string
	{
		$sign = ['+', '-', '*', '/'];
		$sortScript = '_score * 0.01 * ' . $relativity_per;
		$checkSign = static function (&$scriptString, $sign) {
			if (!in_array($scriptString{-1}, $sign, true)) {
				$scriptString .= ' + ';
			}
			return $scriptString;
		};
		foreach ($math_set as $number_set) {
			$sortScript = $checkSign($sortScript, $sign);
			//--指定数值
			if (is_int($number_set)) {
				$sortScript .= $number_set;
			}
			//--指定数据字段
			if (is_string($number_set)) {
				$sortScript .= sprintf('doc["%s"].value', $number_set);
			}
			if (is_array($number_set)) {

			}
		}

		return $sortScript;
	}
}