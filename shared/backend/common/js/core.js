(function($) {
	var components = [];
	
	// Register a new component
	$.registerComponent = function(selector, component, requirements) {
		components.push({selector: selector, component: component, requirements: requirements});
	};
	
	// Initialize all components on new content
	$(document).bind('curry-init', function(e) {
		for(var c in components) {
			var cc = components[c];
			var $scope = (typeof cc.selector == 'string' ? $(cc.selector, e.target) : $(e.target));
			if($scope.length) {
				if(typeof cc.component == 'string')
					$scope[cc.component]();
				else if($.isFunction(cc.component))
					cc.component($scope);
			}
		}
		$(e.target).trigger('curry-init-complete');
	});
	
})(jQuery);
