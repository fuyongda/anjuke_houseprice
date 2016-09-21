CREATE TABLE `area` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(155) NOT NULL COMMENT '�Ͼ�����',
  `url` varchar(155) NOT NULL COMMENT '��Դ��������',
  `pid` int(2) NOT NULL COMMENT '����',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE `hdetail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(5) NOT NULL COMMENT '����id',
  `square` int(10) DEFAULT NULL COMMENT '���',
  `housetype` varchar(55) DEFAULT '' COMMENT '��������',
  `price` int(10) DEFAULT '0' COMMENT '����',
  `allprice` int(10) DEFAULT '0' COMMENT '�ܼ�',
  `name` varchar(155) DEFAULT '' COMMENT 'С������',
  `addr` varchar(155) DEFAULT '' COMMENT 'С����ַ',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;