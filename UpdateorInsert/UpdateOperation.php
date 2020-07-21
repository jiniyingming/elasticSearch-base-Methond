<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/7/18 0018
 * Time: 13:42
 */

namespace app\models;

use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

/**
 * Class UpdateOperation
 * 更新操作
 * es 更新操作 应用于多语言版本操作的
 * 实例化 Model
 * $model = new UpdateOperation(int $type = Element::TYPE, string $lang = 'en');
 * 批量更新es操作 全量更新 更新区间自行指定
 * $model->goUpdateEsAll(int $start = 1, int $limit = 1000,int $end_id=-1);
 * 批量更新条件更新es操作 全量更新 条件自行指定
 * $model->goUpdateEsCondition(array $where, int $limit = 1000,);
 * 指定更新 更新单个 或多个id 的es  id = int or id = array
 * $model->goUpdateSingle($id);
 */
class UpdateOperation
{
    //--es 地址
    private $esHost;
    //--品类类型 细分 元素 模板...
    private $type;
    //--品类类型 元素 背景
    private $mainType;
    //--品类分表集合
    private $tableSet;
    //--品类 语言 索引
    private $index;
    //--表后缀
    private $lgPre;
    //--语言版本
    private $lang;

    /**
     * UpdateOperation constructor.
     * @param int $type
     * @param string $lang
     * @param array $config
     * 独立版本
     */
    public function __construct(int $type, string $lang = 'en', $config = [])
    {
        //--小语言集合索引
        $indexArr = $this->getOtherLangIndex();
        $this->lgPre = '';
        $this->index = $indexArr[$lang][$type];
        if (isset($config['index'])) {
            $this->index = $config['index'];
        }

        //---独立小语言版本 表后缀设置
        if (isset($indexArr[$lang])) {
            $this->lgPre = in_array($lang, ['cn', 'zh', 'cht']) ? '_zh' : '_' . $lang;
        }

        $this->esHost = 'es地址' . $this->index . '/info/_bulk';


        //--设置主品类下的分表集合
        $queryDb = new QueryDb($this->mainType, $lang);
        $this->tableSet = '对应mysql 表';
        $this->type = $type;
        $this->lang = empty($lang) || $lang == 'en' ? 'en' : (in_array($lang, ['cn', 'zh', 'cht']) ? 'cht' : $lang);

    }


    /**
     * @return array
     * 小语言索引集合
     */
    public function getOtherLangIndex()
    {
        //--设置语言索引集合
        $indexArr = [
            'th' => [
                'type' => 'index',
            ]
        ];
        return $indexArr;
    }

    /**
     * @param int $start
     * @param int $limit
     * @param int $end_id
     * 批量更新es 更新所有
     */
    public function goUpdateEsAll(int $start = 1, int $limit = 1000, int $end_id = -1)
    {
        $end = $start + $limit;

        if ($end_id > 0) {
            $last = $end_id;
        } else {
            $last = (new Query())->from($this->tableSet['info'])->orderBy('id desc')->limit(1)->one();
            $last = $last['id'];
        }
        Console::startProgress($start, $last,'update '.$this->index);
        while ($start < $last) {
            //$e1 = microtime(1);
            $model = new Query();
            $res = $model->from($this->tableSet['info'])->where(['>=', 'id', $start])->andWhere(['<', 'id', $end])->all();
            if (empty($res)) {
                $start = $end;
                $end = $start + $limit;
                continue;
            }
            $this->setEsResult($res);

            //$e2 = microtime(true);
//            echo $log = ' ' . $start . '-->' . $end . ' spends ' . ($e2 - $e1) . PHP_EOL;
            Console::updateProgress($end, $last);
            $start = $end;
            $end = $start + $limit;
        }
        Console::endProgress("done update index ".$this->index . PHP_EOL);
    }
	
	
	/**
     * @param int $where
     * @param int $limit
     * 条件批量更新es 
     */
    public function goUpdateEsCondition(array $where = [], int $limit = 1000)
    {
    	$model = new Query();
		if(count($where)==0 || !is_array($where)){
			throw Exception('请检查更新条件');
		}
		$currentId=$page=0;
        while (true) {
            $e1 = microtime(1);
            $res = $model->from($this->tableSet['info'])->where(['>', 'id', $currentId])->andWhere($where)->orderBy('id asc')->limit($limit)->all();
            if (count($res)==0) {
				break;
            }
            $this->setEsResult($res);

            $e2 = microtime(true);
			$page++;
            echo $log = 'page > ' . $page . ' >spends ' . round($e2 - $e1,2) .'s'. PHP_EOL;
			$currentId=$res[count($res)-1]['id'];
        }
		echo 'OVER'.PHP_EOL;
    }

