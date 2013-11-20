public function filterByUserAndRole(User $user = null, UserRole $role = null)
{
	return $this
		->_if($user)
			->filterByUser($user)
		->_else()
			->filterByUserId(null, Criteria::ISNULL)
		->_endif()
		->_if($role)
			->filterByUserRole($role)
		->_else()
			->filterByUserRoleId(null, Criteria::ISNULL)
		->_endif();
}

public function orderByCascade()
{
	return $this
		->usePageQuery()
			->orderByBranch()
		->endUse()
		->orderByUserId()
		->orderByUserRoleId();
}