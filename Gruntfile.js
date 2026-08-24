module.exports = function ( grunt ) {
	var autoprefixer = require( 'autoprefixer' );

	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		/* Autoprefix the source CSS in place. */
		postcss: {
			options: {
				map: false,
				processors: [ autoprefixer( { cascade: false } ) ],
			},
			style: {
				expand: true,
				src: [ 'assets/css/**.css', '!assets/css/**-rtl.css' ],
			},
		},

		/* Generate the -rtl.css counterparts. */
		rtlcss: {
			options: {
				config: {
					preserveComments: true,
					greedy: true,
				},
				map: false,
			},
			dist: {
				files: [
					{
						expand: true,
						cwd: 'assets/css',
						src: [ '*.css', '!*-rtl.css' ],
						dest: 'assets/css/',
						ext: '-rtl.css',
					},
				],
			},
		},

		/* Stage only what ships. */
		copy: {
			main: {
				options: {
					mode: true,
				},
				src: [
					'**',
					'!node_modules/**',
					'!ai-fun-questions/**',
					'!.git/**',
					'!.github/**',
					'!.gitignore',
					'!.gitattributes',
					'!.editorconfig',
					'!.DS_Store',
					'!**/.DS_Store',
					'!Gruntfile.js',
					'!package.json',
					'!package-lock.json',
					'!*.zip',
					'!*.map',
					'!docs/**',
					'!readme.md',
					'!claude.md',
					'!architecture.md',
				],
				dest: 'ai-fun-questions/',
			},
		},

		compress: {
			main: {
				options: {
					archive: 'ai-fun-questions-<%= pkg.version %>.zip',
					mode: 'zip',
				},
				files: [
					{
						src: [ './ai-fun-questions/**' ],
					},
				],
			},
		},

		clean: {
			main: [ 'ai-fun-questions' ],
			zip: [ '*.zip' ],
		},

		/* Version bump: writes the new number into package.json first. */
		bumpup: {
			options: {
				updateProps: {
					pkg: 'package.json',
				},
			},
			file: 'package.json',
		},

		/* ...then propagates it everywhere the version is repeated. */
		replace: {
			plugin_main: {
				src: [ 'ai-fun-questions.php' ],
				overwrite: true,
				replacements: [
					{
						from: /\* Version:\s+.*/g,
						to: '* Version:     <%= pkg.version %>',
					},
				],
			},
			plugin_const: {
				src: [ 'ai-fun-questions.php' ],
				overwrite: true,
				replacements: [
					{
						from: /AI_FQ_VERSION', '.*?'/g,
						to: "AI_FQ_VERSION', '<%= pkg.version %>'",
					},
				],
			},
			stable_tag: {
				src: [ 'readme.txt' ],
				overwrite: true,
				replacements: [
					{
						from: /Stable tag:\ .*/g,
						to: 'Stable tag: <%= pkg.version %>',
					},
				],
			},
		},

		/* Minify JS and CSS. */
		cssmin: {
			options: {
				keepSpecialComments: 0,
			},
			css: {
				files: [
					{
						expand: true,
						cwd: 'assets/css',
						src: [ '*.css' ],
						dest: 'assets/min-css',
						ext: '.min.css',
					},
				],
			},
		},

		uglify: {
			js: {
				options: {
					compress: {
						drop_console: true,
					},
				},
				files: [
					{
						expand: true,
						cwd: 'assets/js',
						src: [ '*.js' ],
						dest: 'assets/min-js',
						ext: '.min.js',
					},
				],
			},
		},
	} );

	// Load grunt tasks
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-postcss' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-bumpup' );
	grunt.loadNpmTasks( 'grunt-text-replace' );

	// Autoprefix
	grunt.registerTask( 'style', [ 'postcss:style' ] );

	// RTL stylesheets
	grunt.registerTask( 'rtl', [ 'rtlcss' ] );

	// Minify everything
	grunt.registerTask( 'minify', [
		'style',
		'rtlcss',
		'cssmin:css',
		'uglify:js',
	] );

	// Build the distributable zip
	grunt.registerTask( 'release', [
		'clean:zip',
		'copy',
		'compress',
		'clean:main',
	] );

	grunt.registerTask( 'release-no-clean', [ 'copy', 'compress' ] );

	// Bump Version - `grunt version-bump --ver=<version-number>`
	grunt.registerTask( 'version-bump', function () {
		var newVersion = grunt.option( 'ver' );

		if ( ! newVersion ) {
			grunt.fail.warn(
				'Pass a version, e.g. grunt version-bump --ver=1.1.0'
			);
			return;
		}

		grunt.task.run( 'bumpup:' + newVersion );
		grunt.task.run( 'replace' );
	} );

	grunt.registerTask( 'default', [ 'minify' ] );
};