    /**
     * @param $filename
     * @param $log
     * @param $type
     *  日志记录
     */
    public function makeDir($filename, $log, $type)
    {
        $log_url = __DIR__ . '/../runtime/' . $type . '/' . date('Y-m-d') . '/';
        if (!is_dir($log_url)) {
            $oldumask = umask(0);
            mkdir($log_url, 0777, true);
            umask($oldumask);
        } else {
            file_put_contents($log_url . $filename, $log . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * 更新单个内容 es
     * @param $id int|array
     * @return bool|null
     */
    public function goUpdateSingle($id)
    {
        usleep(300000);
        $result = getDbQuery($this->tableSet['info'], false, ['id' => $id]);
        if (empty($result)) {
            return null;
        }

        return $this->setEsResult($result);
    }

    /**
     * @param array $result
     * 更新es 组装子信息
     */
    public function setEsResult(array $result)
    {
        $map = Yii::$app->params['PAY_DATA_LANG'];
        $otherIndex = array_keys($this->getOtherLangIndex());
        $otherLanguage = false;
        //--独立版本只更新当前语言字段和英语
        if (in_array($this->lang, $otherIndex))//kor,th,zh
        {
            $map = ['en', $this->lang];
            $this->lang == 'cht' && $map[] = 'cn';
            $otherLanguage = true;
        }
        $model = new Query();
        $ids = array_column($result, 'id');
        $data_lg = [];
        foreach ($map as $item) {
            $queryDb = new QueryDb($this->mainType, $item);
            $en = $queryDb->getWords($ids);
            if (!empty($en)) {
                $data_lg[$item] = ArrayHelper::index($en, 'id');
            }
        }
        $info_data = $model->from($this->tableSet['data'] . $this->lgPre)->where(['id' => $ids])->all();
        $info_data_map = [];
        if (!empty($info_data)) {
            $info_data_map = ArrayHelper::index($info_data, 'id');
        }
        $info_data_map_id = array_keys($info_data_map);
        $info_extra = $model->from($this->tableSet['extra'])->where(['id' => $ids])->all();
        $info_extra_map = [];
        if (!empty($info_extra)) {
            $info_extra_map = ArrayHelper::index($info_extra, 'id');
        }
        $jsonData = [];
        $bulkData = "";
        $lastId = 0;
        foreach ($result as $val) {
            //--组织数据
            foreach ($map as $item) {
                //---非独立语言版本 指定更新 不更新独立版本字段
                if (in_array($item, $otherIndex) && !$otherLanguage) {
                    continue;
                }
                $k = $item == 'en' ? '' : $item . '_';
                $jsonData[$k . 'title'] = isset($data_lg[$item]) && isset($data_lg[$item][$val['id']]) ? ($data_lg[$item][$val['id']][$k . 'title']) : '';
                $jsonData[$k . 'keyword'] = isset($data_lg[$item]) && isset($data_lg[$item][$val['id']]) ? ($data_lg[$item][$val['id']][$k . 'keyword']) : '';
            }
            $rowData = '{"index":{"_id":"' . $val['id'] . '", "_type":"info", "_index":"' . $this->index . '"}}' . "\n" . json_encode($jsonData);
            $bulkData .= $rowData;
            $bulkData .= "\n";
            $lastId = $val['id'];
        }
        if (!empty($bulkData)) {
            $status = CommonPluage::goExeEs($this->index, $bulkData, $this->esHost);
            try {
                if ($status) {
                    $log = 'update es success ' . $lastId . PHP_EOL;
                } else {
                    $log = 'update es error ' . $lastId . PHP_EOL;
                }
                $log_url = __DIR__ . '/../runtime/updateEs/' . date('Y-m-d') . '/';
                if (!is_dir($log_url)) {
                    $oldumask = umask(0);
                    mkdir($log_url, 0777, true);
                    umask($oldumask);
                }
                file_put_contents($log_url . 'updateEs-'.$this->index.'.txt', $log . PHP_EOL, FILE_APPEND);
            } catch (\Exception $E) {
            }
        }
        return true;
    }

    /**
     * @return array
     * 设置字段类型
     */
    public function getUpdateDbFiled()
    {
        if ($this->mainType == Element::TYPE) {
            $table = '...';
        } else {
            $table = '...';
        }
        $filedArr = [];
        foreach (Yii::$app->params['PAY_DATA_LANG'] as $lg) {

            $filedArr[$table . '_' . $lg] = [$lg == 'en' ? 'title' : $lg . '_title', $lg == 'en' ? 'keyword' : $lg . '_keyword'];
        }
        //--独立版本分表  每个表对应的字段
        if ($this->lgPre) {
            $filedArr[$table . '_info_data' . $this->lgPre] = [];
            $filedArr[$table . '_info_extra'] = [];
        } else {
            $filedArr[$table . '_info_data'] =[];
            $filedArr[$table . '_info_extra'] = [];
        }

        $filedArr[$table . '_info_detail'] = [];
        $filedArr[$table . '_info'] = [];
        return $filedArr;
    }

    public function getUpdateDbFieldOur()
    {
        if ($this->mainType == Element::TYPE) {
            $table = '....';
        } else {
            $table = '....';
        }
        $filedArr = [];
        $filedArr[$table.'_our'] = ['title','tags','version'];
        return $filedArr;
    }

    /**
     * @param array $array
     * @param array $params
     * @return $this
     *      *  *      * 更新数据库
     * 更新字段
     * $array = ['字段'=>'新值']
     * 更新条件
     * eg:
     *  $params= ['id'=>1]
     */
    public function goUpdateDb(array $array, array $params)
    {
        //---表对应字段集合
        $dataSet = $this->getUpdateDbFiled();
        $insertParams = [];
        foreach ($dataSet as $table => $filedSet) {
            foreach ($array as $filed => $value) {
                if (in_array($filed, $filedSet)) {
                    $insertParams[$table][$filed] = $value;
                }
            }
        }
        if ($insertParams) {
            foreach ($insertParams as $table => $columns) {
                if(in_array($this->lang,['kor','th','cht']) && strpos($table,'info_data')!==false && isset($params['id'])){
                    $iid = [];
                    foreach ($params['id'] as $iiid) {
                        $iid[] = ['id'=>$iiid];
                    }
                    try{
                        Yii::$app->db->createCommand(getBatchInsetSql($table,['id'],$iid,'insert ignore into'))->execute();
                    }catch (\Exception $e){}
                }

                try {
                    Yii::$app->db->createCommand()->update($table, $columns, $params)->execute();
                } catch (\Exception $E) {
                    die($E->getMessage());
                }
            }
        }
        return $this;
    }

    public function getUpdateDbOur(array $array, array $params)
    {
        //---表对应字段集合
        $dataSet = $this->getUpdateDbFieldOur();
        $insertParams = [];
        foreach ($dataSet as $table => $filedSet) {
            foreach ($array as $filed => $value) {
                if (in_array($filed, $filedSet)) {
                    $insertParams[$table][$filed] = $value;
                }
            }
        }
        if ($insertParams) {
            foreach ($insertParams as $table => $columns) {
                try {
                    Yii::$app->db->createCommand()->update($table, $columns, $params)->execute();
                } catch (\Exception $E) {
                    die($E->getMessage());
                }
            }
        }
        return $this;
    }
}