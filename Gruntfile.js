module.exports = function(grunt) {
    
    // Project configuration.
    grunt.initConfig({
        makepot: {
            target: {
                options: {
                    mainFile: 'simplesalestax.php',  // Main project file.
                    type: 'wp-plugin',               // Type of project (wp-plugin or wp-theme).
                    potHeaders: {
                        'poedit': true,
                        'report-msgid-bugs-to': 'https://github.com/bporcelli/simplesalestax/issues',
                        'language-team': 'TaxCloud <support@taxcloud.net>'
                    }
                }
            }
        }
    });

    // Load the makepot task
    grunt.loadNpmTasks( 'grunt-wp-i18n' );

    // Default task(s).
    grunt.registerTask( 'default', [ 'makepot' ] );

};