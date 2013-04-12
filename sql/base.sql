-- phpMyAdmin SQL Dump
-- version 3.4.5
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Mar 24, 2013 at 11:59 PM
-- Server version: 5.5.16
-- PHP Version: 5.3.8

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `reco`
--

-- --------------------------------------------------------

--
-- Table structure for table `access`
--

CREATE TABLE IF NOT EXISTS `access` (
  `object` int(11) DEFAULT NULL,
  `user` int(11) DEFAULT NULL,
  `group` int(11) DEFAULT NULL,
  `right` int(11) DEFAULT NULL,
  `defined` int(11) DEFAULT NULL,
  KEY `obj` (`object`),
  KEY `obj_user` (`object`,`user`),
  KEY `obj_group` (`object`,`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `access`
--

INSERT INTO `access` (`object`, `user`, `group`, `right`, `defined`) VALUES
(1, NULL, 1, 31, 31),
(1, -1, NULL, 31, 31),
(1, -2, NULL, 1, 1),
(2, -2, NULL, 4, 4);

-- --------------------------------------------------------

--
-- Table structure for table `config`
--

CREATE TABLE IF NOT EXISTS `config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `config`
--

INSERT INTO `config` (`id`, `key`, `value`) VALUES
(1, 'version', '0.4');

-- --------------------------------------------------------

--
-- Table structure for table `game_log`
--

CREATE TABLE IF NOT EXISTS `game_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL,
  `game` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `linked` int(11) NOT NULL,
  `info` varchar(255) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `param` int(11) NOT NULL,
  `ts_real` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `game_idx` (`game`),
  KEY `tgl_idx` (`type`,`game`,`linked`),
  KEY `utl_idx` (`user`,`type`,`linked`),
  KEY `utg_idx` (`user`,`type`),
  KEY `utgl_idx` (`user`,`type`,`game`,`linked`),
  KEY `idx_linked` (`linked`),
  KEY `idx_ugt` (`user`,`game`,`type`),
  KEY `idx_tl` (`type`,`linked`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `game_request`
--

CREATE TABLE IF NOT EXISTS `game_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL,
  `game` int(11) NOT NULL,
  `approved` tinyint(1) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `login` varchar(50) NOT NULL,
  `force_refresh` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `group`
--

CREATE TABLE IF NOT EXISTS `group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) DEFAULT NULL,
  `prio` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `group`
--

INSERT INTO `group` (`id`, `name`, `prio`) VALUES
(1, 'Администраторы', 1),
(2, 'Зарегистрированные пользователи', 255);

-- --------------------------------------------------------

--
-- Table structure for table `log`
--

CREATE TABLE IF NOT EXISTS `log` (
  `id` int(11) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `action` int(11) DEFAULT NULL,
  `modified_by` char(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `manual_task_list`
--

CREATE TABLE IF NOT EXISTS `manual_task_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL,
  `task` int(11) NOT NULL,
  `linked` int(11) NOT NULL,
  `game` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(255) NOT NULL,
  `color` varchar(32) NOT NULL,
  `user` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `mutex`
--

CREATE TABLE IF NOT EXISTS `mutex` (
  `keystr` varchar(24) NOT NULL DEFAULT '',
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`keystr`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `object_class`
--

CREATE TABLE IF NOT EXISTS `object_class` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) DEFAULT NULL,
  `table_name` varchar(32) DEFAULT NULL,
  `data_source` varchar(32) DEFAULT NULL,
  `template` varchar(32) DEFAULT NULL,
  `add_allowed` int(1) DEFAULT NULL,
  `allowed_parents` varchar(80) DEFAULT NULL,
  `visible` int(1) DEFAULT '1',
  `default_rights` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=12 ;

--
-- Dumping data for table `object_class`
--

INSERT INTO `object_class` (`id`, `name`, `table_name`, `data_source`, `template`, `add_allowed`, `allowed_parents`, `visible`, `default_rights`) VALUES
(1, 'Home page', 'object_data_1', 'dfltsrc', 'default.tpl', NULL, NULL, 1, NULL),
(2, 'Игры', 'object_data_2', 'games', 'games.tpl', 0, '1', 1, NULL),
(3, 'Игра', 'object_data_3', 'game', 'game.tpl', 1, '2', 1, 'u,-2,0,31'),
(4, 'Группа заданий', 'object_data_4', 'taskgr', 'taskgr.tpl', 1, '3,4', 1, NULL),
(5, 'Задание', 'object_data_5', 'task', 'task.tpl', 1, '3,4', 1, NULL),
(6, 'Подсказка', 'object_data_6', 'hint', 'hint.tpl', 1, '5', 1, NULL),
(10, 'Типы групп', 'object_data_10', 'gptypes', 'gptypes.tpl', 0, '1', 0, NULL),
(11, 'Тип групп', 'object_data_11', 'gptype', 'gptype.tpl', 0, '10', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `object_data_2`
--

CREATE TABLE IF NOT EXISTS `object_data_2` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `object_data_3`
--

CREATE TABLE IF NOT EXISTS `object_data_3` (
  `id` int(11) NOT NULL,
  `start_time` varchar(255) DEFAULT NULL,
  `finished` tinyint(1) NOT NULL,
  `announce` text,
  `no_requests` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `object_data_4`
--

CREATE TABLE IF NOT EXISTS `object_data_4` (
  `id` int(11) NOT NULL,
  `task_type` varchar(255) DEFAULT NULL,
  `prio_max` int(11) DEFAULT NULL,
  `prio_mid` int(11) DEFAULT NULL,
  `start_delay` int(11) DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `time_limit_parent` int(11) DEFAULT NULL,
  `manual` tinyint(1) DEFAULT NULL,
  `comment` text,
  `show_comment` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `object_data_5`
--

CREATE TABLE IF NOT EXISTS `object_data_5` (
  `id` int(11) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `time_penalty` int(11) DEFAULT NULL,
  `task_type` tinyint(1) DEFAULT NULL,
  `task_text` text,
  `time_limit_parent` int(11) DEFAULT NULL,
  `start_delay` int(11) DEFAULT NULL,
  `dc` varchar(255) DEFAULT NULL,
  `comment` text,
  `manual` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `object_data_6`
--

CREATE TABLE IF NOT EXISTS `object_data_6` (
  `id` int(11) NOT NULL,
  `delay` int(11) DEFAULT NULL,
  `text` text,
  `penalty` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `object_data_10`
--

CREATE TABLE IF NOT EXISTS `object_data_10` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `object_data_11`
--

CREATE TABLE IF NOT EXISTS `object_data_11` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `object_property`
--

CREATE TABLE IF NOT EXISTS `object_property` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(40) DEFAULT NULL,
  `object_class_id` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  `table_field` varchar(32) DEFAULT NULL,
  `list_children` int(11) DEFAULT NULL,
  `order_token` int(11) DEFAULT NULL,
  `is_name` tinyint(1) DEFAULT NULL,
  `maxcnt` int(11) DEFAULT NULL,
  `img_desc` tinyint(1) DEFAULT NULL,
  `default_value` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=43 ;

--
-- Dumping data for table `object_property`
--

INSERT INTO `object_property` (`id`, `name`, `object_class_id`, `type`, `table_field`, `list_children`, `order_token`, `is_name`, `maxcnt`, `img_desc`, `default_value`) VALUES
(1, 'Название игры', 3, 3, NULL, NULL, 1, 1, NULL, 0, 'Игра %n'),
(2, 'Название группы', 4, 3, NULL, NULL, 1, 1, NULL, 0, 'Группа заданий %n'),
(3, 'Название задания', 5, 3, NULL, NULL, 1, 1, NULL, 0, 'Задание %n'),
(4, 'Название подсказки', 6, 3, NULL, NULL, 1, 1, NULL, 0, 'Подсказка %n'),
(5, 'Тип выдачи заданий', 4, 1, 'task_type', 1100, 2, 0, NULL, 0, '1001'),
(6, 'Заданий с приоритетом МАКС', 4, 3, 'prio_max', NULL, 3, 0, NULL, 0, NULL),
(7, 'Заданий с приоритетом СРЕДН', 4, 3, 'prio_mid', NULL, 4, 0, NULL, 0, NULL),
(8, 'Задержка перед началом в сек.', 4, 3, 'start_delay', NULL, 5, 0, NULL, 0, NULL),
(9, 'Название раздела', 2, 3, NULL, NULL, 1, 1, NULL, 0, 'Игры'),
(10, 'Код', 5, 3, 'code', NULL, 2, 0, NULL, 0, NULL),
(11, 'Время выполнения в сек.', 5, 3, 'time_limit', NULL, 3, 0, NULL, 0, '5400'),
(12, 'Штраф или бонус в сек. ', 5, 3, 'time_penalty', NULL, 4, 0, NULL, 0, '900'),
(13, 'Это бонус', 5, 3, 'task_type', NULL, 5, 0, NULL, 0, '0'),
(14, 'Текст задания', 5, 2, 'task_text', NULL, 6, 0, NULL, 0, NULL),
(15, 'Время выдачи в сек.', 6, 3, 'delay', NULL, 2, 0, NULL, 0, NULL),
(16, 'Текст подсказки', 6, 2, 'text', NULL, 3, 0, NULL, 0, NULL),
(18, 'Время старта (ДД.ММ.ГГГГ ЧЧ:ММ)', 3, 3, 'start_time', NULL, 2, 0, NULL, 0, NULL),
(19, 'Штраф за подсказку', 6, 3, 'penalty', NULL, 4, 0, NULL, 0, NULL),
(20, 'Лимит времени в сек.', 4, 3, 'time_limit', NULL, 6, 0, NULL, 0, NULL),
(21, 'Лимит времени после ЗРГ', 5, 3, 'time_limit_parent', NULL, 7, 0, NULL, 0, NULL),
(22, 'Игра закончена', 3, 3, 'finished', NULL, 8, 0, NULL, 0, '0'),
(23, 'Задержка перед началом в сек.', 5, 3, 'start_delay', NULL, 8, 0, NULL, 0, NULL),
(24, 'Код опасности', 5, 3, 'dc', NULL, 9, 0, NULL, 0, NULL),
(25, 'Лимит времени после ЗРГ', 4, 3, 'time_limit_parent', NULL, 7, 0, NULL, 0, NULL),
(26, 'Комментарий', 5, 2, 'comment', NULL, 10, 0, NULL, 0, NULL),
(35, 'Название', 10, 3, NULL, NULL, 1, 1, NULL, 0, NULL),
(36, 'Название', 11, 3, NULL, NULL, 1, 1, NULL, 0, NULL),
(37, 'Только ручная выдача', 4, 3, 'manual', NULL, 8, 0, NULL, 0, NULL),
(38, 'Только ручная выдача', 5, 3, 'manual', NULL, 11, 0, NULL, 0, NULL),
(39, 'Комментарий к группе', 4, 2, 'comment', NULL, 9, 0, NULL, 0, NULL),
(40, 'Отображать комментарий', 4, 3, 'show_comment', NULL, 10, 0, NULL, 0, NULL),
(41, 'Анонс', 3, 2, 'announce', NULL, 9, 0, NULL, 0, NULL),
(42, 'Запретить прием заявок', 3, 3, 'no_requests', NULL, 10, 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `photo`
--

CREATE TABLE IF NOT EXISTS `photo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `href` varchar(255) DEFAULT NULL,
  `reference` int(11) DEFAULT NULL,
  `element_id` int(11) DEFAULT NULL,
  `order_token` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reference` (`reference`),
  KEY `reference_2` (`reference`,`element_id`),
  KEY `ref` (`reference`),
  KEY `ref_el` (`reference`,`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `photo_gallery`
--

CREATE TABLE IF NOT EXISTS `photo_gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_editor_element_id` int(11) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  `prefix` varchar(8) DEFAULT NULL,
  `crop` tinyint(1) DEFAULT NULL,
  `forceW` tinyint(1) DEFAULT NULL,
  `forceH` tinyint(1) DEFAULT NULL,
  `jpeg_quality` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `photo_gallery`
--

INSERT INTO `photo_gallery` (`id`, `page_editor_element_id`, `width`, `height`, `type`, `prefix`, `crop`, `forceW`, `forceH`, `jpeg_quality`) VALUES
(1, 43, 640, 480, 2, 'test_', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `session`
--

CREATE TABLE IF NOT EXISTS `session` (
  `user_id` int(11) NOT NULL,
  `session_id` char(32) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tree_updated` datetime DEFAULT NULL,
  `ip` varchar(15) NOT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `task_selection_request`
--

CREATE TABLE IF NOT EXISTS `task_selection_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL,
  `group` int(11) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=383 ;

-- --------------------------------------------------------

--
-- Table structure for table `tree`
--

CREATE TABLE IF NOT EXISTS `tree` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent` int(11) DEFAULT NULL,
  `class` int(11) DEFAULT NULL,
  `order_token` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `template` varchar(32) DEFAULT NULL,
  `lock` char(32) DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL,
  `modified_by` char(32) DEFAULT NULL,
  `internal` varchar(32) DEFAULT NULL,
  `owner` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent`),
  KEY `idx_parent_type` (`parent`,`class`),
  KEY `idx_type` (`class`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1261 ;

--
-- Dumping data for table `tree`
--

INSERT INTO `tree` (`id`, `parent`, `class`, `order_token`, `name`, `template`, `lock`, `last_modified`, `modified_by`, `internal`, `owner`) VALUES
(1, 0, 1, NULL, 'Home page', NULL, NULL, '2010-07-11 04:06:00', '1', NULL, 1),
(2, 1, 2, 2, 'Игры', NULL, NULL, '2011-10-14 16:45:25', '35b687dea05b52ae4dc6ced278253d30', NULL, 1),
(1001, 1100, 11, 1, 'Последовательно', NULL, NULL, '2010-11-12 17:19:15', '2772ddfde7ad667449be098431eaf571', NULL, 1),
(1002, 1100, 11, 2, 'Параллельно', NULL, NULL, '2010-11-12 17:19:15', '2772ddfde7ad667449be098431eaf571', NULL, 1),
(1003, 1100, 11, 3, 'Случайно', NULL, NULL, '2010-11-12 17:10:32', 'bc7a75087c822455dccccc443e499c3a', NULL, 1),
(1004, 1100, 11, 4, 'Случайно с приоритетами', NULL, NULL, '2010-11-12 17:10:41', 'bc7a75087c822455dccccc443e499c3a', NULL, 1),
(1005, 1100, 11, 5, 'Выбор пользователем', NULL, NULL, '2012-08-27 20:48:19', '8bb1d5130cf1d47ae58eea96119db462', NULL, 1),
(1100, 1, 10, 2, 'Типы групп выдачи', NULL, NULL, '2011-10-14 16:45:25', '35b687dea05b52ae4dc6ced278253d30', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `id` smallint(8) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL DEFAULT '',
  `password` varchar(32) NOT NULL DEFAULT '',
  `salt` char(3) NOT NULL DEFAULT '',
  `access` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `group` int(11) DEFAULT NULL,
  `is_emu` tinyint(1) NOT NULL,
  `last_point` int(11) NOT NULL,
  `last_ts` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=477 ;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `login`, `password`, `salt`, `access`, `name`, `group`, `is_emu`, `last_point`, `last_ts`) VALUES
(1, 'admin', '419b4859458f027e1884a3192945651b', 'eq=', 1, 'admin', 1, 0, 0, 0);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
