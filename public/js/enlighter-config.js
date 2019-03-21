(function( $ ) {
	'use strict';

   $(function() {
		 if (window.H5PIntegration) {
			 window.H5PIntegration.EnlighterJS_Config = window.EnlighterJS_Config ? window.EnlighterJS : window.GINF_EnlighterJS_Config;
		 }
   });
})( jQuery );
