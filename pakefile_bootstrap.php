<?php
/**
 * eZPublishBuilder pakefile bootstrapper.
 *
 * This code allows the original pakefile.php to be invoked standalone, which helps with it being stored in a separate dir
 * from the build dir (which is also supported by native pake but buggy at least up to pake 1.7.4).
 * It is messy by nature :-)
 *
 * @author    G. Giunta
 * @copyright (C) eZ Systems AS 2011-2013
 * @license   code licensed under the GNU GPL 2.0: see README file
 */

if ( class_exists( 'pakeApp' ) )
{
    // This should not happen, but just in case this file is included after pake is already loaded
    if ( !isset( $GLOBALS['internal_pake'] ) )
    {
        $GLOBALS['internal_pake'] = false;
    }
}
else
{
    // If installed via composer, set up composer autoloading
    if ( file_exists( __DIR__ . '/vendor/autoload.php' ) )
    {
        include_once(  __DIR__ . '/vendor/autoload.php' );
    }

    // We look if pake is found in
    // - the folder where composer installs it (assuming this is also installed by composer) - taken care by code above
    // - the folder where composer installs it (assuming this is is the root project) - taken care by code above
    // - the folder where this script used to install it (before composer  usage, versions up to 0.4): ./pake/src
    if ( !class_exists( 'pakeApp' ) && ( file_exists( 'pake/src/bin/pake.php' ) /*&& $pakesrc = 'pake/src/bin/pake.php' ) ||
        ( file_exists( __DIR__ . '/../../indeyets/pake/bin/pake.php' ) && $pakesrc = __DIR__ . '/../../indeyets/pake/bin/pake.php' ) ||
        ( file_exists( __DIR__ . '/vendor/indeyets/pake/bin/pake.php' ) && $pakesrc = __DIR__ . '/vendor/indeyets/pake/bin/pake.php' )*/ ) )
    {
        include_once( 'pake/src/bin/pake.php' );
    }

    if ( !class_exists( 'pakeApp' ) )
    {
        echo "Pake tool not found. Bootstrap needed.\nTry running 'composer install' or 'composer update'\n";
        exit( -1 );
    }

    $GLOBALS['internal_pake'] = true;

}

if ( !function_exists( 'pake_exception_default_handler' ) )
{
    // same bootstrap code as done by pake_cli_init.php, which we do not bother searching for in the composer dir
    function pake_exception_default_handler( $exception )
    {
        pakeException::render( $exception );
        exit( 1 );
    }
}
set_exception_handler( 'pake_exception_default_handler' );
mb_internal_encoding( 'utf-8' );

// take over display of help - in case we want to modify some of it
function run_help( $task=null, $args=array(), $cliopts=array() )
{
    /*if ( count( $args ) == 0 )
    {
        // ...
    }*/
    // work around a pake bug
    if ( count( $args ) > 0 )
        $args[0] = pakeTask::get_full_task_name( $args[0] );
    $pake = pakeApp::get_instance();
    $pake->run_help( $task, $args );
};
pake_task( 'help' );

// pakeApp will include again the main pakefile.php, and execute all the pake_task() calls found in it
$pake = pakeApp::get_instance();
if ( getcwd() !== __DIR__ )
{
    // Running from another directory compared to where pakefile is.
    // Pake 1.7.4 and earlier has a bug: it does not support specification of pakefile.php using absolute paths, at least on windows
    /// @todo to support pakefile.php in other locations, subclass pakeApp and override load_pakefile()
    $retval = $pake->run( preg_replace( '#^' . preg_quote( getcwd() . DIRECTORY_SEPARATOR ) . '#', '', __DIR__ . '/pakefile.php' ) );
}
else
{
    $retval = $pake->run();
}

if ($retval === false )
{
    exit(1);
}

?>
