利用php爬虫分析南京房价
前些天看到csdn上的一篇文章，利用python写爬虫来分析上海房价的。感觉挺有意思的。正好最最近写snake后台也写到了文章采集，我也来用php的爬虫来分析一下南京的房价。说干就开始吧。
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

复制代码
跳过前面的大区，逐个采集。建议用cmd模式运行这个脚本。因为时间较长，所以用浏览器会导致卡死现象。至于不知道怎么用cmd命令执行php的，自己百度吧。

如果觉得慢的话，你们可以复制几分house.php文件，修改

    $sql = "select * from `area` where id > 14";

根据id进行截取，多打开几个cmd执行，就变成多进程模式了。
下面就是等待了，我是8.16号采集的，一共采集了311226条数据。好了现在数有了，就可以开始分析了。我分析的代码如下:
    <?php
    require "init.php";
    $data = unserialize( file_get_contents('./data/nj.data') );
    if( empty( $data ) ){
        //全南京
        $sql = "select avg(price) price from hdetail";
        $nanjing = intval( $db->query($sql)->fetch( PDO::FETCH_ASSOC )['price'] );
        //其余数据
        $data = [
            $nanjing,
            getOtherPrice('2,3,4,5,6,7,8,10'),
            getOtherPrice('1'),
            getOtherPrice('2'),
            getOtherPrice('3'),
            getOtherPrice('4'),
            getOtherPrice('5'),
            getOtherPrice('6'),
            getOtherPrice('7'),
            getOtherPrice('8'),
            getOtherPrice('9'),
            getOtherPrice('10'),
            getOtherPrice('11'),
            getOtherPrice('12'),
            getOtherPrice('13')
        ];
        //添加缓存
        file_put_contents('./data/nj.data', serialize( $data ));
    }
    //均价最高TOP10
    $sql = "select avg(price) price,name from hdetail GROUP BY name ORDER BY price desc limit 10";
    $res = $db->query($sql)->fetchAll( PDO::FETCH_ASSOC );
    $x = "";
    $y = "";
    foreach($res as $vo){
        $x .= "'" . $vo['name'] . "',";
        $y .= intval( $vo['price'] ). ",";
    }
    //均价最低TOP10
    $sql = "select avg(price) price,name from hdetail GROUP BY name ORDER BY price asc limit 10";
    $res = $db->query($sql)->fetchAll( PDO::FETCH_ASSOC );
    $xl = "";
    $yl = "";
    foreach($res as $vo){
        $xl .= "'" . $vo['name'] . "',";
        $yl .= intval( $vo['price'] ). ",";
    }
    //交易房型数据
    $sql = "select count(0) allnum, housetype from hdetail GROUP BY housetype order by allnum desc";
    $res = $db->query($sql)->fetchAll( PDO::FETCH_ASSOC );
    $htype = "";
    foreach($res as $vo){
        $htype .= "[ '" . $vo['housetype'] . "', " .$vo['allnum']. "],";
    }
    $htype = rtrim($htype, ',');
    //交易的房屋面积数据
    $square = ['50平米以下', '50-70平米', '70-90平米', '90-120平米', '120-150平米', '150-200平米', '200-300平米', '300平米以上'];
    $sql = "select count(0) allnum, square from hdetail GROUP BY square";
    $squ = $db->query($sql)->fetchAll( PDO::FETCH_ASSOC );
    $p50 = 0;
    $p70 = 0;
    $p90 = 0;
    $p120 = 0;
    $p150 = 0;
    $p200 = 0;
    $p250 = 0;
    $p300 = 0;
    foreach($squ as $key=>$vo){
        if( $vo['square'] < 50 ){
            $p50 += $vo['allnum'];
        }
        if( $vo['square'] >= 50 &&  $vo['square'] < 70 ){
            $p70 += $vo['allnum'];
        }
        if( $vo['square'] >= 70 &&  $vo['square'] < 90 ){
            $p90 += $vo['allnum'];
        }
        if( $vo['square'] >= 90 &&  $vo['square'] < 120 ){
            $p120 += $vo['allnum'];
        }
        if( $vo['square'] >= 120 &&  $vo['square'] < 150 ){
            $p150 += $vo['allnum'];
        }
        if( $vo['square'] >= 150 &&  $vo['square'] < 200 ){
            $p200 += $vo['allnum'];
        }
        if( $vo['square'] >= 200 &&  $vo['square'] < 300 ){
            $p250 += $vo['allnum'];
        }
        if( $vo['square'] >= 300 ){
            $p300 += $vo['allnum'];
        }
    }
    $num = [ $p50, $p70, $p90, $p120, $p150, $p200, $p250, $p300 ];
    $sqStr = "";
    foreach($square as $key=>$vo){
        $sqStr .= "[ '" . $vo . "', " .$num[$key]. "],";
    }
    //根据获取ids字符串获取对应的均价信息
    function getOtherPrice($str){
        global $db;
        $sql = "select id from area where pid in(" . $str . ")";
        $city = $db->query($sql)->fetchAll( PDO::FETCH_ASSOC );
        $ids = "";
        foreach($city as $v){
            $ids .= $v['id'] . ",";
        }
        $sql = "select avg(price) price from hdetail where pid in (".rtrim($ids, ",").")";
        $price = intval( $db->query($sql)->fetch( PDO::FETCH_ASSOC )['price'] );
        return $price;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>南京房价分析</title>
        <link rel="shortcut icon" href="favicon.ico"> <link href="css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
        <link href="css/font-awesome.min.css?v=4.4.0" rel="stylesheet">
        <link href="css/animate.min.css" rel="stylesheet">
        <link href="css/style.min.css?v=4.1.0" rel="stylesheet">
    </head>
    <body class="gray-bg">
        <div class="wrapper wrapper-content">
            <div class="row">
                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="ibox float-e-margins">
                                <div class="ibox-title">
                                    <h5>全南京以及各区二手房均价</h5>
                                    <div class="ibox-tools">
                                        <a class="collapse-link">
                                            <i class="fa fa-chevron-up"></i>
                                        </a>
                                        <a class="close-link">
                                            <i class="fa fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="ibox-content">
                                   <div id="container"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="ibox float-e-margins">
                                <div class="ibox-title">
                                    <h5>均价最高的小区TOP10</h5>
                                    <div class="ibox-tools">
                                        <a class="collapse-link">
                                            <i class="fa fa-chevron-up"></i>
                                        </a>
                                        <a class="close-link">
                                            <i class="fa fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="ibox-content">
                                   <div id="avgpriceh"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="ibox float-e-margins">
                                <div class="ibox-title">
                                    <h5>均价最低的小区TOP10</h5>
                                    <div class="ibox-tools">
                                        <a class="collapse-link">
                                            <i class="fa fa-chevron-up"></i>
                                        </a>
                                        <a class="close-link">
                                            <i class="fa fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="ibox-content">
                                   <div id="avgpricel"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="ibox float-e-margins">
                                <div class="ibox-title">
                                    <h5>交易房型比例</h5>
                                    <div class="ibox-tools">
                                        <a class="collapse-link">
                                            <i class="fa fa-chevron-up"></i>
                                        </a>
                                        <a class="close-link">
                                            <i class="fa fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="ibox-content">
                                   <div id="htype"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="ibox float-e-margins">
                                <div class="ibox-title">
                                    <h5>交易房屋面积比例</h5>
                                    <div class="ibox-tools">
                                        <a class="collapse-link">
                                            <i class="fa fa-chevron-up"></i>
                                        </a>
                                        <a class="close-link">
                                            <i class="fa fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="ibox-content">
                                   <div id="square"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script type="text/javascript" src="js/jquery.min.js?v=2.1.4"></script>
        <script type="text/javascript" src="js/bootstrap.min.js?v=3.3.6"></script>
        <script type="text/javascript" src="http://cdn.hcharts.cn/highcharts/highcharts.js"></script>
        <script type="text/javascript">
            $(function () {
                $('#container').highcharts({
                    chart: {
                        type: 'column'
                    },
                    title: {
                        text: '全南京以及各区二手房均价'
                    },
                    subtitle: {
                        text: '来源于安居客8.16的数据'
                    },
                    xAxis: {
                        categories: ['全南京','江南八区','江宁区','鼓楼区','白下区','玄武区','建邺区','秦淮区','下关区','雨花台区','浦口区','栖霞区','六合区',
                        '溧水区','高淳区','大厂'],
                        crosshair: true
                    },
                    yAxis: {
                        min: 0,
                        title: {
                            text: '元/m²'
                        }
                    },
                    tooltip: {
                        headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                        pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                        '<td style="padding:0"><b>{point.y:.1f} 元/m²</b></td></tr>',
                        footerFormat: '</table>',
                        shared: true,
                        useHTML: true
                    },
                    plotOptions: {
                        column: {
                            pointPadding: 0.2,
                            borderWidth: 0,
                            dataLabels:{
                             enabled:true// dataLabels设为true    
                            }
                        } 
                    },
                    series: [{
                        name: '平均房价',
                        data: [<?php echo implode(',', $data); ?>]
                    }]
                });
                //均价最高top10
                $('#avgpriceh').highcharts({
                    chart: {
                        type: 'column'
                    },
                    title: {
                        text: '均价最高的小区TOP10'
                    },
                    subtitle: {
                        text: '来源于安居客8.16的数据'
                    },
                    xAxis: {
                        categories: [<?=$x; ?>],
                        crosshair: true
                    },
                    yAxis: {
                        min: 0,
                        title: {
                            text: '元/m²'
                        }
                    },
                    tooltip: {
                        headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                        pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                        '<td style="padding:0"><b>{point.y:.1f} 元/m²</b></td></tr>',
                        footerFormat: '</table>',
                        shared: true,
                        useHTML: true
                    },
                    plotOptions: {
                        column: {
                            pointPadding: 0.2,
                            borderWidth: 0,
                            dataLabels:{
                             enabled:true// dataLabels设为true    
                            }
                        } 
                    },
                    series: [{
                        name: '平均房价',
                        data: [<?=$y; ?>]
                    }]
                });
                //均价最低top10
                $('#avgpricel').highcharts({
                    chart: {
                        type: 'column'
                    },
                    title: {
                        text: '均价最低的小区TOP10'
                    },
                    subtitle: {
                        text: '来源于安居客8.16的数据'
                    },
                    xAxis: {
                        categories: [<?=$xl; ?>],
                        crosshair: true
                    },
                    yAxis: {
                        min: 0,
                        title: {
                            text: '元/m²'
                        }
                    },
                    tooltip: {
                        headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                        pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                        '<td style="padding:0"><b>{point.y:.1f} 元/m²</b></td></tr>',
                        footerFormat: '</table>',
                        shared: true,
                        useHTML: true
                    },
                    plotOptions: {
                        column: {
                            pointPadding: 0.2,
                            borderWidth: 0,
                            dataLabels:{
                             enabled:true// dataLabels设为true    
                            }
                        } 
                    },
                    series: [{
                        name: '平均房价',
                        data: [<?=$yl; ?>]
                    }]
                });
                 // Radialize the colors
                Highcharts.getOptions().colors = Highcharts.map(Highcharts.getOptions().colors, function (color) {
                    return {
                        radialGradient: { cx: 0.5, cy: 0.3, r: 0.7 },
                        stops: [
                            [0, color],
                            [1, Highcharts.Color(color).brighten(-0.3).get('rgb')] // darken
                        ]
                    };
                });
                //房型类型
                $('#htype').highcharts({
                    chart: {
                        plotBackgroundColor: null,
                        plotBorderWidth: null,
                        plotShadow: false
                    },
                    title: {
                        text: '交易的二手房型比例'
                    },
                    tooltip: {
                        pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
                    },
                    plotOptions: {
                        pie: {
                            allowPointSelect: true,
                            cursor: 'pointer',
                            dataLabels: {
                                enabled: true,
                                format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                                style: {
                                    color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                                },
                                connectorColor: 'silver'
                            }
                        }
                    },
                    series: [{
                        type: 'pie',
                        name: 'Browser share',
                        data: [
                            <?=$htype; ?>
                        ]
                    }]
                });
                //房型面积类型
                $('#square').highcharts({
                    chart: {
                        plotBackgroundColor: null,
                        plotBorderWidth: null,
                        plotShadow: false
                    },
                    title: {
                        text: '交易的二手房面积比例'
                    },
                    tooltip: {
                        pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
                    },
                    plotOptions: {
                        pie: {
                            allowPointSelect: true,
                            cursor: 'pointer',
                            dataLabels: {
                                enabled: true,
                                format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                                style: {
                                    color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                                },
                                connectorColor: 'silver'
                            }
                        }
                    },
                    series: [{
                        type: 'pie',
                        name: 'Browser share',
                        data: [
                            <?=$sqStr; ?>
                        ]
                    }]
                });
            });
        </script>
    </body>
    </html>

用以上html可以生成饼状图
