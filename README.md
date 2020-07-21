# ElasticSearch-base-Methond
对于elasticsearch 进行应用模块进行二次封装 增强应用性和可读性 包括 CURD
#composer.json
Add elasticsearch/elasticsearch
```json
{
        "require": {
            "elasticsearch/elasticsearch": "^6.7"
        }
    }
```
#ElasticSearch 配置
config('scout.elasticsearch.hosts')
#总结
SearchService 为基础搜索链式方法 
为了方便根据后期业务方向的扩展,对于每个模块都进行了小粒度拆分,可根据具体情况进行扩展处理
#使用介绍
搜索方向主要 涵盖了 query,match,match_phrase 对于多字段的搜索 也可以进行字段的权重处理;
step1  实例化Service 主要是为了方便多个索引库操作
```php
$search = new SearchService([
                'index' =>'' , 
                'type' => ''
               ]);
```
step2 筛选类方法 主要场景有  并 非 或 三种条件 筛选类型大概有 int string array 三种类型
```php
    /*
     * ---或 条件
     * eg:
     * $whereArray = [
     *      'search'=>[
     *                  'match'=>[
     *                      'search_key'=>'search_word'||[search_word,boost], ...
     *                      ],
     *                  'match_phrase'=>[
     *                      'search_key'=>'search_word'||[search_word,boost],...
     *              ],
     *      'filter'=>['filter_key'=>'(int||string||array)filter_value',....]
     * ]
     * shouldWhere 方法丰富了对搜索的使用
     * 返回类型为 Object
     */
    $search->shouldWhere($whereArray);
    /**
     * ---并 条件
     * eg:
     * $whereArray = [
                        'key'=>'value',
                        ...];
     * 返回类型为 Object
     */
    $search->isMust($whereArray);
    /**
     * ---非 条件
     * eg:
     * $whereArray = [
                    'key'=>'value',
                    ...];
     * 返回类型为 Object
     */
    $search->isNot($whereArray);
    /**
     * 以上三种筛选方式 对于区间范围筛选 传值类型
     * ['字段'=>['范围符号 >= <= < >','搜索值']]
     */
```
step3 分页处理 和 排序处理
```php
    /**
     * ---分页设置
     * 返回类型为 Object
     */
    $search->offset($page = 1,$limit = 10);
    /**
     * ---排序设置
     * $data 值 为 string 或 array 
     * 当 $data为string 配合$sortType作为传统排序使用
     * 当 $data 为 array $sortType则被弃用 此类型作为配合后期健全搜索排序算法使用
     * 返回类型为 Object
     */
    $search->sort($data,$sortType = 'desc');
```
step4 调试 输出组装后的json数据
```php
     /**
      * ---调试输出组装后的json
      * 直接输出 json string
      */
     $search->outPutJson();
```
step5 搜索方式
```php
    /**
     * --- 设置搜索
     * query 方式 分词搜索 
     * 传值 $searchWord 搜索词 string ,$queryField 被涉及字段 array
     * 返回类型为 Object
     */
    $search->query($searchWord, $queryField);
     /**
      * --- 设置搜索
      * match,match_phrase 方式 分词搜索  通过 isFuzzy() 区分搜索粒度 默认match方式搜索
      * 传值 两种情况
        1.此情况不作分被搜索字段的权重区分 
        $searchSet = [
            '被搜索字段'=>'搜索内容　类型为是string',
             ...
          ]
        2.对特定字段进行权重值设定
        $searchSet = [
                    '被搜索字段'=>['搜索内容　类型为是string','设置单个字段的权重值boost 类型为 int'],
                    ...
                  ]
        $isFuzzy 是否为模糊搜索 细粒度分词处理  true 语句匹配 false 分词匹配
      * 返回类型为 Object
      */
      $search->isFuzzy($isFuzzy)->match($searchSet);
```
step6 获取结果集
```php
    /**
     * 获取搜索返回结果集有两个方法 
     * 1.getSearchResult 返回 array ElasticSearch 原始结果集
     * 2.getSearchList 返回 处理过的结果集 
     * $isJsonToArray bool true 则把结果集中的json格式转为数组 false 则不作处理
     *  return [
     * 'list' => array,
     * 'total' => int
     * ];
     * 返回类型 为 Array
     */
     $search->getSearchResult();
     $search->getSearchList($isJsonToArray);
```
step7 增加回调方法 可调用ElasticSearch扩展中自带方法
```php
    $params = ['query'=>['match'=>['title'=>'你好']]];
    $search->count($params);
```
step8 扩展方法
   对于搜索词的特殊过滤 可通过 replaceSpecialChar 方法进行更改
   对于数据的更新可以使用 update 方法

