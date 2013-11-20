public function conditionIsParent($name, $pages)
{
	$i = 0;
	$conditions = array();
	foreach($pages as $page) {
		$this->addCond("{$name}_{$i}_left", PagePeer::LEFT_COL, $page->getLeftValue(), Criteria::GREATER_THAN);
		$this->addCond("{$name}_{$i}_right", PagePeer::LEFT_COL, $page->getRightValue(), Criteria::LESS_THAN);
		$this->addCond("{$name}_{$i}_level", PagePeer::LEVEL_COL, $page->getLevel() + 1, Criteria::EQUAL);
		$this->combine(array("{$name}_{$i}_left", "{$name}_{$i}_right", "{$name}_{$i}_level"), Criteria::LOGICAL_AND, "{$name}_{$i}");
		$conditions[] = "{$name}_{$i}";
		++$i;
	}
	$this->combine($conditions, Criteria::LOGICAL_OR, $name);
	return $this;
}
