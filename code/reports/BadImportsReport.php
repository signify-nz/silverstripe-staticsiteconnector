<?php
/**
 * Reports on URLs that failed to import.
 * Data is based off a text file generated by the RewriteLinksTask
 *
 * @author Russell Michell 2013 russell@silverstripe.com
 */
class BadImportsReport extends SS_Report {

	public function title() {
		return "A list of pages containing links to unimported URLs";
	}
	
	/*
	 * @return ArrayList
	 *
	 * @param $params
	 */
	public function SourceRecords($params) {
		return $this->getDataAsSSList();
	}

	/**
	 * Get the columns to show with header titles
	 *
	 * @return array
	 */
	public function columns() {
		return array(
			'Title' => array(
				'title' => 'Title',
				'formatting' => '<a href=\"/admin/pages/edit/show/".$ID."\" title=\"See the page\">$Title</a>'
			),
			'Created' => array(
				'title' => 'Imported',
				'casting' => 'SS_Datetime->Full'
			)
		);
	}
	
	/*
	 * @return mixed boolean|array
	 */
	protected function getBadImportData() {
		$logFile = '/tmp/'.StaticSiteRewriteLinksTask::$failure_log;
		if(!$logFile || !file_exists($logFile)) {
			return false;
		}
		return explode(PHP_EOL,file_get_contents($logFile));
	}

	/*
	 * @return ArrayList
	 */
	protected function getDataAsSSList() {
		$data = $this->getBadImportData();
		$list = new ArrayList();
		if(!$data) {
			return $list;
		}
		foreach($data as $line) {
			if(!strlen($line)>0 || !$processed = $this->processBadImportDataByLine($line)) {
				continue;
			}
			if(!$foundIn = DataObject::get_by_id('SiteTree', (int)$processed['ID'])) {
				continue;
			}
			$list->push($foundIn);
		}
		return $list;
	}

	/*
	 * @param string $line
	 * @return mixed boolean|array $line If an array, it contains the Bad URL and the #ID of the page in which it was found
	 */
	protected function processBadImportDataByLine($line) {
		$badSchemes = implode('|',StaticSiteRewriteLinksTask::$non_http_uri_schemes);
		// Ignore the header found at the top of the logfile
		if(stristr($line, "Couldn't rewrite: ") !== false) {
			$line = str_replace("Couldn't rewrite: ",'',$line);
			if(preg_match("#($badSchemes)#i",$line)) {
				return false;
			}
			$matches = array();
			preg_match_all("#^(\/[a-zA-Z0-9%]+)+\..+\(\#([0-9]+)\)$#",$line,$matches);
			if(!isset($matches[1][0]) && !isset($matches[2][0])) {
				// No matches
				return false;
			}
			return array(
				'BadUrl'	=> $matches[1][0],
				'ID'  => $matches[2][0]
			);
		}
		return false;
	}
}

