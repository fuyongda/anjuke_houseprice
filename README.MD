利用php爬虫来抓取安居客数据，分析南京房价
本次爬虫的依赖文件: 首先是ares333大神的CURL类。我用的是初期的版本，这是https://github.com/ares333/php-curlmulti大神的github项目地址，他写的curl确实很牛！
采集用的是phpQuery，不知道这个类的朋友，可以自行百度吧。
至于数据的来源，我选择安居客，数据量还是可以的，打开安居客选到南京的频道。开始分析他们的页面结构，至于怎么用phpQuery分析页面结构采集的方法，这里就不做详细的介绍了。分析好结构，好，开始建立数据表。首先建立区域表，房屋交易都是分版块的，版块表结构如下

    CREATE TABLE `area` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(155) NOT NULL COMMENT '南京市区',
      `url` varchar(155) NOT NULL COMMENT '房源区域连接',
      `pid` int(2) NOT NULL COMMENT '分类',
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


我是首先自己添加的一些区服的数据，其实可以采集这些，因为就那几个区，地址有限，就直接添加了。添加了是14条数据：

初始数据准备好了，就可以开始采集所有的区域版块入口地址了。贴上代码
area.php

    <?php
    // +----------------------------------------------------------------------
    // | 采集区域脚本
    // +----------------------------------------------------------------------
    // | Author: NickBai <1902822973@qq.com>
    // +----------------------------------------------------------------------
    set_time_limit(0);
    require 'init.php';
    //根据大区信息前往抓取
    $sql = "select * from `area`";
    $area = $db->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
    foreach($area as $key=>$vo){
        $url = $vo['url'];
        $result = $curl->read($url);
        $charset = preg_match("/<meta.+?charset=[^\w]?([-\w]+)/i", $result['content'], $temp) ? strtolower( $temp[1] ) : "";  
        phpQuery::$defaultCharset = $charset;  //设置默认编码
        $html = phpQuery::newDocumentHTML( $result['content'] );
        $span = $html['.items .sub-items a'];
        $st = $db->prepare("insert into area(name,url,pid) values(?,?,?)");
        foreach($span as $v){
            $v = pq( $v );
            //为方便分页抓取,先加入分页规则
            $href = trim( $v->attr('href') ) . 'p*/#filtersort';
            $st->execute([ trim( $v->text() ), $href, $vo['id']]);
        }
    }
采集出的单条数据如下：
    15    百家湖    http://nanjing.anjuke.com/sale/baijiahu/p*/#filtersort    1
数据地址都有了，而且页面地址我加了*，这样就可以替换了，打开程序就能开始采集每个模块下的其他分页的书据了。最重要的主程序就要开始了；
新建hdetail表来记录采集来的房屋数信息:

    CREATE TABLE `hdetail` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `pid` int(5) NOT NULL COMMENT '区域id',
      `square` int(10) DEFAULT NULL COMMENT '面积',
      `housetype` varchar(55) DEFAULT '' COMMENT '房屋类型',
      `price` int(10) DEFAULT '0' COMMENT '单价',
      `allprice` int(10) DEFAULT '0' COMMENT '总价',
      `name` varchar(155) DEFAULT '' COMMENT '小区名称',
      `addr` varchar(155) DEFAULT '' COMMENT '小区地址',
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

数据库有了，那么主程序奉上。house.php

    <?php
    // +----------------------------------------------------------------------
    // | 采集各区具体房源信息
    // +----------------------------------------------------------------------
    // | Author: NickBai <1902822973@qq.com>
    // +----------------------------------------------------------------------
    set_time_limit(0);
    require 'init.php';
    //查询各板块数据
    $sql = "select * from `area` where id > 14";
    $allarea = $db->query($sql)->fetchAll( PDO::FETCH_ASSOC );
    //http://nanjing.anjuke.com/sale/页面不存在时,会跳转到首页
    foreach($allarea as $key=>$vo){
        $url = $vo['url'];
        $i = 1;
        while ( true ){
            $urls = str_replace( "*" , $i, $url);
            $result = $curl->read( $urls );
            if( "http://nanjing.anjuke.com/sale/" == $result['info']['url'] ){
                break;
            }
            $charset = preg_match("/<meta.+?charset=[^\w]?([-\w]+)/i", $result['content'], $temp) ? strtolower( $temp[1] ) : "";  
            phpQuery::$defaultCharset = $charset;  //设置默认编码
            $html = phpQuery::newDocumentHTML( $result['content'] );
            $div = $html['#houselist-mod li .house-details'];
            $isGet = count( $div->elements );  //未采集到内容跳出,视为结束
            if( 0 == $isGet ){
                break;
            }
            foreach($div as $v){
                $sql = "insert into hdetail(pid,square,housetype,price,allprice,name,addr) ";
                $pid = $vo['id'];
                $square =  rtrim( trim( pq($v)->find("div:eq(1) span:eq(0)")->text() ), "平方米");
                $htype = trim( pq($v)->find("div:eq(1) span:eq(1)")->text() );
                $price = rtrim ( trim( pq($v)->find("div:eq(1) span:eq(2)")->text() ), "元/m²");
                $area = explode(" ", trim( pq($v)->find("div:eq(2) span")->text() ) );
        
                $name =  str_replace( chr(194) . chr(160), "", array_shift($area) );   //utf-8中的空格无法用trim去除,所以采用此方法
                $addr = rtrim( ltrim (trim( array_pop($area) ) , "["), "]" );
                $allprice = trim( pq($v)->siblings(".pro-price")->find("span strong")->text() );
                $sql .= " value( ". $pid .",". $square .", '". $htype ."' ,". $price .",". $allprice .", '". $name ."' ,'". $addr ."' )";
                $db->query($sql);
            }
            echo mb_convert_encoding($vo['name'], "gbk", "utf-8") . " PAGE : ". $i . PHP_EOL;
            $i++;
        }
    }

跳过前面的大区，逐个采集。建议用cmd模式运行这个脚本。因为时间较长，所以用浏览器会导致卡死现象。至于不知道怎么用cmd命令执行php的，自己百度吧。

如果觉得慢的话，你们可以复制几分house.php文件，修改

    $sql = "select * from `area` where id > 14";

根据id进行截取，多打开几个cmd执行，就变成多进程模式了。
下面就是等待了，我是8.16号采集的，一共采集了311226条数 
。好了现在数据库已经生成数据了，差不多三十万条，就可以开始分析了
