module.exports = {
	dist: {
		files: {
			'assets/css/cambridge-coaching.src.css': 'assets/css/sass/cambridge-coaching.scss',
			'assets/css/editor-styles.css': 'assets/css/sass/editor-styles.scss',
		},
		options: {
			imagePath:   'assets/img',
			outputStyle: 'nested',
			sourceMap:   false
		},
	},
	minDist: {
		files: {
			'assets/css/cambridge-coaching.min.css': 'assets/css/sass/cambridge-coaching.scss',
		},
		options: {
			imagePath:   'assets/images',
			outputStyle: 'compressed',
			sourceMap:   false
		},
	}
}
