(function( $ ) {
	'use strict';

   $(function() {
		 if (window.H5P && window.H5P.externalDispatcher) {
			 H5P.externalDispatcher.on('xAPI', function (event) {
				 $.ajax({
					 method: 'POST',
					 url: ginf_h5p_rest_object.api_url + 'xapi/statements/',
					 data: {
						 statement: JSON.stringify(event.data.statement)
					 },
					 beforeSend: function ( xhr ) {
						 xhr.setRequestHeader( 'X-WP-Nonce', ginf_h5p_rest_object.api_nonce );
					 }
				 })
				 .done(function(response) {
					 //console.log(JSON.stringify(response));
				 })
				 .fail(function(jqXHR, textStatus, errorThrown) {
					 //console.error(jqXHR, textStatus, errorThrown);
				 });
			 });
		 }
   });
})( jQuery );
