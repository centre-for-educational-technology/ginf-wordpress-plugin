(function( $ ) {
	'use strict';

	var addGraph = function($container, id, type, columns) {
		$('<div>', {
			id: id,
			class: 'graph',
		}).prependTo($container).ready(function() {
			var chart = c3.generate({
				bindto: '#' + id,
				data: {
					columns: columns,
					type: type
				}
			});
		});
	};

	$(function() {
		if (ginfLrsStatistics && ginfLrsStatistics.statements && ginfLrsStatistics.statements.length > 0) {
			addGraph($('.statement-statistics > .graph-container', $(window.document)), 'statement-statistics-graph', 'pie', ginfLrsStatistics.statements.map(function(single) {
				return [single.code, parseInt(single.total)];
			}));
		}

		if (ginfLrsStatistics && ginfLrsStatistics.requests && ginfLrsStatistics.requests.length > 0) {
			addGraph($('.request-statistics > .graph-container', $(window.document)), 'request-statistics-graph', 'bar', ginfLrsStatistics.requests.map(function(single) {
				return [single.code, parseInt(single.total)];
			}));
		}
	});
})( jQuery );
