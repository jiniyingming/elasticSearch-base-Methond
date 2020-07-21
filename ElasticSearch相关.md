#ElasticSearch Kibana 相关操作  
```
#查看所有索引状态
GET _cat/indices 
#索引中增加字段
PUT /gb/_mapping/tweet
{
  "properties": {
    "tag": {
      "type": "text",
      "index": false
    }
  }
}
#创建索引 并设置相应国家的分词器
PUT /back_1120
{
  "settings": {
    "number_of_shards": 5,
    "number_of_replicas": 0,
    "analysis": {
      "analyzer": {
        "my_icu": {
          "char_filter": [
            "icu_normalizer"
          ],
          "tokenizer": "icu_tokenizer"
        }
      }
    }
  },
  "mappings": {
    "info": {
      "dynamic": "strict",
      "properties": {
        "ar_keyword": {
          "type": "text",
          "analyzer": "arabic"
        },
        "cht_keyword": {
          "type": "text",
          "fields": {
            "key": {
              "type": "keyword"
            }
          },
          "analyzer": "my_icu"
        },
        "de_title": {
          "type": "text",
          "boost": 9,
          "analyzer": "german"
        },
        "fra_keyword": {
          "type": "text",
          "analyzer": "french"
        },
        "hi_title": {
          "type": "text",
          "boost": 9,
          "fields": {
            "key": {
              "type": "keyword"
            }
          },
          "analyzer": "hindi"
        },
        "it_keyword": {
          "type": "text",
          "fields": {
            "key": {
              "type": "keyword"
            }
          },
          "analyzer": "italian"
        },
        "jp_title": {
          "type": "text",
          "boost": 9,
          "analyzer": "kuromoji"
        },
        "kor_title": {
          "type": "text",
          "boost": 9,
          "analyzer": "my_icu"
        },
        "ms_title": {
          "type": "text",
          "boost": 9,
          "fields": {
            "key": {
              "type": "keyword"
            }
          },
          "analyzer": "my_icu"
        },
        "pl_title": {
          "type": "text",
          "boost": 9,
          "analyzer": "standard"
        },
        "pt_keyword": {
          "type": "text",
          "analyzer": "portuguese"
        },
        "ru_title": {
          "type": "text",
          "boost": 9,
          "analyzer": "russian"
        },
        "spa_keyword": {
          "type": "text",
          "analyzer": "spanish"
        },
        "th_keyword": {
          "type": "text",
          "analyzer": "thai"
        },
        "title": {
          "type": "text",
          "boost": 9,
          "analyzer": "english"
        },
        "vie_keyword": {
          "type": "text",
          "analyzer": "my_icu"
        }
      }
    }
  }
}

#查看某个type的映射关系
GET /{index}/_mapping/{type}
#修改es 刷新状态  更新es时建议更改为 -1  不进行刷新
PUT /iconfont_new/_settings
{
      "refresh_interval": "60s"  
}
#创建es 索引
PUT /help
{
 "settings": {
          "analysis": {
         "analyzer": {
            "my_icu": {
               "char_filter": [
                  "icu_normalizer"
               ],
               "tokenizer": "icu_tokenizer"
            }
         }
      }
   },  
  "mappings": {
    "info": {
      "dynamic": "strict",
      "properties": {
        "hi_keyword": {
          "type": "text",
          "analyzer": "english",
          "fields": {
            "key": {
              "type": "keyword"
            }
          }
        },
        "related_terms": {
          "type": "keyword"
        }
      }
    }
  }
} 
#reindex
POST _reindex
{
  "source": {
    "index": "back_re"
  },
  "dest": {

    "index": "back_1120"
  }
}
#别名alias
POST /_aliases
{
  "actions": [
    {
      "remove": {
        "index": "back_re",
        "alias": "back_a"
      }
    },
    {
      "add": {
        "index": "back_1120",
        "alias": "back_a"
      }
    }
  ]
}
 
``` 