module.exports = function (grunt) {

    var pkg = require('./package.json');

    // Project configuration.
    grunt.initConfig({
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

    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-cssmin');

    grunt.registerTask('build', ['uglify', 'cssmin']);
    grunt.registerTask('default', ['build']);

};
