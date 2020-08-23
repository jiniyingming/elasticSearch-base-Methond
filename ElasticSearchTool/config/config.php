<?php
return [
	'elasticsearch' => [
		'index' => 'fx_test',
		'prefix' => 'fx_',
		'hosts' => [
			'http://localhost',
		],
		'analyzer' => 'ik_max_word',
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
	]];