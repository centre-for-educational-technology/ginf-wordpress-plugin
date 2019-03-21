(function( $ ) {
	'use strict';

	$(function() {
		if (window.H5PIntegration && window.EnlighterJS_Config) {
			window.H5PIntegration.EnlighterJS_Config = window.EnlighterJS_Config;
		}

		function enlighter_init() {
			if (window.H5PIntegration && window.H5PIntegration.EnlighterJS_Config) {
				var nothidden = ':not([style*="display: none"])';

				EnlighterJS.Util.Init(H5PIntegration.EnlighterJS_Config.selector.block + nothidden, H5PIntegration.EnlighterJS_Config.selector.inline + nothidden, H5PIntegration.EnlighterJS_Config);
				setTimeout(function() {
					if (H5P.instances && H5P.instances.length > 0) {
						H5P.instances.forEach(function(instance) {
							H5P.trigger(H5P.instances[0], 'resize');
						});
					}
				}, 100);
			}
		}

		function add_enlighter_trigger(selector) {
			var elements = document.querySelectorAll(selector);

			if (elements && elements.length > 0) {
				elements.forEach(function(element) {
					element.removeEventListener('click', enlighter_init);
					element.addEventListener('click', enlighter_init);
				});
			}
		}

		function add_all_enlighter_triggers() {
			var selectors = ['nav ol li a', '.h5p-image-hotspot', '.joubel-tip-container', '.h5p-element-button', '.h5p-interaction-button', '.h5p-nav-button'];
			selectors.forEach(function(selector) {
				add_enlighter_trigger(selector);
			});
		}

		H5P.externalDispatcher.on('xAPI', function (event) {
			if (event.data.statement.verb &&
				event.data.statement.verb.id &&
				event.data.statement.context &&
				event.data.statement.context.contextActivities &&
				event.data.statement.context.contextActivities.category) {
				if (event.data.statement.verb.id === "http://adlnet.gov/expapi/verbs/progressed" && event.data.statement.context.contextActivities.category[0].id.indexOf("H5P.CoursePresentation") != -1) {
					enlighter_init();
					add_all_enlighter_triggers(); // Add all triggers again to new slide
				} else if(event.data.statement.verb.id === "http://adlnet.gov/expapi/verbs/answered" && event.data.statement.context.contextActivities.category[0].id.indexOf("H5P.MultiChoice") != -1){
					add_enlighter_trigger(".h5p-question-try-again"); // Retry button in MultiChoice
				}
			}
		});

		enlighter_init();
	});
})( H5P.jQuery );
