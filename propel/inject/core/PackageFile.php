public function fileIsModified()
{
	return $this->getChecksum() !== sha1_file($this->getRealpath());
}

public static function mapFile($filename)
{
	$dirs = array(
		"cms/" => Curry_Core::$config->curry->projectPath . DIRECTORY_SEPARATOR,
		"www/" => Curry_Core::$config->curry->wwwPath . DIRECTORY_SEPARATOR,
	);
	foreach($dirs as $source => $target)
		if(substr($filename, 0, strlen($source)) == $source)
			return $target . substr($filename, strlen($source));
	throw new Exception("Unable to map file '$filename'.");
}

public function getRealpath()
{
	return self::mapFile($this->getFilename());
}

public function backup()
{
	$package = $this->getPackage();
	$filename = $this->getRealpath();
	$backupTarget = $filename.".custom-".$package->getVersion();
	if(file_exists($backupTarget)) {
		$backupTarget .= date("-Ymd_His");
		if(file_exists($backupTarget))
			throw new Exception('Unable to backup file.');
	}
	if(!rename($filename, $backupTarget))
		throw new Exception('Unable to rename file.');
	return $backupTarget;
}