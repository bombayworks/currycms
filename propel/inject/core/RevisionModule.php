public function postSave(PropelPDO $con = null)
{
	PagePeer::changePage();
}

public function postDelete(PropelPDO $con = null)
{
	PagePeer::changePage();
}