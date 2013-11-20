<?php
/**
 * Curry CMS
 *
 * LICENSE
 *
 * This source file is subject to the GPL license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://currycms.com/license
 *
 * @category   Curry CMS
 * @package    Curry
 * @copyright  2011-2012 Bombayworks AB (http://bombayworks.se)
 * @license    http://currycms.com/license GPL
 * @link       http://currycms.com
 */

/**
 * Static class used by installation script.
 * 
 * @package Curry
 */
class Curry_Install {
	/**
	 * Show the specified installation step.
	 *
	 * @param string $view
	 */
	public static function show($view)
	{
		ob_start(array(__CLASS__, 'showPage'));
		switch($view) {
			case 'phpinfo':
				ob_end_clean();
				phpinfo();
				exit;

			default:
				echo <<<MESSAGE
<h2>Welcome to the installation of Curry CMS</h2>
<p>This will help you unpack your project bundle.
If you have any questions regarding the installation or Curry CMS please visit <a href="http://currycms.com/" target="_blank">http://currycms.com/</a>.</p>
MESSAGE;
				self::showCompatibility();
				self::showUnpack();
				break;
		}
	}

	/**
	 * Show compatibility table.
	 */
	protected static function showCompatibility()
	{
		echo '<div id="compatibility">';
		echo '<h2>Compatibility</h2>';
		echo '<p>Please make sure your server is compatible with Curry CMS. If you are unsure on any errors contact your server provider or admin. To view a complete phpinfo page [<a href="?step=phpinfo" target="_blank">click here</a>].</p>';
		echo '<table>';
		echo '<tr class="h"><th>Setting</th><th>Local Value</th><th>Expected</th><th>Status</th></tr>';
		
		$error = false;
		$warning = false;
		$pdo = phpversion('pdo') !== FALSE;
		$pdoDrivers = method_exists('PDO', 'getAvailableDrivers') ? PDO::getAvailableDrivers() : array();
		$gd = extension_loaded('gd');
		$timeout = self::getTimeout();

		$error |= self::addCompatibilityRow("PHP Version", phpversion(), ">= 5.3.7", version_compare(phpversion(), '5.3.7', '>='));
		$error |= self::addCompatibilityRow("PDO", ($pdo ? 'Installed' : 'Not installed'), 'Installed', $pdo);
		$error |= self::addCompatibilityRow("PDO Driver", count($pdoDrivers) ? join(", ", $pdoDrivers) : 'No drivers', 'Any', count($pdoDrivers) > 0);
		$error |= self::addCompatibilityRow('Magic Quotes GPC', (get_magic_quotes_gpc() ? 'Enabled' : 'Disabled'), 'Disabled', !get_magic_quotes_gpc());

		$warning |= self::addCompatibilityRow('Safe Mode', (ini_get('safe_mode') ? 'Enabled' : 'Disabled'), 'Disabled (recommended)', !ini_get('safe_mode'), 'Warning');
		$warning |= self::addCompatibilityRow('Register globals', ini_get('register_globals') ? 'Enabled' : 'Disabled', 'Disabled (recommended)', !ini_get('register_globals'), 'Warning');
		$warning |= self::addCompatibilityRow("GD", ($gd ? 'Installed' : 'Not installed'), 'Installed (recommended)', $gd, 'Warning');
		$warning |= self::addCompatibilityRow('Script timeout', $timeout, '>= 30', $timeout >= 30, 'Warning');
		
		echo '</table>';
		if($error)
			echo '<div class="error"><p>There were errors</p></div>';
		else if($warning)
			echo '<div class="error"><p>There were warnings</p></div>';
		echo '</div>';
	}
	
	/**
	 * Show view to unpack bundle.
	 */
	protected static function showUnpack()
	{
		echo '<div id="unpack">';
		echo '<h2>Select bundle</h2>';

		$bundles = glob('*.tar');

		if($bundles === false) {
			if(!is_readable('bundle.tar'))
				echo '<div class="error"><p>Failed to scan directory for bundle files, please name the file bundle.tar for successful detection.</p></div>';
			$bundles = array('bundle.tar');
		}

		if(!count($bundles)) {
			echo '<p>Please upload your bundle file to the same folder as this script (install.php). You should then be able to select your bundle for unpacking if you reload the page.</p>';
		} else if(!isset($_POST['bundle'])) {
			echo '<p>The selected bundle will be unpacked to this directory, no files will be overwritten.';
			echo '<form action="" method="POST">';
			$options = '';
			foreach($bundles as $bundle)
				$options .= '<option value="'.htmlspecialchars($bundle).'">'.htmlspecialchars($bundle).'</option>';
			echo '<select name="bundle">'.$options.'</select><br/>';
			echo '<input class="button unpack-button" type="submit" name="unpack" value="Unpack" /><br/>';
			echo '</form>';
		} else {
			echo "<p>Extracting files...</p>";

			$bundle = $_POST['bundle'];
			if(!in_array($bundle, $bundles)) {
				echo '<div class="error"><p>Invalid bundle name.</p></div>';
				return;
			}

			@set_time_limit(300);
			$symlinkFallback = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || !function_exists('symlink');
			$symlinks = array();
			$tar = new Curry_Archive($bundle);
			$tar->extract(array(
				array(
					// Collect and skip symlinks if we're using the symlink fallback
					'callback' => function($file, $options) use (&$symlinks, $symlinkFallback) {
						if($file->isLink() && $symlinkFallback) {
							$symlinks[] = $file;
							return false;
						}
						return true;
					},
					// Print warning for existing files
					'overwrite' => function($file, $options) {
						echo "Warning, file {$file->getPathname()} already exists, skipping<br />";
						return false;
					},
				),
				array(
					'path' => 'www/',
					'target' => './',
				),
			));
			self::fixSymlinks($symlinks);
			echo '<div class="success"><p>Bundle unpacked successfully.</p></div>';

			$success = self::writeInit();
			$success = self::writeConfig() && $success;
			if($success) {
				echo '<div class="success"><p>Curry configuration written.</p></div>';
			}
			echo '<p><a href="admin.php">Continue setup</a>.</p>';
		}
		echo '</div>';
	}

