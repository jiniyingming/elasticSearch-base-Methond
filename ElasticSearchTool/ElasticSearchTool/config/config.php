<?php
return [
	'elasticsearch' => [
		'index' => env('ELASTICSEARCH_PREFIX', 'fx_'),
		'prefix' => env('ELASTICSEARCH_PREFIX', 'fx_'),
		'hosts' => [
			env('ELASTICSEARCH_HOST', 'http://localhost'),
		],
		'analyzer' => env('ELASTICSEARCH_ANALYZER', 'ik_max_word'),
		'settings' => [],
		'filter' => [
			'+',
			'-',
			'&',
			'|',
			'!',
			'(',
			')',
			'{',
			'}',
			'[',
			']',
			'^',
			'\\',
			'"',
			'~',
			'*',
			'?',
			':'
		]
	]
];