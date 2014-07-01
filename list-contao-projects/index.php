<?php

/*
I have a test/development system where many Contao projects are installed.
Bookmarks tend to become incomplete, outdated and do not tell me which
Contao version is used in which project and which database instance is used.
So I wrote this script which gives me a live overview over the projects
and, additionally, comfortable links to the FE and BE of each.

The script assumes the following structure:
	DOCUMENT_ROOT/cto/				# base dir for all projects, can be null
	                 /project1/
					 /project2/
					 /core-3.2.12/	# an unpacked yet not installed version
					 /(etc)
					 /index.php		# this script

	http://localhost/cto then shows teh overview

For each sub-directory which looks like a Contao source, the script will display
some or all of the following information
- name of the project ( = sub-directory name) with a link to the start page
- Contao version (for 3.x only)
- Link to backend login
- database name
- Website title
- list of installed extensions (as per system/modules/*) except some std ones
Some data and links can only be displayed if install.php has been executed
(obviously). Data is extraced from localconfig.php and others.

Various things are easily configurable in this script.
*/

/**
 * Class LocalContaoInstalls
 *
 * Outputs a webpage (or section) with a list of all
 * local Contao installations (in current directory).
 * @copyright	Thomas Pahl	2013 - 2014
 * @author		Thomas Pahl
 * @license		LGPL
 * @package		private Contao tools
 *
 * 
 * 
 */

class LocalContaoInstalls
{

	/**
	 * config: open links in new window?
	 */
	public $openNewWindow = false;

	/**
	 * config: list extensions also
	 */
	public $optionExtensions = true;

	/**
	 * config: HTML page wrap
	 */
	public $htmlHeader = <<<EOD
<!DOCTYPE html>
<html>
<head>
<title>Lokale Contao (Test-)Installationen</title>
<style>
#listinst table {
	margin-left:2em;
	border-collapse:collapse;
}
#listinst th, #listinst td {
	border:1px solid #ccc;
	padding:4px;
}
#listinst th {
	background-color:#ddd;
}
#listinst td.ext {
	font-family:Arial,sans-serif;
	font-size:small;
}
</style>
</head>
<body>
EOD;
	public $htmlFooter = <<<EOD
