module.exports = {
	options: {
		stripBanners: true,
		sourceMap: true
	},
	dist: {
		src: [
			// JS Check is first priority
			'assets/js/src/jsCheck.js',

			// Vendor libraries
			'node_modules/fontfaceobserver/fontfaceobserver.standalone.js',

			// Other JS functionality
			'assets/js/src/loadFonts.js',
			'assets/js/src/enableMenuToggle.js'
		],
		dest: 'assets/js/dist/bundle.src.js'
	},
}
