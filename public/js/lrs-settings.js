(function( $ ) {
	'use strict';

   $(function() {
     $('#test-lrs-connection').on('click', function() {
       $.ajax({
         method: 'POST',
         url: ginf_h5p_rest_object.api_url + 'xapi/connection/test',
         data: {
           endpoint: $('#xapi_endpoint').val(),
           key: $('#key').val(),
           secret: $('#secret').val()
         },
         beforeSend: function ( xhr ) {
           xhr.setRequestHeader( 'X-WP-Nonce', ginf_h5p_rest_object.api_nonce );
         }
       })
       .done(function(response) {
         alert('Code: ' + response.response.code + ', message: ' + response.response.message);
       })
       .fail(function(jqXHR, textStatus, errorThrown) {
         //console.error(jqXHR, textStatus, errorThrown);
       });
     });
   });
})( jQuery );
