(function( $ ) {
  'use strict';

  $(function() {
    if (window.H5PIntegration) {
      var $meta = $(document).find('meta[name="ginf_enlighter_config"]');
      if ($meta && $meta.length > 0) {
        window.H5PIntegration.EnlighterJS_Config = JSON.parse( atob( $meta.attr('content') ) );
      }
    }
  });
})( H5P.jQuery );
