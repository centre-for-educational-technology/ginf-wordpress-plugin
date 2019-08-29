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

		 // Add logos to boom front page
		 if ($('.home.page .block-meta__content-box').length > 0) {
			 var logoData = [
				 {
					 image: 'el-programm-logo.jpg',
					 alt: 'el-programm-logo',
					 style: 'max-width: 250px;'
				 },
				 {
					 image: 'hitsa-logo.jpg',
					 alt: 'hitsa-logo',
					 style: 'max-width: 300px;'
				 },
				 {
					 image: 'tiger-logo.jpg',
					 alt: 'tiger-logo',
					 style: 'max-width: 200px;'
				 }
			 ];
			 var logosElem = $('<div>', {
				 class: 'ginf-book-logos',
				 style: 'text-align: center;'
			 });
			 $.each(logoData, function(index, logo) {
				 $('<img>', {
					 src: ginf_config_object.images_url + logo.image,
					 alt: logo.alt,
					 style: logo.style
				 }).appendTo(logosElem);
			 });

			 logosElem.appendTo($('.home.page .block-meta__content-box').get(0))
		 }
   });
})( jQuery );
