<?php

namespace App\Services\Search;

use Elasticsearch\Client, Elasticsearch\ClientBuilder, Exception;

/**
 * Class SearchService
 * @package App\Services\Search
 * ElasticSearch 基础搜索方法使用模型
 */
class SearchService
{
    /**
     * @var mixed 索引 Index
     */
    protected $index;
    /**
     * @var mixed type
     */
    protected $type;
    /**
     * @var bool 是否模糊搜索配合match
     */
    private $isFuzzy = false;
    /**
     * @var array 设置排序规则
     */
    private $sort = [];
    /**
     * @var Client ElasticSearch
     */
    protected $client;
    /**
     * @var array 设置query_string 方式搜索项
     */
    private $searchWhere = [];
    /**
     * @var array 设置 match 方式搜索项 全匹配和分词搜索
     */
    private $mathWhere = [];
    /**
     * @var array not in 设置排除项
     */
    private $isNotData = [];
    /**
     * @var int 分页
     */
    private $offset = 0;
    private $pageSize = 10;
    /**
     * @var null 设置返回字段 默认不返回
     */
    private $_source = null;
    /**
     * @var int 字段最小匹配数量
     */
    protected $minimum_should_match = 1;
    /**
     * @var array 或条件搜索
     */
    private $shouldWhere = array();
    /**
     * @var array 并条件信息集
     */
    private $isMustData = array();
    /**
     * @var int 設置對單個字段的搜索權重值
     */
    private $boost = 1;

    /**
     * SearchService constructor.
     * @param array $conf 实例化
     * @throws Exception
     */
    public function __construct(array $conf)
    {
        if (!isset($conf['index'], $conf['type'])) {
            throw new \RuntimeException('Index Or Type Not Found');
        }
        $this->index = $conf['index'];
        $this->type = $conf['type'];
        $this->client = ClientBuilder::create()->setHosts(config('scout.elasticsearch.hosts'))->build();
    }

    /**
     * @var array 聚合查询信息集
     */
    private $aggiData = [];

    /**
     * @param array $aggi
     * @return $this
     * 设置聚合
     */
    public function aggs(array $aggi): self
    {
        if (empty($aggi)) {
            return $this;
        }
        if($this->aggiData)
        {
            throw new \RuntimeException('aggs already exists');
        }
        $this->aggiData = $aggi;
        return $this;
    }

    /**
     * @param string $field
     * @param int $returnCount
     * @return $this
     * 简单分组查询
     */
    public function groupBy(string $field,$returnCount = 10): SearchService
    {
        $this->aggiData = [
            'group_by_'.$field=>[
                "group_by_{$field}_list" => array(
                    "terms" => array(
                        "field" => $field,
                        "size" => $returnCount,
                    ),
                    "aggs" => array(
                        'my_top_hits' => array(
                            "top_hits" => array(
                                "size" => 1
                            )
                        )
                    )
                )
            ]
        ];
        return $this;
    }

    /**
     * @param bool $isTrue
     * @return $this
     * 是否模糊搜索 true 完全匹配 false 分词匹配
     */
    public function isFuzzy(bool $isTrue = false): self
    {
        $this->isFuzzy = $isTrue;
        return $this;
    }

    /**
     * @param $data
     * @param string $sortType
     * @return $this
     * 设置排序条件
     */
    public function sort($data = null, string $sortType = 'desc'): self
    {
        if (empty($data)) {
            return $this;
        }
        $this->sort[] = is_array($data) ? $data : [$data => ['order' => $sortType]];
        return $this;
    }

