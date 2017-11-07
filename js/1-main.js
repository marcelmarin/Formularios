jQuery(function($){
		
	$(document).ready(function() {
		
		var $window = $(window),
		$body = $('body');		
		
		$window.scroll(function(){
			$('#site-header-menu').removeClass('toggled-on');
		});
		
		$('#site-header-menu').scroll(function() {
			 $('#site-header-menu').addClass('toggled-on');
		});
		
	});
});