</table>
</body>
</html>
EOD;

	#public $column_headers = array()

	/**
	 * standard modules/extensions that should not be listed in the extension column
	 * (backend, frontend, ... are Contao 2 only)
	 */
	public $stdmodules = array('core', 'calendar', 'comments', 'devtools', 'faq', 'listing', 'news', 'newsletter', 'repository');
	public $stdmodules2 = array('backend', 'frontend', 'registration', 'rep_base', 'rep_client', 'rss_reader', 'tpl_editor');


	/* NO USER SERVICEABLE CONSTANTS AFTER THIS POINT */

	/**
	 * array of detected Contao installations
	 */
	protected $systems = array();


	/**
	 * Find all Contao installations in current directory (must be 
	 * direct subdiretories) and extract some information.
	 */
	function find_systems()
	{
		$root = preg_replace("!^.*/htdocs/!", '', getcwd()) . '/';
		$root = dirname($_SERVER['SCRIPT_NAME']);
		$root = $root == '/' ? '' : $root;
		$dir = new DirectoryIterator('.');
		$this->systems = array();

		foreach ($dir as $fileinfo) {
			if (!$fileinfo->isDot() && $fileinfo->isDir()) {
				// collect essential information about this (possible) installation
				$path = $fileinfo->getPathName();
				$path = preg_replace('!^\.\/!', '', $path);
				$name = preg_replace('!/.*$!', '', $path);
				##echo "$name $path<br>\n";

				$configpath = $path.'/system/config/';
				// check for a file present in C2 and C3
				if (file_exists($configpath.'countries.php')) {
					##echo "..config found<br>\n";
					$this->systems[$name] = array();
					$this->systems[$name]['name'] = $name;
					$this->systems[$name]['webpath'] = $root.$path;
					$localconfig = $path.'/system/config/localconfig.php';
					if (file_exists($localconfig)) {
						##echo "$name $localconfig\n";
						$this->systems[$name]['localconfig'] = true;
						$this->eval_config($name, $localconfig);
					}
					$this->eval_const($name, $configpath.'constants.php');
					$this->systems[$name]['extensions'] = $this->list_extensions($fileinfo->getPathname(), $this->systems[$name]['version']);
				}
			}
		}
		ksort($this->systems);
		##var_dump($this->systems);
	}

	/**
	 * List extensions (system/modules/* actually) in an installation
	 */
	function list_extensions($path, $version='3')
	{
		$extensions = array();
		if (!$this->optionExtensions) {
			return $extensions;
		}
		try {
			$dir = new DirectoryIterator($path . '/system/modules');
		}
		catch (Exception $e) {
			echo "$path: no ext<br>";
			return $extensions;
		}


		foreach ($dir as $fileinfo) {
			if (!$fileinfo->isDot() && $fileinfo->isDir()) {
				$filename = $fileinfo->getFilename();
				if (!in_array($filename, $this->stdmodules)) {
					$extensions[] = $filename;
				}
			}
		}
		if (empty($version) or substr($version, 0 , 1) != '3') {
			// probably a Contao 2
			$extensions = array_diff($extensions, $this->stdmodules2);
		}
		natcasesort($extensions);
		return $extensions;
	}

	/**
	* Read the constants.php file and grab some values
	*/
	function eval_const($name, $file)
	{
		$version = $build = $match = false;
		$fp = fopen($file, 'r');
		if ($fp) {
			$count = 2;
			while ($count && ($line = fgets($fp)) !== false) {
				if (preg_match("!^define[^']*'([A-Z]+)'[^']*'([A-Z0-9.]+)'!", $line, $match)) {
					##var_dump($match);
					switch ($match[1]) {
						case 'VERSION':
							$version = $match[2];
							--$count;
							break;
						case 'BUILD':
							$build = $match[2];
							--$count;
							break;
					}
				}
			}
		}
		##echo "version=$version.$build\n";
		$this->systems[$name]['version'] = $version;
		$this->systems[$name]['build'] = $build;
	}

	/**
	* Read the localconfig.php file and grab some values
	*/
	function eval_config($name, $file)
	{
		$database = $build = $match = false;
		$fp = fopen($file, 'r');
		if ($fp) {
			$count = 2;
			while ($count && ($line = fgets($fp)) !== false) {
				if (preg_match("!^.GLOBALS..TL_CONFIG..[^']*'([^']+)'[^']*'([^']+)'!", $line, $match)) {
					##var_dump($match);
					switch ($match[1]) {
						case 'dbDatabase':
							$this->systems[$name]['database'] = $match[2];
							--$count;
							break;
						case 'websiteTitle':
							$this->systems[$name]['websitetitle'] = $match[2];
							--$count;
							break;
					}
				}
			}
		}
	}


	/**
	 * output the table with information and links
	 */
	function generate() {
		$this->find_systems();
		
		$out = <<<EOD
<section id="listinst">
<h2>Contao Installationen auf {{server}}</h2>
<table><tr><th>Name</th><th>Contao Version</th><th>Admin Link</th><th>Database Name</th><th>Website Name</th><th>Extensions</th></tr>
EOD;
		$out = str_replace('{{server}}', $_SERVER['SERVER_NAME'], $out);

		foreach ($this->systems as $system) {
			$out .= "<tr><td>";
			if (empty($system['database'])) {
				$out .= sprintf('%s</td><td>%s</td><td></td><td></td><td></td><td>', $system['name'], $system['version'].'.'.$system['build']);
			}
			else {
				$tmp = sprintf('<a href="%s/">%s</a></td><td>%s</td><td><a href="%s/contao/">Admin</a></td><td>%s</td><td>%s</td><td class="ext">%s', $system['name'], $system['name'], $system['version'].'.'.$system['build'], $system['name'], $system['database'], $system['websitetitle'], implode(', ', $system['extensions']));
				if ($this->openNewWindow) {
					$tmp = str_replace('<a href=', '<a target="_blank" href=', $tmp);
				}
				$out .= $tmp;
			}
			$out .= "</td></tr>\n";
		}
		$out .= "</section>\n";
		return $out;
	}

	/**
	 * run - output self-contained web page
	 */
	function run()
	{
		echo $this->htmlHeader;
		echo $this->generate();
		##var_dump($_SERVER);
		echo $this->htmlFooter;
	}

}

/*
 * Delete the following lines if you want to embed the output into your page/script
 * or sub-class it for configuration.
 * Call generate() then.
 */
error_reporting(E_ALL ^E_NOTICE);

$x = new LocalContaoInstalls();
$x->run();
