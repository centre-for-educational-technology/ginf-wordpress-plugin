(function( $ ) {
	'use strict';

   $(function() {
			 H5P.externalDispatcher.on('xAPI', function (event) {
				 $.ajax({
					 method: 'POST',
					 url: H5PIntegration.baseUrl + '/wp-json/ginf/v1/xapi/statements/',
					 data: {
						 statement: JSON.stringify(event.data.statement)
					 },
					 beforeSend: function ( xhr ) {
						 xhr.setRequestHeader( 'X-WP-Nonce', $(document).find('meta[name="api_nonce"]').attr('content') );
					 }
				 })
				 .done(function(response) {
					 //console.log(JSON.stringify(response));
				 })
				 .fail(function(jqXHR, textStatus, errorThrown) {
					 //console.error(jqXHR, textStatus, errorThrown);
				 });
			 });
   });
})( H5P.jQuery );