    /**
     * 输出完整 数据JSON
     */
    public function outPutJson(): void
    {
        exit(json_encode($this->setParams(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @param $searchWord
     * @param array $queryField
     * @return $this
     * 搜索条件
     */
    public function query($searchWord, array $queryField): self
    {
        $keyword = $this->checkKeyword($searchWord);
        if (!empty($keyword)) {
            $this->searchWhere['query'] = $keyword;
            $this->searchWhere['query_field'] = $queryField;
        }
        return $this;
    }

    /**
     * @param array $notArray
     * @return $this
     * 去除条件
     */
    public function isNot(array $notArray = []): self
    {
        if (empty($notArray)) {
            return $this;
        }
        $this->isNotData = $notArray;
        return $this;
    }

    /**
     * @param int $page
     * @param int $limit
     * @return $this
     * 设置分页
     */
    public function offset(int $page = 1, int $limit = 10): self
    {
        $page = $page <= 0 ? 1 : $page;
        $this->pageSize = (int)$limit;
        $this->offset = (int)($limit * ($page - 1));
        return $this;
    }

    /**
     * @param array $mustArray
     * @return $this
     * 设置必须筛选项
     */
    public function isMust(array $mustArray = []): self
    {
        if (empty($mustArray)) {
            return $this;
        }
        $this->isMustData = !empty($this->isMustData) ? array_merge($this->isMustData, $mustArray) : $mustArray;
        return $this;
    }

    /**
     * @param array $_source
     * @return $this
     * 设置返回字段
     */
    public function _source(array $_source): self
    {
        if (empty($_source)) {
            return $this;
        }
        $this->_source = $_source;
        return $this;
    }

    /**
     * @var array 最终组装的结果集
     */
    protected $params = [];

    /**
     * @return array
     * 组装条件
     */
    public function setParams(): array
    {
        $this->params = [
            'index' => $this->index,
            'type' => $this->type,
        ];
        $this->params['body']['from'] = $this->offset;
        $this->params['body']['size'] = $this->pageSize;
        if ($this->sort) {
            $this->params['body']['sort'] = $this->sort;
        }
        if ($this->_source) {
            $this->params['body']['_source'] = $this->_source;
        }
        //搜索条件
        //query string 搜索方式
        $this->setQueryParams();
        //作为必要条件筛选
        $this->setMatchParams();
        //作为非必要筛选项 OR
        $this->setShouldParams();
        //去除条件
        $this->setNotParams();
        //必须筛选项
        $this->setMustParams();
        //聚合数组组装
        if ($this->aggiData) {
            $this->params['body']['aggs'] = $this->aggiData;
        }
        return $this->params;
    }

    /**
     * 设置QUERY 方式信息
     */
    private function setQueryParams(): void
    {
        if ($this->searchWhere) {
            $this->params['body']['query']['bool']['must'][] = [
                'query_string' => [
                    'query' => $this->searchWhere['query'],
                    'fields' => $this->searchWhere['query_field']
                ]
            ];
        }
    }

    /**
     * @var string[]
     * 区间标识符转换
     */
    private $intervalMapping = [
        '>' => 'gt',
        '>=' => 'gte',
        '<' => 'lt',
        '<=' => 'lte',
    ];

    /**
     * @param $filterField
     * @param $filterData
     * @return array
     * 设置区间筛选量
     */
    private function setInterval($filterField, $filterData): array
    {
        if (is_string($filterData) && empty($filterData)) {
            return [];
        }
        $type = 'term';
        if (is_array($filterData)) {
            $type = 'terms';
            $filter = [$type => [$filterField => $filterData]];
            if (count($filterData) === 2 && is_string($filterData[0]) && isset($this->intervalMapping[$filterData[0]])) {
                $filter = ["range" => [$filterField => [$this->intervalMapping[$filterData[0]] => $filterData[1]]]];
            }
        } else {
            $filter = [$type => [$filterField => $filterData]];
        }
        return $filter;
    }

    /**
     * 设置并条件信息集
     */
    private function setMustParams(): void
    {
        if ($this->isMustData) {
            $filter = [];
            foreach ($this->isMustData as $field => $word) {
                $data = $this->setInterval($field, $word);
                if (empty($data)) {
                    continue;
                }
                $filter[] = $data;
            }
            if ($filter) {
                $this->params['body']['query']['bool']['must'][]['bool']['filter'] = $filter;
            }
        }
    }

    /**
     * 设置Not 信息集
     */
    private function setNotParams(): void
    {
        if ($this->isNotData) {
            $notData = [];
            foreach ($this->isNotData as $field => $word) {
                $data = $this->setInterval($field, $word);
                if (empty($data)) {
                    continue;
                }
                $notData[] = $data;
            }
            if ($notData) {
                $this->params['body']['query']['bool']['must_not'][]['bool']['filter'] = $notData;
            }
        }
    }

    /**
     * 设置搜索必选项
     */
    private function setMatchParams(): void
    {
        if ($this->mathWhere) {
            //2.match_phrase  match搜索方式
            $matchType = 'match_phrase';//全匹配
            if ($this->isFuzzy === true) {
                $matchType = 'match';//分词查询
            }
            $should = [];
            foreach ($this->mathWhere as $field => $word) {
                //boost 对于单个字段的查询结果设置权重值 默认唯一
                $word = $this->setBoostVal($word);
                if (is_string($word) && !empty($word)) {
                    $should[] = [
                        $matchType => [
                            $field => ['query' => $word, 'boost' => $this->boost]
                        ]
                    ];
                }
            }
            if ($should) {
                $this->params['body']['query']['bool']['must'] = $should;
                //设置最少匹配数量 暂时弃用
//                if ($this->minimum_should_match > 0) {
//                    $this->params['body']['query']['bool']['minimum_should_match'] = $this->minimum_should_match;
//                }
            }
        }
    }

    /**
     * @param $searchValue
     * @return mixed|string
     * 判断权重值
     */
    private function setBoostVal($searchValue)
    {
        if (is_array($searchValue)) {
            if (count($searchValue) === 2) {
                [$searchValue, $this->boost] = $searchValue;
            }else{
                $key = array_key_first($searchValue);
                if(is_string($key))
                {
                    $searchValue = $this->setBoostVal($searchValue[$key]);
                }
            }
        }
        return $this->checkKeyword($searchValue);
    }

    /**
     * 设置 OR 筛选项
     * search 搜索词项 设计拆词查询 词权重处理
     * filter 不涉及分词拆词 单纯筛选
     */
    private function setShouldParams(): void
    {

        if (!empty($this->shouldWhere)) {
            $search = [];
            $filter = [];
            foreach ($this->shouldWhere as $shouldType => $shouldParams) {
                switch ($shouldType) {
                    case 'search':
                        foreach ($shouldParams as $searchType => $searchValueSet) {
                            if (in_array($searchType, ['match', 'match_phrase']) && is_array($searchValueSet)) {
                                foreach ($searchValueSet as $searchKey => $searchValue) {
                                    $searchValue = $this->setBoostVal($searchValue);
                                    if (!empty($searchValue)) {
                                        $search[] = [
                                            $searchType => [
                                                $searchKey => [
                                                    'query' => $searchValue,
                                                    'boost' => $this->boost
                                                ]
                                            ]
                                        ];
                                    }
                                }
                            }
                        }
                        break;
                    case 'filter':
                        foreach ($shouldParams as $filterKey => $filterValue) {
                            if (is_array($filterValue)) {
                                foreach ($filterValue as $field => $item) {
                                    $data = $this->setInterval($field, $item);
                                    if (empty($data)) {
                                        continue;
                                    }
                                    $filter[] = $data;
                                }
                            } else {
                                $data = $this->setInterval($filterKey, $filterValue);
                                if (empty($data)) {
                                    continue;
                                }
                                $filter[] = $data;
                            }
                        }
                        break;
                }
            }
            if (!empty($search)) {
                $this->params['body']['query']['bool']['should'] = $search;
                $this->params['body']['query']['bool']['should'][]['bool']['filter'] = $filter;
            }
        }
    }

    /**
     * @param array $shouldWhere
     * @return $this
     * 可选条件 OR
     * eg:
     * $shouldWhere = [
     *      'search'=>[
     *                  'match'=>[
     *                      'search_key'=>'search_word'||[search_word,boost]...
     *                      ],
     *                  'match_phrase'=>[
     *                      'search_key'=>'search_word'||[search_word,boost]...
     *              ],
     *      'filter'=>['filter_key'=>'(int||string||array)filter_value'....]
     * ]
     */
    public function shouldWhere(array $shouldWhere = []): self
    {
        if (empty($shouldWhere)) {
            return $this;
        }
        if (!isset($shouldWhere['search']) && !isset($shouldWhere['filter'])) {
            throw new \RuntimeException('Not found search or filter');
        }
        $this->shouldWhere = $shouldWhere;
        return $this;
    }

    /**
     * @param array $keywordArray
     * @return $this
     * 分词搜索  必须条件 and
     */
    public function match(array $keywordArray = []): self
    {
        if (empty($keywordArray)) {
            return $this;
        }
        $this->mathWhere = $this->mathWhere ? array_merge($this->mathWhere, $keywordArray) : $keywordArray;
        return $this;
    }

    /**
     * @param $number
     * @return $this
     * 设置搜索匹配最小数量
     */
    public function setMinimumMatch($number = 1): self
    {
        $this->minimum_should_match = (int)$number;
        return $this;
    }

    /**
     * @param $word
     * @return mixed|string
     * 过滤特殊字符
     */
    protected function checkKeyword($word)
    {
        return $word ? $this->replaceSpecialChar($word) : $word;
    }

    /**
     * @return array
     * 返回 Es 信息集
     */
    public function getSearchResult(): array
    {
        $this->setParams();
        return $this->client->search($this->params);
    }

    /**
     * @return array
     * 获取搜索列表
     * return [
     * 'list' => array,
     * 'total' => int
     * ];
     */
    public function getSearchList(): array
    {
        $result = $this->getSearchResult();
        $returnData = [
            'list' => array_pluck($result['hits']['hits'], '_source'),
            'total' => (int)$result['hits']['total'],
            'groupList' => []
        ];
        //---处理分组数据
        if (isset($result['aggregations']) && $bucketSet = $result['aggregations']) {

            $bucketName = array_key_first($bucketSet);
            $bucketChildName = array_key_first($this->aggiData[$bucketName]['aggs']);
            $bucketList = array_pluck($result['aggregations'][$bucketName]['buckets'], $bucketChildName);
            $data = [];
            $count = 1;
            foreach ($bucketList as $item) {
                $key = 0;
                $count = count($item['hits']['hits']);
                if ($count === 1) {
                    $returnData['groupList'][] = array_merge(['group_num' => $item['hits']['total']], $item['hits']['hits'][$key]['_source']);
                    continue;
                }
                while ($key < $count) {
                    $data[] = array_merge(['group_num' => $item['hits']['total']], $item['hits']['hits'][$key]['_source']);
                    ++$key;
                }
            }
            if (empty($returnData['groupList'])) {
                $returnData['groupList'] = array_chunk($data, $count);
            }
        }
        return $returnData;
    }

    /**
     * @param string $content
     * @return string
     * 过滤特殊字符
     */
    protected function replaceSpecialChar(string $content): string
    {
        $replace = array('◆','♂','）','=','+','$','￥bai','-','、','、','：',';','！','!','/');
        return str_replace($replace, '', $content);
    }

    /**
     * @param      $data
     * @param null $_id
     * @return object
     * 更新方法
     */
    public function update($data, $_id = NULL)
    {
        $parameters = [
            "id" => $_id,
            "body" => ['doc' => $data],
        ];

        if ($index = $this->index) {
            $parameters["index"] = $index;
        }

        if ($type = $this->type) {
            $parameters["type"] = $type;
        }

        return (object)$this->client->update($parameters);
    }

    /**
     * @param $method
     * @param $arguments
     * @return mixed
     * 自动调用 ElasticSearch 配置方法
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this->client, $method)) {
            !isset($arguments['index']) && $arguments['index'] = $this->index;
            !isset($arguments['type']) && $arguments['type'] = $this->type;
            return call_user_func_array(array($this->client, $method), $arguments);
        }
        return [];
    }

    /**
     * @param int $id
     * @return array|mixed
     * 查询单个id数据
     */
    public function getOneByEs($id = 0)
    {
        if ($id < 1) {
            return [];
        }
        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'id' => (int)$id
        ];
        if (!empty($this->_source)) {
            $params['_source'] = $this->_source;
        }
        try {
            $response = $this->client->get($params);
        } catch (\exception $e) {
            return [];
        }
        $response['_source']['id'] = $response['_id'];
        return $response['_source'];
    }

}