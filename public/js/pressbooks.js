(function( $ ) {
	'use strict';

   $(function() {
		 // Make sure that book side menu is always open, provided one is present
		 if ($('.reading-header__toc.dropdown > h3').length > 0) {
			 var intervalId = setInterval(function() {
				 var button = $('.reading-header__toc.dropdown > h3 > button');
				 if (button.length > 0) {
					 clearInterval(intervalId);
					 button.trigger('click');
				 }
			 }, 50);
		 }
   });
})( jQuery );
