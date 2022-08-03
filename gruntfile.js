module.exports = function (grunt) {

    var pkg = require('./package.json');

    // Project configuration.
    grunt.initConfig({
        makepot: {
            target: {
                options: {
                    mainFile: 'simple-sales-tax.php',  // Main project file.
                    type: 'wp-plugin',               // Type of project (wp-plugin or wp-theme).
                    exclude: [
                        'node_modules/.*',
                        'includes/vendor/.*',
                        'build/.*'
                    ],
                    potHeaders: {
                        'poedit': true,
                        'report-msgid-bugs-to': 'https://github.com/bporcelli/simplesalestax/issues',
                        'language-team': 'Brett Porcelli <bporcelli@taxcloud.com>'
                    }
                }
            }
        },
        uglify: {
            target: {
                files: [{
                    expand: true,
                    cwd: 'assets/js',
                    src: ['*.js', '!*.min.js'],
                    dest: 'assets/js',
                    rename: function (dst, src) {
                        return dst + '/' + src.replace('.js', '.min.js')
                    }
                }]
            }
        },
        cssmin: {
            target: {
                files: [{
                    expand: true,
                    cwd: 'assets/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'assets/css',
                    ext: '.min.css'
                }]
            }
        },
        clean: ['build/'],
        copy: {
            target: {
                expand: true,
                src: ['assets/**', 'includes/**', 'languages/**', 'simple-sales-tax.php', 'uninstall.php', 'readme.txt', 'changelog.txt'],
                dest: 'build/'
            }
        },
        compress: {
            target: {
                options: {
                    archive: function () {
                        return 'releases/simple-sales-tax-' + pkg.version + '.zip'
                    }
                },
                files: [{
                    expand: true,
                    cwd: 'build/',
                    src: '**',
                    dest: 'simple-sales-tax/'
                }]
            }
        },
    });

    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('assets', ['uglify', 'cssmin']);
    grunt.registerTask('build', ['makepot', 'assets', 'clean', 'copy', 'compress']);
    grunt.registerTask('default', ['build']);

};
