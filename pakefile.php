<?php
/**
 * eZPublishBuilder pakefile:
 * a script to build & package the eZ Publish Community Project.
 *
 * Needs the Pake tool to run: https://github.com/indeyets/pake/wiki
 *
 * It should be installed from the web via composer
 *
 * The steps involved in the build process are described here:
 * https://docs.google.com/a/ez.no/document/d/1h5n3aZdXbyo9_iJoDjoDs9a6GdFZ2G-db9ToK7J1Gck/edit?hl=en_GB
 *
 * @author    G. Giunta
 * @author    N. Pastorino
 * @copyright (C) eZ Systems AS 2011-2013
 * @license   code licensed under the GNU GPL 2.0: see README file
 */

// We allow this script to be used both
// 1. by having it in the current directory and invoking pake: pake --tasks
// 2. using direct invocation: ezextbuilder --tasks
// The second form is in fact preferred. It works also when pakefile.php is not in the current dir,
// such as when installed via composer (this is also possible using pake invocation using the -f switch)
if ( !function_exists( 'pake_task' ) )
{
    require( __DIR__ . '/pakefile_bootstrap.php' );
}
else
{

// this is unfortunately a necessary hack: ideally we would always check for
// proper pake version, but version 0.1 of this extension was
// shipped with a faulty pake_version, so we cannot check for required version
// when using the bundled pake.
// To aggravate things, version 0.1 did not upgrade the bundled pake when
// upgrading to a new script, so we can not be sure that, even if the end user
// updates to a newer pakefile, the bundled pake will be upgraded
// (it will only be when the user does two consecutive updates)
// Last but not least, when using a pake version installed via composer, that
// also does not come with proper version tag...
if ( !( isset( $GLOBALS['internal_pake'] ) && $GLOBALS['internal_pake'] ) )
{
    pake_require_version( eZPCPBuilder\Builder::MIN_PAKE_VERSION );
}

// this should not be strictly needed, but it does not hurt
if ( strtoupper( substr( PHP_OS, 0, 3) ) === 'WIN' )
{
    pakeGit::$needs_work_tree_workaround = true;
}

// *** declaration of the pake tasks ***

// NB: up to pake 1.99.1 this will not work
//pake_task( 'eZExtBuilder\GenericTasks::default' );
function run_default( $task=null, $args=array(), $cliopts=array() )
{
    eZPCPBuilder\Tasks::run_default( $task, $args, $cliopts );
}

pake_task( 'default' );

pake_task( 'eZPCPBuilder\Tasks::show-properties' );

pake_task( 'eZPCPBuilder\Tasks::init' );

pake_task( 'eZPCPBuilder\Tasks::init-ci-repo' );

pake_task( 'eZPCPBuilder\Tasks::build',
    'init', 'init-ci-repo', 'generate-upgrade-instructions', 'generate-changelog', 'wait-for-changelog', 'update-ci-repo', 'wait-for-continue', 'run-jenkins-build4', 'run-jenkins-build5' );

pake_task( 'eZPCPBuilder\Tasks::update-source' );

pake_task( 'eZPCPBuilder\Tasks::display-source-revision' );

pake_task( 'eZPCPBuilder\Tasks::display-previous-release' );

pake_task( 'eZPCPBuilder\Tasks::generate-upgrade-instructions',
   'update-source' );

pake_task( 'eZPCPBuilder\Tasks::generate-changelog',
    'update-source' );

pake_task( 'eZPCPBuilder\Tasks::wait-for-changelog' );

pake_task( 'eZPCPBuilder\Tasks::update-ci-repo-source' );

pake_task( 'eZPCPBuilder\Tasks::update-ci-repo',
    'update-ci-repo-source' );

pake_task( 'eZPCPBuilder\Tasks::wait-for-continue' );

pake_task( 'eZPCPBuilder\Tasks::run-jenkins-build4' );

pake_task( 'eZPCPBuilder\Tasks::check-jenkins-build5pre' );

pake_task( 'eZPCPBuilder\Tasks::run-jenkins-build5pre' );

pake_task( 'eZPCPBuilder\Tasks::run-jenkins-build5',
    'check-jenkins-build5pre' );

pake_task( 'eZPCPBuilder\Tasks::tag-github-repos' );

pake_task( 'eZPCPBuilder\Tasks::tag-jenkins-builds' );

pake_task( 'eZPCPBuilder\Tasks::generate-html-changelog' );

pake_task( 'eZPCPBuilder\Tasks::generate-html-credits' );

pake_task( 'eZPCPBuilder\Tasks::update-version-history' );

pake_task( 'eZPCPBuilder\Tasks::generate-apidocs-LS' );

pake_task( 'eZPCPBuilder\Tasks::generate-apidocs-NS' );

pake_task( 'eZPCPBuilder\Tasks::generate-apidocs-4X' );

pake_task( 'eZPCPBuilder\Tasks::dist-init' );

pake_task( 'eZPCPBuilder\MSWPITasks::dist-wpi' );

pake_task( 'eZPCPBuilder\Tasks::dist',
    'dist-init', 'generate-apidocs-LS', 'generate-apidocs-NS' );

pake_task( 'eZPCPBuilder\Tasks::release',
    'generate-html-changelog', 'generate-html-credits', 'update-share', 'update-version-history', 'upload-apidocs' );

pake_task( 'eZPCPBuilder\Tasks::all',
    'build', 'dist', 'release' );

pake_task( 'eZPCPBuilder\Tasks::clean' );

pake_task( 'eZPCPBuilder\Tasks::clean-ci-repo' );

pake_task( 'eZPCPBuilder\Tasks::dist-clean' );

pake_task( 'eZPCPBuilder\Tasks::clean-all',
    'clean', 'dist-clean' );

}
