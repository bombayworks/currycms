public function preInsert(PropelPDO $con = null)
{
	if ($this->getUid() === null)
		$this->setUid(\Curry\Util\Helper::getUniqueId());
	return true;
}

public function postSave(PropelPDO $con = null)
{
	PagePeer::changePage();
}

public function postDelete(PropelPDO $con = null)
{
	PagePeer::changePage();
}