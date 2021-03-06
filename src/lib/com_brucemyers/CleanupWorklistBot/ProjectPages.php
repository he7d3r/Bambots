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

namespace com_brucemyers\CleanupWorklistBot;

use PDO;
use com_brucemyers\Util\Logger;

class ProjectPages
{
	const SQL_Articles_by_quality = 'SELECT * FROM category WHERE cat_title = ? AND cat_pages > 0 LIMIT 1';
	const SQL_WikiProject_articles = 'SELECT * FROM category WHERE cat_title = ? AND cat_pages - (cat_subcats + cat_files) > 0 LIMIT 1';
	const SQL_Category_talk = 'SELECT * FROM categorylinks as cl, page WHERE cl.cl_from = page.page_id AND
    			page.page_namespace = 1 AND cl.cl_to = ? LIMIT 1';
	const SQL_Category_article = 'SELECT * FROM categorylinks as cl, page WHERE cl.cl_from = page.page_id AND
    			page.page_namespace = 0 AND cl.cl_to = ? LIMIT 1';
	const SQL_Importance = "SELECT cl_from FROM categorylinks WHERE cl_to = ? AND cl_type = 'page'";
	const SQL_Class = "SELECT cl_from FROM categorylinks WHERE cl_to = ? AND cl_type = 'page'";

	var $dbh_enwiki;
	var $enwiki_host;
	var $user;
	var $pass;
	var $dbh_tools;

    /**
     * Constructor
     *
	 * @param string $enwiki_host
	 * @param string $user
	 * @param string $pass
     * @param PDO $dbh_tools
     */
     public function __construct($enwiki_host, $user, $pass, PDO $dbh_tools)
    {
		$this->enwiki_host = $enwiki_host;
		$this->user = $user;
		$this->pass = $pass;
        $this->dbh_tools = $dbh_tools;
    }

