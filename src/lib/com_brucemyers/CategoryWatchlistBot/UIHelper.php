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

namespace com_brucemyers\CategoryWatchlistBot;

use com_brucemyers\Util\Config;
use com_brucemyers\Util\MySQLDate;
use com_brucemyers\Util\FileCache;
use com_brucemyers\Util\DateUtil;
use com_brucemyers\Util\HttpUtil;
use com_brucemyers\Util\L10N;
use PDO;

class UIHelper
{
	public $max_watch_days;
	protected $serviceMgr;
	protected $dbh_tools;

	public function __construct()
	{
		$this->serviceMgr = new ServiceManager();
		$this->dbh_tools = $this->serviceMgr->getDBConnection('tools');

		$this->max_watch_days = Config::get(CategoryWatchlistBot::MAX_WATCH_DAYS);
	}

	/**
	 * Get a list of wikis.
	 *
	 * @return array wikiname => array('title', 'domain')
	 */
	public function getWikis()
	{
		$sql = 'SELECT * FROM wikis ORDER BY wikititle';
		$sth = $this->dbh_tools->query($sql);

		//$wikis = array('enwiki' => array('title' => 'English Wikipedia', 'domain' => 'en.wikipedia.org', 'lang' => 'en'),
		//	'commonswiki' => array('title' => 'Wikipedia Commons', 'domain' => 'commons.wikimedia.org', 'lang' => 'en')); // Want first
		$wikis = array('enwiki' => array('title' => 'English Wikipedia', 'domain' => 'en.wikipedia.org', 'lang' => 'en')); // Want first

		while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
			$wikiname = $row['wikiname'];
			if (! isset($wikis[$wikiname])) {
				$wikis[$wikiname] = array('title' => $row['wikititle'], 'domain' => $row['wikidomain'], 'lang' => $row['lang']);
			}
		}

