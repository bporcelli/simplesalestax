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
    });

    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-cssmin');

    grunt.registerTask('build', ['uglify', 'cssmin']);
    grunt.registerTask('i18n', ['makepot']);
    grunt.registerTask('default', ['build']);

};
