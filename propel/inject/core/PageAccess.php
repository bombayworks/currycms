public static function getPermissionTypes()
{
	$types = array();
	foreach(PageAccessPeer::getFieldNames(BasePeer::TYPE_PHPNAME) as $phpName) {
		if(substr($phpName, 0, 4) == 'Perm') {
			$columnName = PageAccessPeer::translateFieldName($phpName, BasePeer::TYPE_PHPNAME, BasePeer::TYPE_COLNAME);
			$types[$columnName] = $phpName;
		}
	}
	return $types;
}

public function getPermissions()
{
	$p = array();
	foreach(self::getPermissionTypes() as $colName => $phpName) {
		if($this->{'get'.$phpName}() !== null)
			$p[$colName] = $this->{'get'.$phpName}();
	}
	return $p;
}