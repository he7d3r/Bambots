<?php
/**
 Copyright 2014 Myers Enterprises II

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
 */

namespace com_brucemyers\test\CleanupWorklistBot;

use PDO;

class CreateTables
{
	/**
	 * Create test tables
	 *
	 * @param PDO $dbh_enwiki
	 * @param PDO $dbh_tools
	 */
    public function __construct(PDO $dbh_enwiki, PDO $dbh_tools)
    {
    	// enwiki
   		$dbh_enwiki->exec('DROP TABLE page');

    	$sql = "CREATE TABLE IF NOT EXISTS `category` (
		  `cat_id` int(10) unsigned NOT NULL,
		  `cat_title` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
		  `cat_pages` int(11) NOT NULL DEFAULT '0',
		  `cat_subcats` int(11) NOT NULL DEFAULT '0',
		  `cat_files` int(11) NOT NULL DEFAULT '0',
		  PRIMARY KEY (`cat_id`),
		  UNIQUE KEY `cat_title` (`cat_title`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    	$dbh_enwiki->exec($sql);

    	$sql = "CREATE TABLE IF NOT EXISTS `categorylinks` (
		  `cl_from` int(10) unsigned NOT NULL DEFAULT '0',
		  `cl_to` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
		  `cl_type` enum('page','subcat','file') NOT NULL DEFAULT 'page',
		  UNIQUE KEY `cl_from` (`cl_from`,`cl_to`),
		  KEY `cl_sortkey` (`cl_to`,`cl_type`,`cl_from`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    	$dbh_enwiki->exec($sql);

    	$sql = "CREATE TABLE IF NOT EXISTS `page` (
		  `page_id` int(10) unsigned NOT NULL,
		  `page_namespace` int(11) NOT NULL,
		  `page_title` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
		  PRIMARY KEY (`page_id`),
		  UNIQUE KEY `name_title` (`page_namespace`,`page_title`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    	$dbh_enwiki->exec($sql);

    	// tools
    	new \com_brucemyers\CleanupWorklistBot\CreateTables($dbh_tools);

    	// load enwiki

   		$dbh_enwiki->exec('TRUNCATE category');
   		$dbh_enwiki->exec('TRUNCATE page');
   		$dbh_enwiki->exec('TRUNCATE categorylinks');
   		$dbh_tools->exec('TRUNCATE history');

     	// project article categories

    	// category - x articles by quality (subcats)
    	$dbh_enwiki->exec("INSERT INTO category VALUES (1,'Michigan_articles_by_quality',2,2,0)");
    	$dbh_enwiki->exec("INSERT INTO category VALUES (2,'B-Class_Michigan_articles',1,0,0)");
   		$dbh_enwiki->exec("INSERT INTO category VALUES (3,'Unassessed_Michigan_articles',3,0,0)");
   		$dbh_enwiki->exec("INSERT INTO category VALUES (4,'Top-importance_Michigan_articles',1,0,0)");
   		$dbh_enwiki->exec("INSERT INTO category VALUES (5,'NA-importance_Michigan_articles',3,0,0)");
   		$dbh_enwiki->exec("INSERT INTO category VALUES (6,'All_articles_needing_coordinates',1,0,0)");
   		$dbh_enwiki->exec("INSERT INTO category VALUES (7,'Articles_needing_cleanup_from_May_2013',3,0,0)");
   		$dbh_enwiki->exec("INSERT INTO category VALUES (8,'Articles_needing_cleanup_from_March_2013',1,0,0)");

   		$dbh_enwiki->exec("INSERT INTO page VALUES (1, 14, 'B-Class_Michigan_articles')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (2, 14, 'Unassessed_Michigan_articles')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (3, 0, 'Michigan')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (4, 1, 'Michigan')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (5, 0, 'Detroit,_Michigan')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (6, 1, 'Detroit,_Michigan')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (7, 0, 'Mackinac_Island')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (8, 1, 'Mackinac_Island')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (9, 0, 'Lansing,_Michigan')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (10, 1, 'Lansing,_Michigan')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (11, 14, 'All_articles_needing_coordinates')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (12, 14, 'Articles_needing_cleanup_from_May_2013')");
   		$dbh_enwiki->exec("INSERT INTO page VALUES (13, 14, 'Articles_needing_cleanup_from_March_2013')");

   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (1, 'Michigan_articles_by_quality', 'subcat')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (2, 'Michigan_articles_by_quality', 'subcat')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (3, 'All_articles_needing_coordinates', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (4, 'B-Class_Michigan_articles', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (4, 'Top-importance_Michigan_articles', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (5, 'Articles_needing_cleanup_from_May_2013', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (5, 'Articles_needing_cleanup_from_March_2013', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (6, 'Unassessed_Michigan_articles', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (6, 'NA-importance_Michigan_articles', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (8, 'Unassessed_Michigan_articles', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (8, 'NA-importance_Michigan_articles', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (9, 'Articles_needing_cleanup_from_May_2013', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (10, 'Unassessed_Michigan_articles', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (10, 'NA-importance_Michigan_articles', 'page')");

   		$dbh_tools->exec("INSERT INTO history VALUES ('Michigan', '2014-05-14', 2, 1, 2)");
   		$dbh_tools->exec("INSERT INTO history VALUES ('Michigan', '2014-05-21', 3, 2, 3)");

   	 	// category - WikiProject x articles
    	$dbh_enwiki->exec("INSERT INTO category VALUES (100,'WikiProject_India_articles',1,0,0)");
    	$dbh_enwiki->exec("INSERT INTO category VALUES (101,'Stub-Class_India_articles',1,0,0)");
    	$dbh_enwiki->exec("INSERT INTO category VALUES (102,'Unknown-importance_India_articles',1,0,0)");
    	$dbh_enwiki->exec("INSERT INTO category VALUES (103,'Articles_needing_cleanup',1,0,0)");

    	$dbh_enwiki->exec("INSERT INTO page VALUES (100, 0, 'India')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (101, 1, 'India')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (102, 14, 'Articles_needing_cleanup')");

    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (100, 'Articles_needing_cleanup', 'page')");
    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (101, 'WikiProject_India_articles', 'page')");
    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (101, 'Stub-Class_India_articles', 'page')");
    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (101, 'Unknown-importance_India_articles', 'page')");


    	// category - x (talk namespace); subcats = only
    	$dbh_enwiki->exec("INSERT INTO category VALUES (200,'Good_article_nominees',1,0,0)");
    	$dbh_enwiki->exec("INSERT INTO category VALUES (201,'Articles_with_incorrect_citation_syntax',1,1,0)");
    	$dbh_enwiki->exec("INSERT INTO category VALUES (202,'Pages_using_citations_with_format_and_no_URL',1,0,0)");

    	$dbh_enwiki->exec("INSERT INTO page VALUES (200, 14, 'Pages_using_citations_with_format_and_no_URL')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (201, 0, 'United_States')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (202, 1, 'United_States')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (203, 14, 'Articles_with_incorrect_citation_syntax')");

    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (200, 'Articles_with_incorrect_citation_syntax', 'subcat')");
    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (201, 'Pages_using_citations_with_format_and_no_URL', 'page')");
    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (202, 'Good_article_nominees', 'page')");


    	// category - x (article namespace)
    	$dbh_enwiki->exec("INSERT INTO category VALUES (300,'Featured_articles',2,0,0)");
    	$dbh_enwiki->exec("INSERT INTO category VALUES (301,'Pages_with_DOIs_inactive_since_2013',2,0,0)");

    	$dbh_enwiki->exec("INSERT INTO page VALUES (301, 0, 'Earth')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (302, 1, 'Earth')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (303, 0, 'Read\'s Cavern')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (304, 1, 'Read\'s Cavern')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (305, 14, 'Pages_with_DOIs_inactive_since_2013')");

    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (301, 'Featured_articles', 'page')");
    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (301, 'Pages_with_DOIs_inactive_since_2013', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (301, 'Articles_needing_cleanup_from_May_2013', 'page')");
    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (303, 'Featured_articles', 'page')");
    	$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (303, 'Pages_with_DOIs_inactive_since_2013', 'page')");
   		$dbh_enwiki->exec("INSERT INTO categorylinks VALUES (303, 'Articles_needing_cleanup_from_May_2013', 'page')");
    }
}