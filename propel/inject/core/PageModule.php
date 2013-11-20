public function preInsert(PropelPDO $con = null)
{
	if ($this->getUid() === null)
		$this->setUid(Curry_Util::getUniqueId());
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