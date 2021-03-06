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

namespace com_brucemyers\test\DatabaseReportBot;

use PDO;

class CreateTablesBSA
{
	/**
	 * Create test tables
	 *
	 * @param PDO $dbh_enwiki
	 * @param PDO $dbh_tools
	 */
    public function __construct(PDO $dbh_enwiki)
    {
    	// enwiki
   		$dbh_enwiki->exec('DROP TABLE page');

    	$sql = "CREATE TABLE IF NOT EXISTS `redirect` (
 			`rd_from` int unsigned NOT NULL default 0 PRIMARY KEY,
 			`rd_namespace` int NOT NULL default 0,
 			`rd_title` varchar(255) binary NOT NULL default '',
 			`rd_interwiki` varchar(32) default NULL,
 			`rd_fragment` varchar(255) binary default NULL
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

    	$sql = "CREATE TABLE IF NOT EXISTS pagelinks (
    		`pl_from` int unsigned NOT NULL default 0,
    		`pl_from_namespace` int NOT NULL default 0,
    		`pl_namespace` int NOT NULL default 0,
    		`pl_title` varchar(255) binary NOT NULL default ''
    	) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    	$dbh_enwiki->exec($sql);

    	// load enwiki

   		$dbh_enwiki->exec('TRUNCATE redirect');
   		$dbh_enwiki->exec('TRUNCATE page');
   		$dbh_enwiki->exec('TRUNCATE pagelinks');

   		// Good section redirect
    	$dbh_enwiki->exec("INSERT INTO page VALUES (1,0,'1980_Summer_Olympics')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (2,0,'1980_Summer_Olympics_bids')");
    	$dbh_enwiki->exec("INSERT INTO redirect VALUES (2,0,'1980_Summer_Olympics','','Host city selection')");

    	// Bad section redirect
    	$dbh_enwiki->exec("INSERT INTO page VALUES (3,0,'Anesthesia')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (4,0,'Anesthesia_record')");
    	$dbh_enwiki->exec("INSERT INTO redirect VALUES (4,0,'Anesthesia','','Anesthetic monitoring')");
    	$dbh_enwiki->exec("INSERT INTO pagelinks VALUES (1,0,0,'Anesthesia_record')");

    	// Good section redirect with template in section heading
    	$dbh_enwiki->exec("INSERT INTO page VALUES (5,0,'2nd_Regiment,_Royal_Horse_Artillery')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (6,0,'B_Brigade,_Royal_Horse_Artillery')");
    	$dbh_enwiki->exec("INSERT INTO redirect VALUES (6,0,'2nd_Regiment,_Royal_Horse_Artillery','','B Brigade, RHA')");

    	// Good section redirect with url encoded fragment
    	$dbh_enwiki->exec("INSERT INTO page VALUES (7,0,'A3_road')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (8,0,'A3(M)')");
    	$dbh_enwiki->exec("INSERT INTO redirect VALUES (8,0,'A3_road','','A3.28M.29')");

    	// Good section redirect to anchor
    	$dbh_enwiki->exec("INSERT INTO page VALUES (9,0,'ANSI_escape_code')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (10,0,'HPR_(ANSI)')");
    	$dbh_enwiki->exec("INSERT INTO redirect VALUES (10,0,'ANSI_escape_code','','HPR')");

    	// Another bad section redirect
    	$dbh_enwiki->exec("INSERT INTO page VALUES (12,0,'Anesthesia_not_found')");
    	$dbh_enwiki->exec("INSERT INTO redirect VALUES (12,0,'Anesthesia','','Anesthetic not found')");

    	// Good section redirect was failing
    	$dbh_enwiki->exec("INSERT INTO page VALUES (13,0,'Culture_of_Israel')");
    	$dbh_enwiki->exec("INSERT INTO page VALUES (14,0,'Theatre_of_Israel')");
    	$dbh_enwiki->exec("INSERT INTO redirect VALUES (14,0,'Culture_of_Israel','','Theater')");
    }
}