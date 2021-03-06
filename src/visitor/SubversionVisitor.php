<?php

class SubversionVisitor extends AbstractVersioningVisitor {
	private $svnCommand = "/usr/bin/env svn";
	private $mode;
	private $path;

	private $xml = null;

	protected $min_svn = "1.4.0";

	const SVN_LIST = 0;
	const SVN_INFO = 1;
	const SVN_LOG = 2;

	public function __construct($path = null, $mode = self::SVN_INFO) {
		assert ( ! ($mode === self::SVN_LIST && $path === null) );
		assert ( $mode >= self::SVN_LIST && $mode <= self::SVN_LOG );
		$this->mode = $mode;
		$this->path = $path;

		if ($this->execSvnVersion () < $this->min_svn) {
			throw new Exception("Please use Subversion version $this->min_svn or higher");
		}

		if($mode === self::SVN_LIST) {
			$this->execSvnList ();
		}
	}

	private function execSvnList() {
		if (file_exists ( $this->path )) {
			if ($this->xml === null) {
				$this->xml = array();
				$data = `$this->svnCommand list -R --xml $this->path`;
				echo "Loaded all Subversion information from disk\n";
				$xml = new SimpleXMLElement ( $data );
				foreach ( $xml->list->entry as $entry ) {
					$index = $entry->name->__toString();
					$this->xml[$index] = $entry;
				}

			}
		}
		return $this->xml;
	}

	private function processExecSvnListResult($path) {
		$commits = array ();

		$len = strlen($this->path);
		if($this->path[$len-1] !== DIRECTORY_SEPARATOR) {
			$len++;
		}

		$path = substr($path, $len);

		if (isset($this->xml[$path])) {
			$entry = $this->xml[$path];
			$id = $entry->attributes('revision')->__toString();
			$author = $entry->commit->author->__toString();
			//<date>2010-11-24T10:21:22.052561Z</date>
			$date = DateTime::createFromFormat("Y-m-d?H:i:s.ue",$entry->commit->date->__toString());
			$message = "Not available in SVN_LIST mode";
			$commits[] = new Commit($id, $author, $date, $message);
		}

		return $commits;
	}

	private function execSvnVersion() {
		$result = `$this->svnCommand --version`;
		$return = "";

		preg_match ( "/^svn, version ([0-9]+.[0-9]+.[0-9]+) /", $result, $matches );
		if (isset ( $matches [1] )) {
			$return = $matches [1];
		}
		return $return;
	}

	private function execSvnInfo($path) {
		$command = sprintf ( "%s info %s 2>&1", $this->svnCommand, $path );
		$result = `$command`;
		return $result;
	}

	private function execSvnLog($path, $max = 1) {
		$command = sprintf ( "%s log %s -l %d 2>&1", $this->svnCommand, $path, $max );
		$result = `$command`;
		return $result;
	}

	private function processExecSvnInfoResult($result) {
		$commits = array ();
		preg_match_all ( '/\nLast Changed Author: (.+)\nLast Changed Rev: (.+)\nLast Changed Date: (.+)\(/', $result, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$id = $match [2];
			$author = $match [1];
			// 2012-01-16 09:21:51 +0100 (ma, 16 jan 2012)
			$date = new DateTime ( $match [3] );
			$message = "not available in SVN_INFO mode";
			$commit = new Commit ( $id, $author, $date, $message );
			$commits [] = $commit;
		}

		return $commits;
	}

	private function processExecSvnLogResult($result) {
		$commits = array ();
		preg_match_all ( '/r([0-9]+) \| (.+) \| (.+) \(.+\n\n(.*)\n------/msU', $result, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$id = $match [1];
			$author = $match [2];
			// 2012-01-16 09:21:51 +0100 (ma, 16 jan 2012)
			$date = DateTime::createFromFormat ( "Y-m-d H:m:s O", $match [3] );
			$message = $match [4];
			$commit = new Commit ( $id, $author, $date, $message );
			$commits [] = $commit;
		}

		return $commits;
	}

	protected function getCommits($path) {
		$commits = array ();
			switch ($this->mode) {
				case self::SVN_INFO :
					$result = $this->execSvnInfo ( $path );
					$commits = $this->processExecSvnInfoResult ( $result );
					break;
				case self::SVN_LOG :
					$result = $this->execLogSvn ( $path );
					$commits = $this->processExecSvnLogResult ( $result );
					break;
				case self::SVN_LIST :
					$commits = $this->processExecSvnListResult ( $path );
					break;
			}
		return $commits;
	}
}

?>