	/**
	 * Replace symlinks with their real target.
	 *
	 * @param array $symlinks
	 */
	protected static function fixSymlinks($symlinks)
	{
		foreach($symlinks as $symlink) {
			$target = $symlink->getTarget();
			$linkTarget = $symlink->getLinkTarget();
			$source = dirname($target).'/'.$linkTarget;
			echo "Fixing symlink $source => $target<br />";
			rename($source, $target);
		}
	}

	/**
	 * Update init.php with paths to curry core and config.php.
	 *
	 * @return bool
	 */
	protected static function writeInit()
	{
		$corePath = "dirname(__FILE__) . '/curry/include/Curry/Core.php'";
		$configPath = "dirname(__FILE__) . '/cms/config/config.php'";

		$initFile = 'init.php';
		$initContent = file_get_contents($initFile);
		$initContent = preg_replace(
			array(
				'@^\s*require_once (.*Core\.php.*);\s*$@m',
				'@^\s*Curry_Core::init\((.*)\);\s*$@m',
			),
			array(
				'require_once '.$corePath.';',
				'Curry_Core::init('.$configPath.', $timeStart);',
			),
			$initContent
		);

		if(!@file_put_contents($initFile, $initContent)) {
			echo '<div class="error"><p>Failed to write init.php, please make sure this is the content of init.php:</p>';
			echo '<pre>'.htmlspecialchars($initContent).'</pre></div>';
			return false;
		}
		return true;
	}

	/**
	 * Update config.php and enable setup flag.
	 *
	 * @return bool
	 */
	protected static function writeConfig()
	{
		$configPath = 'cms/config/config.php';
		$config = require $configPath;
		$config['curry']['setup'] = true;
		$configContent = "<?php\nreturn ".var_export($config, true).";";

		if(!@file_put_contents($configPath, $configContent)) {
			echo '<div class="error"><p>Failed to write configuration, please make sure this is the content of '.$configPath.':</p>';
			echo '<pre>'.htmlspecialchars($configContent).'</pre></div>';
			return false;
		}
		return true;
	}

	/**
	 * Wrap $content in html document.
	 *
	 * @param $content
	 * @return string
	 */
	public static function showPage($content)
	{
		$template = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <title>Curry Installation</title>
  <meta name="ROBOTS" content="NOINDEX,NOFOLLOW,NOARCHIVE" />
  <style type="text/css">
  {{INSTALL_CSS}}
  </style>
</head>
<body>
  <div id="curry-panel">
    <div class="container">
      <h1><a href="install.php">Curry CMS</a></h1>
    </div>
  </div>
  <div id="content" class="container">
    {{CONTENT}}
  </div>
  <div id="footer">
    <div class="container"></div>
  </div>
</body>
</html>
HTML;
		return str_replace('{{CONTENT}}', $content, $template);
	}

	/**
	 * Add a row that shows Compatibility.
	 *
	 * @param string $setting
	 * @param string $value
	 * @param bool $required
	 * @param string $status
	 * @param string $errorString
	 * @return bool Inverted status
	 */
	protected static function addCompatibilityRow($setting, $value, $required, $status, $errorString = 'Error')
	{
		$s = $status;
		if(!is_string($status))
			$status = ($status ? 'Ok' : $errorString);
		echo '<tr><td class="e">'.htmlspecialchars($setting).'</td><td class="v">'.htmlspecialchars($value).'</td><td class="v">'.htmlspecialchars($required).'</td><td class="'.($s?'v':'w').'">'.$status.'</td></tr>';
		return !$s;
	}

	/**
	 * Try to figure out the maximum execution time.
	 *
	 * @return integer|bool
	 */
	protected static function getTimeout()
	{
		$phptime = ini_get('max_execution_time');
		if($phptime == 0)
			$phptime = false;

		ob_start();
		phpinfo();
		$phpinfo = ob_get_clean();
		$apachetime = preg_match('/Connection: (\d+)/', $phpinfo, $m) ? $m[1] : false;

		if($phptime !== false && $apachetime !== false)
			return min($phptime, $apachetime);
		else if($phptime !== false)
			return $phptime;
		else if($apachetime !== false)
			return $apachetime;
		return false;
	}
}