		return $wikis;
	}

	/**
	 * Fetch a saved queries paramaters
	 *
	 * @param string $queryid Query hash
	 * @return array Parameters, empty = not found
	 */
	public function fetchParams($queryid)
	{
		$sth = $this->dbh_tools->prepare('SELECT params FROM querys WHERE hash = ?');
		$sth->bindParam(1, $queryid);
		$sth->execute();

		if ($row = $sth->fetch(PDO::FETCH_ASSOC)) return unserialize($row['params']);
		else return array();
	}

	/**
	 * Save a query
	 *
	 * @param array $params
	 */
	public function saveQuery(&$params)
	{
		$serialized = serialize($params);
		$hash = md5($serialized);
		$accessdate = MySQLDate::toMySQLDate(time());
		$wikiname = $params['wiki'];

		// See if we have a query record
    	$sth = $this->dbh_tools->prepare("SELECT id FROM querys WHERE hash = ?");
    	$sth->bindParam(1, $hash);
    	$sth->execute();

    	if ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    		$queryid = $row['id'];
    		$sth = $this->dbh_tools->prepare("UPDATE querys SET lastaccess = ? WHERE id = $queryid");
    		$sth->bindParam(1, $accessdate);
    		$sth->execute();
    	} else {
    		$sth = $this->dbh_tools->prepare("INSERT INTO querys (wikiname,hash,params,lastaccess,lastrecalc) VALUES (?,?,?,?,?)");
    		$sth->bindParam(1, $wikiname);
    		$sth->bindParam(2, $hash);
    		$sth->bindParam(3, $serialized);
    		$sth->bindParam(4, $accessdate);
    		$sth->bindParam(5, $accessdate);
    		$sth->execute();
		}
	}

	/**
	 * Get watch list results
	 *
	 * @param array $params
	 * @param int $page
	 * @param int $max_rows
	 * @return array Results, keys = errors - array(), results - array()
	 * @see WatchResults
	 */
	public function getResults($params, $page, $max_rows)
	{
		$errors = array();
		$serialized = serialize($params);
		$hash = md5($serialized);
		$accessdate = MySQLDate::toMySQLDate(time());
		$wikiname = $params['wiki'];

		// See if we have a query record
    	$sth = $this->dbh_tools->prepare("SELECT id FROM querys WHERE hash = ?");
    	$sth->bindParam(1, $hash);
    	$sth->execute();

    	if ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    		$queryid = $row['id'];
    		$sth = $this->dbh_tools->prepare("UPDATE querys SET lastaccess = ? WHERE id = $queryid");
    		$sth->bindParam(1, $accessdate);
    		$sth->execute();
		} else {
			return array('errors' => array('Query not found'), 'results' => array());
		}

		$dbh_wiki = $this->serviceMgr->getDBConnection($wikiname);

		$results = array();

    	$watchResults = new WatchResults($dbh_wiki, $this->dbh_tools);
    	$results = $watchResults->getResults($queryid, $params, $page, $max_rows);

		return array('errors' => $errors, 'results' => $results);
	}

	/**
	 * Get recent results.
	 *
	 * @param string $wikiname
	 * @param int $page
	 * @param int $max_rows
	 * @return array
	 */
	public function getRecent($wikiname, $page, $max_rows)
	{
		$queryid = 0;
		$page = (int)$page - 1;
		if ($page < 0 || $page > 1000) $page = 0;
		$offset = $page * $max_rows;

		$cachekey = CategoryWatchlistBot::CACHE_PREFIX_RESULT . $queryid . '_'. $wikiname . '_' . $page;

		// Check the cache
		$results = FileCache::getData($cachekey);
		if (! empty($results)) {
			$results = unserialize($results);
			return $results;
		}

		// Get the updated pages
		$sth = $this->dbh_tools->prepare("SELECT * FROM `{$wikiname}_diffs` " .
		" ORDER BY id DESC " .
		" LIMIT $offset,$max_rows");
		$sth->execute();
		$sth->setFetchMode(PDO::FETCH_ASSOC);

		$results = array();

		while ($row = $sth->fetch()) {
			$results[] = $row;
		}

		$sth->closeCursor();

		if (! count($results)) return $results;

		$serialized = serialize($results);

		FileCache::putData($cachekey, $serialized);

		return $results;
	}

	/**
	 * Generate an atom feed
	 *
	 * @param string $query Query id
	 * @return boolean true - success, false - failure
	 */
	public function generateAtom($query)
	{
		header('Content-Type: application/atom+xml');

		// Check the cache
		$feed = FileCache::getData(CategoryWatchlistBot::CACHE_PREFIX_ATOM . $query);
		if (! empty($feed)) {
			echo $feed;
			return true;
		}

		$params = $this->fetchParams($query);
		$results = $this->getResults($params, 1, 100);

		$host  = $_SERVER['HTTP_HOST'];
		$uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
		$extra = "CategoryWatchlist.php?action=atom&amp;query=$query";
		$protocol = HttpUtil::getProtocol();
		$wikis = $this->getWikis();
		$domain = $wikis[$params['wiki']]['domain'];
		$wikiprefix = "$protocol://$domain/wiki/";
		$l10n = new L10N($wikis[$params['wiki']]['lang']);

		$updated = gmdate("Y-m-d\TH:i:s\Z");

		if (empty($params['title'])) {
			$title = $params['cn1'];
			if (! empty($params['cn2'])) $title = ', ...';
		} else {
			$title = $params['title'];
		}

		$title = htmlentities($title, ENT_QUOTES, 'UTF-8');
		$title2 = htmlentities(htmlentities($l10n->get('watchlisttitle'), ENT_COMPAT, 'UTF-8') . " : $title", ENT_COMPAT, 'UTF-8');

		$feed = "<?xml version=\"1.0\"?>\n<feed xmlns=\"http://www.w3.org/2005/Atom\" xml:lang=\"en\">\n";
		$feed .= "<id>//$host$uri/$extra</id>\n";
		$feed .= "<title>$title2</title>\n";
		$feed .= "<link rel=\"self\" type=\"application/atom+xml\" href=\"$protocol://$host$uri/$extra\" />\n";
		$feed .= "<link rel=\"alternate\" type=\"text/html\" href=\"$protocol://$host$uri/CategoryWatchlist.php?query=$query\" />\n";
		$feed .= "<updated>$updated</updated>\n";

		// Sort by date, namespace, title
		$dategroups = array();
		foreach ($results['results'] as &$result) {
			$date = $result['diffdate'];
			unset($result['diffdate']);
			if (! isset($dategroups[$date])) $dategroups[$date] = array();
			$dategroups[$date][] = $result;
		}
		unset($result);

		$hour = htmlentities($l10n->get('hour'), ENT_COMPAT, 'UTF-8');
		$summary = htmlentities($l10n->get('mostrecentresults'), ENT_COMPAT, 'UTF-8') . "<table><thead><tr><th>" .
			htmlentities($l10n->get('page', true), ENT_COMPAT, 'UTF-8') . "</th><th>+/&ndash;</th><th>" .
			htmlentities($l10n->get('category', true), ENT_COMPAT, 'UTF-8') . " / " .
			htmlentities($l10n->get('template', true), ENT_COMPAT, 'UTF-8') . "</th></tr></thead><tbody>\n";

		foreach ($dategroups as $date => &$dategroup) {
			usort($dategroup, array($this, 'resultgroupsort'));
			$displaydate = date('F j, Y G', MySQLDate::toPHP($date));
			$ord = DateUtil::ordinal(date('G', MySQLDate::toPHP($date)));
			$summary .= "<tr><td><i>$displaydate$ord $hour</i></td><td>&nbsp;</td><td>&nbsp;</td></tr>\n";
			$x = 0;
			$prevtitle = '';
			$prevaction = '';

			foreach ($dategroup as &$result) {
				$title = $result['title'];
				$action = $result['plusminus'];
				$category = htmlentities($result['category'], ENT_COMPAT, 'UTF-8');
				if ($result['cat_template'] == 'T') $category = '{{' . $category . '}}';
				$displayaction = ($action == '-') ? '&ndash;' : $action;

				if ($title == $prevtitle && $action == $prevaction) {
					$summary .= "; $category";
				} elseif ($title == $prevtitle) {
					$summary .= "</td></tr>\n";
					$summary .= "<tr><td>&nbsp;</td><td>$displayaction</td><td>$category";
				} else {
					if ($x++ > 0) $summary .= "</td></tr>\n";
					$summary .= "<tr><td><a href=\"$wikiprefix" . urlencode(str_replace(' ', '_', $title)) . "\">" .
						htmlentities($title, ENT_COMPAT, 'UTF-8') . "</a></td><td>$displayaction</td><td>$category";
				}
				$prevtitle = $title;
				$prevaction = $action;
			}

			if ($x > 0) $summary .= "</td></tr>\n";
		}

		$summary .= "</tbody></table>\n";
		$summary = htmlentities($summary, ENT_COMPAT, 'UTF-8');
		unset($dategroup);
		unset($result);

		foreach ($dategroups as $date => &$dategroup) {
			$date = MySQLDate::toPHP($date);
			$ord = DateUtil::ordinal(date('G', $date));
			$humandate = date('F j, Y G', $date) . $ord;
			$updated = gmdate("Y-m-d\TH:i:s\Z", $date);
			$title = htmlentities(htmlentities($l10n->get('resultsfor'), ENT_COMPAT, 'UTF-8') . " $humandate $hour", ENT_COMPAT, 'UTF-8');

			$feed .= "<entry>\n";
			$feed .= "<id>//$host$uri/$extra&amp;date=$humandate</id>\n";
			$feed .= "<title>$title</title>\n";
			$feed .= "<link rel=\"alternate\" type=\"text/html\" href=\"$protocol://$host$uri/CategoryWatchlist.php?query=$query\" />\n";
			$feed .= "<updated>$updated</updated>\n";
			$feed .= "<summary type=\"html\">$summary</summary>\n";
			$feed .= "<author><name>CategoryWatchlistBot</name></author>\n";
			$feed .= "</entry>\n";
			break; // Only want one entry
		}
		unset($dategroup);

		$feed .= '</feed>';

		FileCache::putData(CategoryWatchlistBot::CACHE_PREFIX_ATOM . $query, $feed);

		echo $feed;

		return true;
	}

	/**
	 * Sort a result group by namespace, title
	 *
	 * @param unknown $a
	 * @param unknown $b
	 * @return number
	 */
	function resultgroupsort($a, $b)
	{
		$ans = $a['ns'];
		$bns = $b['ns'];

		if ($ans > $bns) return 1;
		if ($ans < $bns) return -1;
		return strcmp($a['title'], $b['title']);
	}

	/**
	 * Check an admin password
	 *
	 * @param string $pass Password
	 * @return boolean Is password ok
	 */
	public function checkPassword($pass)
	{
		$curpass = Config::get('wiki.password');
		return ($pass == $curpass);
	}

	/**
	 * Get unapproved queries
	 *
	 * @return array Unapproved queries, keys = id, hash, wikiname
	 */
	public function getUnapproveds()
	{
		$sth = $this->dbh_tools->query('SELECT id, hash, wikiname FROM querys WHERE catcount = ' . QueryCats::CATEGORY_COUNT_UNAPPROVED);
		$sth->setFetchMode(PDO::FETCH_ASSOC);

		$results = array();

		while ($row = $sth->fetch()) {
			$results[] = $row;
		}

		return $results;
	}

	/**
	 * Set a queries status
	 *
	 * @param unknown $hash
	 * @param unknown $status
	 */
	public function setQueryStatus($hash, $status)
	{
		$sth = $this->dbh_tools->prepare('UPDATE querys SET catcount = ? WHERE hash = ?');
		$sth->bindParam(1, $status);
		$sth->bindParam(2, $hash);
		$sth->execute();
	}

	/**
	 * Process template redirect.
	 *
	 * @param string $wikiname
	 * @param string $templatename
	 * @return string Resolved template name
	 */
	public function processTemplateRedirect($wikiname, $templatename)
	{
		static $dbh_wiki = null;
		if (empty($dbh_wiki)) $dbh_wiki = $this->serviceMgr->getDBConnection($wikiname);

		$escapedname = $dbh_wiki->quote($templatename);

		$sql = "SELECT rd_title FROM page, redirect " .
				" WHERE page_namespace = 10 AND page_is_redirect = 1 AND page_title = $escapedname AND page_id = rd_from";

		$results = $dbh_wiki->query($sql);
		$results->setFetchMode(PDO::FETCH_NUM);

		if ($row = $results->fetch()) {
			$templatename = str_replace('_', ' ', $row[0]);
		}

		$results->closeCursor();
		$results = null;

		return $templatename;
	}
}