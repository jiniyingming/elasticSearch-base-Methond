<?php


namespace App\Libs\ElasticSearchTool\DML;


use Elasticsearch\Client;
use http\Exception\RuntimeException;

class ElasticDescFactory
{
	private static $client;


	public function __construct(Client $elasticTool)
	{
		self::$client = $elasticTool;
	}

	/**
	 * @param array $data
	 * @param string $index
	 * @return array|bool
	 * 批量添加
	 */
	public function addAll(array $data, string $index)
	{
		$params = [];
		foreach ($data as $datum) {
			if (!is_array($datum)) {
				throw new \RuntimeException('batch parameter error');
			}
			$params['body'][] = [
				'index' => [
					'_index' => $index
				],
				$datum
			];
		}
		if (empty($params)) {
			throw new \RuntimeException('insert data empty');
		}
		$response = self::$client->bulk($params);
		if (isset($response['errors']) && is_array($response['items']) && false === $response['errors']) {
			$ids = [];
			foreach ($response['items'] as $val) {
				$ids[] = $val['index']['_id'] ?? false;
			}
			return $ids;
		}
		return false;
	}

	/**
	 * @param      $data
	 * @param null $_id
	 * @param string $index
	 * @return array
	 * 更新方法
	 */
	public function update($data, $_id, string $index): array
	{
		$parameters = [
			"id" => $_id,
			"body" => ['doc' => $data],
		];

		if ($index) {
			$parameters["index"] = $index;
		}


		return self::$client->update($parameters);
	}
}