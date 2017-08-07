/**
 * Watch loading of web fonts.
 *
 * Replace this with the actual fonts.
 */
(function(window) {

	var sourceSansObserver = new FontFaceObserver('Source Sans Pro');

	sourceSansObserver.load().then(function() {
		document.documentElement.classList.add('fonts-loaded');
	});

})(this);