    /**
     * Load a projects pages
     *
     * @param string $category
     * @return int Pages loaded
     * @throws Exception
     */
    public function load($category)
    {
    	$dbh_enwiki = new PDO("mysql:host={$this->enwiki_host};dbname=enwiki_p", $this->user, $this->pass);
    	$dbh_enwiki->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    	$category = str_replace(' ', '_', $category);
    	// Determine the category type
    	$sql = '';

    	// category - x articles by quality (subcats)
    	$sth = $dbh_enwiki->prepare(self::SQL_Articles_by_quality);
    	$ucfcategory = ucfirst($category);
    	$param = "{$ucfcategory}_articles_by_quality";
    	$sth->bindParam(1, $param);
    	$sth->execute();

    	if ($sth->fetch(PDO::FETCH_ASSOC)) $sql = '
			SELECT DISTINCT article.page_id as artid, talk.page_id as talkid, article.page_title as title
			FROM page AS article
			JOIN page AS talk ON article.page_title = talk.page_title
			JOIN categorylinks AS cl1 ON talk.page_id = cl1.cl_from
			JOIN page AS cat ON cl1.cl_to = cat.page_title
			JOIN categorylinks AS cl2 ON cat.page_id = cl2.cl_from
			WHERE cl2.cl_to = ?
			AND article.page_namespace = 0
			AND talk.page_namespace = 1
			AND cat.page_namespace = 14';

    	$sth->closeCursor();
    	$sth = null;

    	if (empty($sql)) {
    		// category - WikiProject x articles
    		$sth = $dbh_enwiki->prepare(self::SQL_WikiProject_articles);
    		$param = "WikiProject_{$category}_articles";
    		$sth->bindParam(1, $param);
    		$sth->execute();

    		if ($sth->fetch(PDO::FETCH_ASSOC)) $sql = '
				SELECT article.page_id as artid, talk.page_id as talkid, article.page_title as title
 				FROM page AS article
    			JOIN page AS talk ON article.page_title = talk.page_title
				JOIN categorylinks AS cl ON talk.page_id = cl.cl_from
				WHERE cl.cl_to = ?
				AND article.page_namespace = 0
				AND talk.page_namespace = 1';

    		$sth->closeCursor();
    		$sth = null;
    	}

    	if (empty($sql)) {
    		// category - x (talk namespace)
    		$sth = $dbh_enwiki->prepare(self::SQL_Category_talk);
    		$param = $category;
    		$sth->bindParam(1, $param);
    		$sth->execute();

    		if ($sth->fetch(PDO::FETCH_ASSOC)) $sql = '
				SELECT article.page_id as artid, talk.page_id as talkid, article.page_title as title
				FROM page AS article
				JOIN page AS talk ON article.page_title = talk.page_title
				JOIN categorylinks AS cl ON talk.page_id = cl.cl_from
				WHERE cl.cl_to = ?
				AND article.page_namespace = 0
				AND talk.page_namespace = 1';

    		$sth->closeCursor();
    		$sth = null;
    	}

    	if (empty($sql)) {
    		// category - x (article namespace)
    		$sth = $dbh_enwiki->prepare(self::SQL_Category_article);
    		$param = $category;
    		$sth->bindParam(1, $param);
    		$sth->execute();

    		if ($sth->fetch(PDO::FETCH_ASSOC)) $sql = '
				SELECT article.page_id as artid, talk.page_id as talkid, article.page_title as title
				FROM page AS article
				LEFT JOIN page AS talk ON article.page_title = talk.page_title
				JOIN categorylinks AS cl ON article.page_id = cl.cl_from
				WHERE cl.cl_to = ?
				AND article.page_namespace = 0
				AND talk.page_namespace = 1';

    		$sth->closeCursor();
    		$sth = null;
    	}

    	if (empty($sql)) throw new CatTypeNotFoundException("Category type not found for '$category'");

    	// Load the pages
   		$this->dbh_tools->exec('TRUNCATE page');
   		$dbh_enwiki = null;
   		$dbh_enwiki = new PDO("mysql:host={$this->enwiki_host};dbname=enwiki_p", $this->user, $this->pass);
   		$dbh_enwiki->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    	$sth = $dbh_enwiki->prepare($sql);
    	$sth->bindParam(1, $param);
    	$sth->setFetchMode(PDO::FETCH_ASSOC);
    	$sth->execute();

    	$this->dbh_tools->beginTransaction();
    	$isth = $this->dbh_tools->prepare('INSERT INTO page (article_id, talk_id, page_title) VALUES (:artid, :talkid, :title)');
    	$page_count = 0;

    	while($row = $sth->fetch()) {
			++$page_count;
    		if ($page_count % 1000 == 0) {
    			$this->dbh_tools->commit();
    			$this->dbh_tools->beginTransaction();
    		}
			$isth->execute($row);
    	}

    	$sth->closeCursor();
    	$sth = null;
    	$this->dbh_tools->commit();
    	$isth = null;

    	// Delete the pages with no issues
    	$sql = 'DELETE FROM page
    		WHERE article_id NOT IN (
    			SELECT cl_from
    			FROM categorylinks
			)';

    	$this->dbh_tools->exec($sql);
    	$dbh_enwiki = null;

    	// Set importance
    	foreach(array_keys(CreateTables::$IMPORTANCES) as $importance) {
   			$dbh_enwiki = new PDO("mysql:host={$this->enwiki_host};dbname=enwiki_p", $this->user, $this->pass);
   			$dbh_enwiki->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    		$sth = $dbh_enwiki->prepare(self::SQL_Importance);
    		$sth->bindValue(1, "{$importance}-importance_{$category}_articles");
    		$sth->setFetchMode(PDO::FETCH_ASSOC);
    		$sth->execute();
    		$count = 0;

    		$cl_froms = array();

    		while($row = $sth->fetch()) {
    			$cl_froms[] = $row['cl_from'];
    		}

    		$sth->closeCursor();
    		$sth = null;
    		$chunks = array_chunk($cl_froms, 100);

    		$this->dbh_tools->beginTransaction();

    		foreach ($chunks as $chunk) {
    			$count += count($chunk);
    			if ($count % 1000 == 0) {
    				$this->dbh_tools->commit();
    				$this->dbh_tools->beginTransaction();
    			}
    			$ids = implode(',', $chunk);
    			$sql = "UPDATE page SET importance = '$importance' WHERE talk_id IN ($ids)";
    			$this->dbh_tools->exec($sql);
    		}

    		$this->dbh_tools->commit();
   			$dbh_enwiki = null;
    	}

    	$total_class = 0;


    	// Set class
    	foreach(array_keys(CreateTables::$CLASSES) as $class) {
   			$dbh_enwiki = new PDO("mysql:host={$this->enwiki_host};dbname=enwiki_p", $this->user, $this->pass);
   			$dbh_enwiki->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    		if ($class == 'Unassessed')
  		        $theclass = "{$class}_{$category}_articles";
       		else
          		$theclass = "{$class}-Class_{$category}_articles";

    		$sth = $dbh_enwiki->prepare(self::SQL_Class);
    		$sth->bindValue(1, $theclass);
    		$sth->setFetchMode(PDO::FETCH_ASSOC);
    		$sth->execute();
    		$count = 0;

    	    $cl_froms = array();

    		while($row = $sth->fetch()) {
    			$cl_froms[] = $row['cl_from'];
    		}

    		$sth->closeCursor();
    		$sth = null;
    		$chunks = array_chunk($cl_froms, 100);

    		$this->dbh_tools->beginTransaction();

    		foreach ($chunks as $chunk) {
    			$count += count($chunk);
    			if ($count % 1000 == 0) {
    				$this->dbh_tools->commit();
    				$this->dbh_tools->beginTransaction();
    			}
    			$ids = implode(',', $chunk);
    			$sql = "UPDATE page SET class = '$class' WHERE talk_id IN ($ids)";
    			$this->dbh_tools->exec($sql);
    		}

    		$this->dbh_tools->commit();
    		$total_class += $count;
   			$dbh_enwiki = null;
    	}

    	if (! $total_class) Logger::log("$category (no classes found)");

    	return $page_count;
    }
}