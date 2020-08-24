<?php

namespace ElasticSearchTool\test;


class Test
{
	public function testIndex()
	{
		$where = [];
		$model = \App\Libs\ElasticSearchTool\ElasticTool::operationSearch()
			->setIndex('test_index')
			->isMust(...)
			->match(...)
			->mustShould(...$where)
			->getSearchList();
		print_r($model);
		die;
	}
}
