(function( $ ) {
	'use strict';

   $(function() {
       // A cleaner solution would be to use the lookahead and lookbehind, yet support is not universal
       // var found = H5PIntegration.ajax.setFinished.match(/(?<=token=)(.*)(?=&action=)/ig);
       var matches = /token=(.*?)&action=/ig.exec(H5PIntegration.ajax.setFinished);

			 H5P.externalDispatcher.on('xAPI', function (event) {
				 $.ajax({
					 method: 'POST',
					 url: H5PIntegration.baseUrl + '/wp-json/ginf/v1/xapi/statements/',
					 data: {
						 statement: event.data.statement
					 },
					 beforeSend: function ( xhr ) {
						 xhr.setRequestHeader( 'X-H5P-Nonce', (matches && matches.length === 2) ? matches[1] : '' );
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
