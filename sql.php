CREATE TABLE `area` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(155) NOT NULL COMMENT '南京市区',
  `url` varchar(155) NOT NULL COMMENT '房源区域连接',
  `pid` int(2) NOT NULL COMMENT '分类',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

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