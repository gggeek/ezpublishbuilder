<?php
/**
* eZPublishBuilder pakefile:
* a script to build & package the eZ Publish Community Project
*
* Needs the Pake tool to run: https://github.com/indeyets/pake/wiki
* It can bootstrap, by downloading all required components from the web
*
* @author    G. Giunta
* @copyright (C) G. Giunta 2011
* @license   code licensed under the GNU GPL 2.0: see README file
* @version   SVN: $Id$
*/

// too smart for your own good: allow this script to be gotten off web servers in source form
if ( isset( $_GET['show'] ) && $_GET['show'] == 'source' )
{
    echo file_get_contents( __FILE__ );
    exit;
}

// *** function definition (live code at the end) ***/

// Since this script might be included twice, we wrap any function in an ifdef

if ( !function_exists( 'register_ezc_autoload' ) )
{
    // try to force ezc autoloading. End user should have set php include path properly
    function register_ezc_autoload()
    {
        if ( !class_exists( 'ezcBase' ) )
        {
            @include( 'ezc/Base/base.php' ); // pear install
            if ( !class_exists( 'ezcBase' ) )
            {
                @include( 'Base/src/base.php' ); // tarball download / svn install
            }
            if ( class_exists( 'ezcBase' ) )
            {
                spl_autoload_register( array( 'ezcBase', 'autoload' ) );
            }
        }
    }
}

if ( !function_exists( 'run_default' ) )
{

// definition of the pake tasks

function run_default()
{
    pake_echo ( "eZ Publish Community Project Builder ver." . eZPCPBuilder::$version . "\nSyntax: php pakefile.php [--\$general-options] \$task [--\$task-options].\n  Run: php pakefile.php --tasks to learn more about available tasks." );
}

/// @todo show more properties
function run_show_properties( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( @$args[0] );
    pake_echo ( 'Build dir: ' . eZPCPBuilder::getBuildDir( $opts ) );
    //pake_echo ( 'Extension name: ' . $opts['ezpublish']['name'] );*/
}

/**
* Downloads eZP from its source repository, removes files not to be built
* @todo add a dependency on a check-updates task that updates script itself?
*/
function run_init( $task=null, $args=array(), $cliopts=array() )
{
    $skip_init = @$cliopts['skip-init'];
    $skip_init_fetch = @$cliopts['skip-init-fetch'] || $skip_init;
    $skip_init_clean = @$cliopts['skip-init-clean'] || $skip_init;

    if ( ! $skip_init )
    {
        $opts = eZPCPBuilder::getOpts( @$args[0] );
        pake_mkdirs( eZPCPBuilder::getBuildDir( $opts ) );

        //$destdir = eZPCPBuilder::getBuildDir( $opts ) . '/' . $opts['ezpublish']['name'];
        pake_echo( 'NB: init is a manual process for the moment' );
        pake_echo( 'Please copy eZ Publish sources into directory: ' . eZPCPBuilder::getBuildDir( $opts ) );
    }

    /*
    if ( ! $skip_init_fetch )
    {
        if ( @$opts['svn']['url'] != '' )
        {
            pake_echo( 'Fetching code from SVN repository' );
            pakeSubversion::checkout( $opts['svn']['url'], $destdir );
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            {
                sleep( 3 );
            }
        }
        else if ( @$opts['git']['url'] != '' )
        {
            pake_echo( 'Fetching code from GIT repository' );
            pakeGit::clone_repository( $opts['git']['url'], $destdir );
            if ( @$opts['git']['branch'] != '' )
            {
                /// @todo test checking out a specific branch
                pakeGit::checkout_repo( $destdir, $opts['git']['branch'] );
            }
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            {
                sleep( 3 );
            }
        }
        else if ( @$opts['file']['url'] != '' )
        {
            pake_echo( 'Fetching code from local repository' );
            /// @todo (!important) exclude stuff we know we're going to delete immediately afterwards
            $files = pakeFinder::type( 'any' )->in( $opts['file']['url'] );
            pake_mirror( $files, $opts['file']['url'], $destdir );
        }
        else
        {
            throw new pakeException( "Missing source repo option: either svn:url, git:url or file:url" );
        }
    }

    /*
    // remove files
    if ( ! $skip_init_clean )
    {
        // known files/dirs not to be packed / md5'ed
        /// @todo !important shall we make this configurable?
        /// @bug 'build' & 'dist' we should probably take from options
        $files = array( 'ant/', 'build.xml', '**' . '/.svn', '.git/', 'build/', 'dist/' );
        // hack! when packing ourself, we need to keep this stuff
        if ( $opts['ezpublish']['name'] != 'ezextensionbuilder' )
        {
            $files = array_merge( $files, array( 'pake/', 'pakefile.php', '**' . '/.gitignore' ) );
        }
        // files from user configuration
        $files = array_merge( $files, $opts['files']['to_exclude'] );

        /// we figured a way to allow user to speficy both:
        ///       files in a specific subdir
        ///       files to be removed globally (ie. from any subdir)
        //pakeFinder::type( 'any' )->name( $files )->in( $destdir );
        $files = pake_antpattern( $files, $destdir );
        foreach( $files as $key => $file )
        {
            if ( is_dir( $file ) )
            {
                pake_remove_dir( $file );
                unset( $files[$key] );
            }
        }
        pake_remove( $files, '' );
    }

    if ( ! $skip_init )
    {
        // move package file where it has to be
        $file = pakeFinder::type( 'file' )->name( 'package.xml' )->maxdepth( 0 )->in( $destdir );
        if ( count( $file ) )
        {
            if ( $opts['create']['tarball'] || $opts['create']['zip'] )
            {
                pake_rename( $destdir . '/package.xml', $destdir . '/../../package.xml' );
            }
            else
            {
                pake_remove( $file, '' );
            }
        }
    }*/
}

/// We rely on the pake dependency system here to do real stuff
/// @todo remove this warning as soon as we implement some tasks
function run_build( $task=null, $args=array(), $cliopts=array() )
{
     pake_echo( 'The build task is not doing anything at the moment. Just run "dist"' );
}

function run_clean( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( @$args[0] );
    pake_remove_dir( eZPCPBuilder::getBuildDir( $opts ) );
}

function run_dist( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( @$args[0] );
    if ( $opts['create']['mswpipackage'] /*|| $opts['create']['zip'] || $opts['create']['ezpackage'] || $opts['create']['pearpackage']*/ )
    {
        if ( !class_exists( 'ezcArchive' ) )
        {
            throw new pakeException( "Missing Zeta Components: cannot generate tar file. Use the environment var PHP_CLASSPATH" );
        }
        pake_mkdirs( $opts['dist']['dir'] );
        $rootpath = eZPCPBuilder::getBuildDir( $opts ) . '/' . eZPCPBuilder::getProjName();
        if ( $opts['create']['mswpipackage'] )
        {
            // add extra files to build @todo move this to another phase/task...
            $toppath = eZPCPBuilder::getBuildDir( $opts );
            $pakepath = dirname( __FILE__ ) . '/pake';
            pake_copy( $pakepath . '/install.sql', $toppath . '/install.sql' );

            /// @todo: if the $rootpath is different from "ezpublish", the manifest and parameters files need to be altered accordingly
            /// after copying them to their location
            pake_copy( $pakepath . '/manifest.xml', $toppath . '/manifest.xml' );
            pake_copy( $pakepath . '/parameters.xml', $toppath . '/parameters.xml' );

            // this one is overwritten
            pake_copy( $pakepath . '/kickstart.ini', $rootpath . '/kickstart.ini', array( 'override' => true ) );

            if ( is_file( $rootpath . '/web.config-RECOMMENDED' ) )
            {
                pake_copy( $rootpath . '/web.config-RECOMMENDED', $rootpath . '/web.config' );
            }
            else if ( !is_file( $rootpath . '/web.config' ) )
            {
                pake_copy( $pakepath . '/web.config', $rootpath . '/web.config' );
            }

            // create zip
            /// @todo if name is empty do not add an extra hyphen
            $filename = 'ezpublish-' . $opts[eZPCPBuilder::getProjName()]['name'] . '-' . $opts['version']['alias'] . '-wpi.zip';
            $target = $opts['dist']['dir'] . '/' . $filename;
            eZPCPBuilder::archiveDir( $toppath, $target, ezcArchive::ZIP, true );

            // update feed file
            $feedfile = 'ezpcpmswpifeed.xml';
            pake_copy( $pakepath . '/' . $feedfile, $opts['dist']['dir'] . '/' . $feedfile );
            $files = pakeFinder::type( 'file' )->name( $feedfile )->maxdepth( 0 )->in( $opts['dist']['dir'] );
            //pake_replace_regexp( $files, $opts['dist']['dir'], array(
            //) );
            pake_replace_tokens( $files, $opts['dist']['dir'], '{', '}', array(
                '$update_date' => gmdate( 'c' ),
                '$version' => $opts['version']['alias'],
                '$sha1' => sha1_file( $target ),
                '$filename' => $filename,
                '$filesize' => filesize( $target )
            ) );

        }

        /*if ( $opts['create']['tarball'] )
        {
            $target = $opts['dist']['dir'] . '/' . $opts['ezpublish']['name'] . '-' . $opts['version']['alias'] . '.' . $opts['version']['release'] . '.tar.gz';
            eZPCPBuilder::archiveDir( $rootpath, $target, ezcArchive::TAR );
        }*/

        /*if ( $opts['create']['zip'] )
        {
            $target = $opts['dist']['dir'] . '/' . $opts['ezpublish']['name'] . '-' . $opts['version']['alias'] . '.' . $opts['version']['release'] . '.zip';
            eZPCPBuilder::archiveDir( $rootpath, $target, ezcArchive::ZIP );
        }*/

        /*if ( $opts['create']['ezpackage'] || $opts['create']['pearpackage'] )
        {
            $toppath = $opts['build']['dir'];

            // check if package.xml file is there
            $file = pakeFinder::type( 'file' )->name( 'package.xml' )->maxdepth( 0 )->in( $toppath );
            if ( !count( $file ) )
            {
                pake_echo_error( "File 'package.xml' missing in build dir $rootpath. Cannot create package(s)" );
                return;
            }

            // cleanup if extra files/dirs found
            $dirs = array();
            $dirs = pakeFinder::type( 'directory' )->not_name( array( 'documents', 'ezextension' ) )->maxdepth( 0 )->in( $toppath );
            $dirs = array_merge( $dirs, pakeFinder::type( 'directory' )->in( $toppath . '/documents' ) );
            $dirs = array_merge( $dirs, pakeFinder::type( 'directory' )->not_name( $opts['ezpublish']['name'] )->maxdepth( 0 )->in( $toppath . '/ezextension' ) );
            $files = pakeFinder::type( 'file' )->not_name( 'package.xml' )->maxdepth( 0 )->in( $toppath );
            $files = array_merge( $files, pakeFinder::type( 'file' )->in( $toppath . '/documents' ) );
            $files = array_merge( $files, pakeFinder::type( 'file' )->not_name( 'extension-' . $opts['ezpublish']['name']. '.xml' )->maxdepth( 0 )->in( $toppath . '/ezextension' ) );
            if ( count( $dirs ) || count( $files ) )
            {
                pake_echo( "Extra files/dirs found in build dir. Must remove them to continue:\n  " . implode( "\n  ", $dirs ) . "  ". implode( "\n  ", $files ) );
                $ok = pake_input( "Do you want to delete them? [y/n]", 'n' );
                if ( $ok != 'y' )
                {
                    return;
                }
                foreach( $files as $file )
                {
                    pake_remove( $file, '' );
                }
                foreach( $dirs as $dir )
                {
                    pake_remove_dir( $dir );
                }
            }
            // prepare missing folders/files
            /// @todo we should not blindly copy LICENSE and README, but inspect actual package.xml file
            ///       and copy any files mentioned there
            pake_copy( $rootpath . '/' . $opts['files']['gnu_dir'] . '/LICENSE', $toppath . '/documents/LICENSE' );
            pake_copy( $rootpath . '/' . $opts['files']['gnu_dir'] . '/README', $toppath . '/documents/README' );
            $target = $opts['dist']['dir'] . '/' . $opts['ezpublish']['name'] . '_extension.ezpkg';
            eZPCPBuilder::archiveDir( $toppath, $target, ezcArchive::TAR, true );

            if ( $opts['create']['pearpackage'] )
            {
                /// @todo ...
                pake_echo_error( "PEAR package creation not yet implemented" );
            }
        }*/

    }
}

function run_dist_clean( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( @$args[0] );
    pake_remove_dir( $opts['dist']['dir'] );
}

function run_all( $task=null, $args=array(), $cliopts=array() )
{
}

function run_clean_all( $task=null, $args=array(), $cliopts=array() )
{
}

function run_tool_upgrade_check( $task=null, $args=array(), $cliopts=array() )
{
    $latest = eZPCPBuilder::latestVersion();
    if ( $latest == false )
    {
        pake_echo ( "Cannot determine latest version available. Please check that you can connect to the internet" );
    }
    else
    {
        $current = eZPCPBuilder::$version;
        $check = version_compare( $latest, $current );
        if ( $check == -1 )
        {
            pake_echo ( "Danger, Will Robinson! You are running a newer version ($current) than the lastest available online ($latest)" );
        }
        else if( $check == 0 )
        {
            pake_echo ( "You are running the lastest available version: $latest" );
        }
        else
        {
            pake_echo ( "A newer version is available online: $latest (you are running $current)" );
            $ok = pake_input( "Do you want to upgrade? [y/n]", 'n' );
            if ( $ok == 'y' )
            {
                run_tool_upgrade(  $task, $args, $cliopts );
            }
        }
    }
}

/// @todo add a backup enable/disable option
function run_tool_upgrade( $task=null, $args=array(), $cliopts=array() )
{
    $latest = eZPCPBuilder::latestVersion( true );
    if ( $latest == false )
    {
        pake_echo ( "Cannot download latest version available. Please check that you can connect to the internet" );
    }
    else
    {
        // 1st get the whole 'pake' dir contents, making a backup copy
        $tmpzipfile = tempnam( "tmp", "zip" );
        $zipfile = dirname( __FILE__ ) . '/pake/pakedir-' . eZPCPBuilder::$version . '.zip';
        eZPCPBuilder::archiveDir( dirname( __FILE__ ) . '/pake', $tmpzipfile, ezcArchive::ZIP );
        @unlink( $zipfile ); // otherwise pake_rename might complain
        pake_rename( $tmpzipfile, $zipfile );
        eZPCPBuilder::bootstrap();

        // then update the pakefile itself, making a backup copy
        pake_copy( __FILE__, dirname( __FILE__ ) . '/pake/pakefile-' . eZPCPBuilder::$version . '.php', array( 'override' => true ) );
        /// @todo test: does this work on windows?
        file_put_contents( __FILE__, $latest );
    }
}

/**
* Class implementing the core logic for our pake tasks
* @todo separate in another file?
*/
class eZPCPBuilder
{
    static $options = null;
    //static $defaultext = null;
    static $installurl = 'http://svn.projects.ez.no/ezpublishbuilder/stable';
    static $version = '0.1';
    static $min_pake_version = '1.6.1';
    static $projname = 'ezpublish';

    static function getBuildDir( $opts )
    {
        $dir = $opts['build']['dir'];
        return $dir;
    }

    // leftover from ezextensionbuilder
    static function getProjName()
    {
        return self::$projname;
        /*if ( self::$defaultext != null )
        {
            return self::$defaultext;
        }
        $files = pakeFinder::type( 'file' )->name( 'options-*.yaml' )->not_name( 'options-sample.yaml' )->not_name( 'options-ezextensionbuilder.yaml' )->maxdepth( 0 )->in( 'pake' );
        if ( count( $files ) == 1 )
        {
            self::$defaultext = substr( basename( $files[0] ), 8, -5 );
            pake_echo ( 'Found extension: ' . self::$defaultext );
            return self::$defaultext;
        }
        else if ( count( $files ) == 0 )
        {
            throw new pakeException( "Missing configuration file pake/options-[extname].yaml, cannot continue" );
        }
        else
        {
            throw new pakeException( "Multiple configuration files pake/options-*.yaml found, need to specify an extension name to continue" );
        }*/
    }

    /**
    * nb: when called with a custom project name, sets it as current for subsequent calls too
    */
    static function getOpts( $projname='' )
    {
        if ( $projname == '' )
        {
            $projname = self::getProjName();
            //self::$defaultext = $projname;
        }
        else
        {
            self::$projname = $projname;
        }

        if ( !isset( self::$options[$projname] ) || !is_array( self::$options[$projname] ) )
        {
            self::loadConfiguration( "pake/options-$projname.yaml", $projname );
        }
        return self::$options[$projname];
    }

    /// @todo
    /// @bug this only works as long as all defaults are 2 levels deep
    static function loadConfiguration ( $infile='pake/options.yaml', $projname='' )
    {
        $mandatory_opts = array( 'ezpublish' => array( 'name' ), 'version' => array( 'major', 'minor', 'release' ) );
        $default_opts = array(
            'build' => array( 'dir' => 'build' ),
            'dist' => array( 'dir' => 'dist' ),
            'create' => array( 'mswpipackage' => true, /*'tarball' => false, 'zip' => false, 'filelist_md5' => true, 'doxygen_doc' => false, 'ezpackage' => false, 'pearpackage' => false*/ ),
            'version' => array( 'license' => 'GNU General Public License v2.0' ),
            'releasenr' => array( 'separator' => '.' ),
            //'files' => array( 'to_parse' => array(), 'to_exclude' => array(), 'gnu_dir' => '', 'sql_files' => array( 'db_schema' => 'schema.sql', 'db_data' => 'cleandata.sql' ) ),
            /*'dependencies' => array( 'extensions' => array() )*/ );
        /// @todo !important: test if !file_exists give a nicer warning than what we get from loadFile()
        $options = pakeYaml::loadFile( $infile );
        foreach( $mandatory_opts as $key => $opts )
        {
            foreach( $opts as $opt )
            {
                if ( !isset( $options[$key][$opt] ) )
                {
                    throw new pakeException( "Missing mandatory option: $key:$opt" );
                }
            }
        }
        if ( !isset( $options['version']['alias'] ) || $options['version']['alias'] == '' )
        {
            $options['version']['alias'] = $options['version']['major'] . '.' . $options['version']['minor'];
        }
        foreach( $default_opts as $key => $opts )
        {
            if ( isset( $options[$key] ) && is_array( $options[$key] ) )
            {
                $options[$key] = array_merge( $opts, $options[$key] );
            }
            else
            {
                /// @todo echo a warning if $options[$key] is set but not array?
                $options[$key] = $opts;
            }
        }
        self::$options[$projname] = $options;
        return true;
    }

    /**
    * Download from the web all files that make up the extension (except self)
    * and uncompress them in ./pake dir
    */
    static function bootstrap()
    {
        if ( is_file( 'pake ' ) )
        {
            echo "Error: could not create 'pake' directory to install the extension because a file named 'pake' exists";
            exit( -1 );
        }

        if ( is_dir( 'pake') )
        {
            /// @todo test: if dir is not empty, ask for confirmation,
            ///       least we overwrite something
        }

        if ( !is_dir( 'pake' ) && !mkdir( 'pake' ) )
        {
            echo "Error: could not create 'pake' directory to install the extension";
            exit( -1 );
        }

        // download components
        /// @todo use a recursive fget, so that we do not need to download a zip
        $src = self::$installurl.'/pake/ezpublishbuilder_pakedir.zip';
        $zipfile = tempnam( "tmp", "zip" );
        if ( !file_put_contents( $zipfile, file_get_contents( $src ) ) )
        {
            echo "Error: could not download source file $src";
            exit -1;
        }

        // unzip them
        $zip = new ZipArchive;
        if ( $zip->open( $zipfile ) !== true )
        {
            echo "Error: downloaded source file $src is not a valid zip file";
            exit -1;
        }
        if ( !$zip->extractTo( 'pake' ) )
        {
            echo "Error: could not decompress source file $zipfile";
            $zip->close();
            exit -1;
        }
        $zip->close();
        unlink( $zipfile );
    }

    /**
    * Checks the latest version available online
    * @return string the version nr. or the new version of the file, depending on input param (false in case of error)
    */
    static function latestVersion( $getfile=false )
    {
        $src = self::$installurl.'/pakefile.php?show=source';
        /// @todo test using curl for allow_url_fopen off
        if ( $source = pake_read_file( $src ) )
        {
            if ( $getfile )
            {
                return $source;
            }
            if ( preg_match( '/^[\s]*static \$version = \'([^\']+)\';/m', $source, $matches ) )
            {
                return $matches[1];
            }
        }
        return false;
    }

    /**
    * Creates an archive out of a directory
    */
    static function archiveDir( $sourcedir, $archivefile, $archivetype, $no_top_dir=false )
    {
        if ( substr( $archivefile, -3 ) == '.gz' )
        {
            $zipext = 'gz';
            $target = substr( $archivefile, 0, -3 );
        }
        else if ( substr( $archivefile, -6 ) == '.ezpkg' )
        {
            $zipext = 'ezpkg';
            $target = substr( $archivefile, 0, -6 ) . '.tar';
        }
        else
        {
            $zipext = false;
            $target = $archivefile;
        }
        $rootpath = str_replace( '\\', '/', realpath( $no_top_dir ? $sourcedir : dirname( $sourcedir ) ) );
        $files = pakeFinder::type( 'any' )->in( $sourcedir );
        // fix for win
        foreach( $files as $i => $file )
        {
            $files[$i] = str_replace( '\\', '/', $file );
        }
        // current ezc code does not like having folders in list of files to pack
        // unless they end in '/'
        foreach( $files as $i => $f )
        {
            if ( is_dir( $f ) )
            {
                $files[$i] = $files[$i] . '/';
            }
        }
        // we do not rely on this, not to depend on phar extension and also because it's slightly buggy if there are dots in archive file name
        //pakeArchive::createArchive( $files, $opts['build']['dir'], $target, true );
        $tar = ezcArchive::open( $target, $archivetype );
        $tar->truncate();
        $tar->append( $files, $rootpath );
        $tar->close();
        if ( $zipext )
        {
            $fp = fopen( 'compress.zlib://' . ( $zipext == 'ezpkg' ? substr( $target, 0, -4 ) : $target ) . ".$zipext", 'wb9' );
            /// @todo read file by small chunks to avoid memory exhaustion
            fwrite( $fp, file_get_contents( $target ) );
            fclose( $fp );
            unlink( $target );
        }
        pake_echo_action( 'file+', $archivefile );
    }
}

}

// The following two functions we use, and submitted for inclusion in pake.
// While we wait for acceptance, we define them here...
if ( !function_exists( 'pake_replace_regexp_to_dir' ) )
{

function pake_replace_regexp_to_dir($arg, $src_dir, $target_dir, $regexps, $limit=-1)
{
    $files = pakeFinder::get_files_from_argument($arg, $src_dir, true);

    foreach ($files as $file)
    {
        $replaced = false;
        $content = pake_read_file($src_dir.'/'.$file);
        foreach ($regexps as $key => $value)
        {
            $content = preg_replace($key, $value, $content, $limit, $count);
            if ($count) $replaced = true;
        }

        pake_echo_action('regexp', $target_dir.DIRECTORY_SEPARATOR.$file);

        file_put_contents($target_dir.DIRECTORY_SEPARATOR.$file, $content);
    }
}

function pake_replace_regexp($arg, $target_dir, $regexps, $limit=-1)
{
    pake_replace_regexp_to_dir($arg, $target_dir, $target_dir, $regexps, $limit);
}

}

if ( !function_exists( 'pake_antpattern' ) )
{

/**
* Mimics ant pattern matching.
* Waiting for pake 1.6.2 or later to provide this natively
* @see http://ant.apache.org/manual/dirtasks.html#patterns
* @todo more complete testing
* @bug looking for " d i r / * * / " will return subdirs but not dir itself
*/
function pake_antpattern( $files, $rootdir )
{
    $results = array();
    foreach( $files as $file )
    {
        //echo " Beginning with $file in dir $rootdir\n";

        // safety measure: try to avoid multiple scans
        $file = str_replace( '/**/**/', '/**/', $file );

        $type = 'any';
        // if user set '/ 'as last char: we look for directories only
        if ( substr( $file, -1 ) == '/' )
        {
            $type = 'dir';
            $file = substr( $file, 0, -1 );
        }
        // managing 'any subdir or file' as last item: trick!
        if ( strlen( $file ) >= 3 && substr( $file, -3 ) == '/**' )
        {
            $file .= '/*';
        }

        $dir = dirname( $file );
        $file = basename( $file );
        if ( strpos( $dir, '**' ) !== false )
        {
            $split = explode( '/', $dir );
            $path = '';
            foreach( $split as $i => $part )
            {
                if ( $part != '**' )
                {
                    $path .= "/$part";
                }
                else
                {
                    //echo "  Looking for subdirs in dir $rootdir{$path}\n";
                    $newfile = implode( '/', array_slice( $split, $i + 1 ) ) . "/$file" . ( $type == 'dir'? '/' : '' );
                    $dirs = pakeFinder::type( 'dir' )->in( $rootdir . $path );
                    // also cater for the case '** matches 0 subdirs'
                    $dirs[] = $rootdir . $path;
                    foreach( $dirs as $newdir )
                    {
                        //echo "  Iterating in $newdir, looking for $newfile\n";
                        $found = pake_antpattern( array( $newfile ), $newdir );
                        $results = array_merge( $results, $found );
                    }
                    break;
                }
            }
        }
        else
        {
            //echo "  Looking for $type $file in dir $rootdir/$dir\n";
            $found = pakeFinder::type( $type )->name( $file )->maxdepth( 0 )->in( $rootdir . '/' . $dir );
            //echo "  Found: " . count( $found ) . "\n";
            $results = array_merge( $results, $found );
        }
    }
    return $results;
}

}


// *** Live code starts here ***

// First off, test if user is running directly this script
// (we allow both direct invocation via "php pakefile.php" and invocation via "php pake.php")
if ( !function_exists( 'pake_desc' ) )
{
    // Running script directly. look if pake is found in the folder where this script installs it: ./pake/src
    if ( file_exists( 'pake/src/bin/pake.php' ) )
    {
        include( 'pake/src/bin/pake.php' );

        // force ezc autoloading (including pake.php will have set include path from env var PHP_CLASSPATH)
        register_ezc_autoload();

        $GLOBALS['internal_pake'] = true;

        $pake = pakeApp::get_instance();
        $pake->run();
    }
    else
    {

        echo "Pake tool not found. Bootstrap needed\n  (automatic download of missing components from projects.ez.no)\n";
        do
        {
            echo 'Continue? [y/n] ';
            $fp = fopen('php://stdin', 'r');
            $ok = trim( strtolower( fgets( $fp ) ) );
            fclose( $fp );
            if ( $ok == 'y' )
            {
                break;
            }
            else if ( $ok == 'n' )
            {
                exit ( 0 );
            }
            echo "\n";
        } while( true );

        eZPCPBuilder::bootstrap();

        echo
            "Succesfully downloaded sources\n" .
            "  Next steps: edit file pake/options-ezpublish.yaml to suit your needs\n" .
            "  (eg: change version nr.), then run again this script.\n".
            "  Use the environment var PHP_CLASSPATH for proper class autoloading of eg. Zeta Components";
        exit( 0 );

    }
}
else
{
    // pake is loaded

// force ezc autoloading (including pake.php will have set include path from env var PHP_CLASSPATH)
register_ezc_autoload();

/// @todo test if the hack below is necessary for ezpublishbuilder or if we can remove the if.
///       After all, we never shipped a release with a non-versioned pake...

// this is unfortunately a necessary hack: version 0.1 of this extension
// shipped with a faulty pake_version, so we cannot check for required version
// when using the bundled pake.
// To aggravate things, version 0.1 did not upgrade the bundled pake when
// upgrading to a new script, so we can not be sure that, even if the end user
// updates to a newer pakefile, the bundled pake will be upgraded
// (it will only be when the user does two consecutive updates)
if ( !( isset( $GLOBALS['internal_pake'] ) && $GLOBALS['internal_pake'] ) )
{
    pake_require_version( eZPCPBuilder::$min_pake_version );
}

pake_desc( 'Shows help message' );
pake_task( 'default' );

pake_desc( 'Shows the properties for this build file' );
pake_task( 'show-properties' );

pake_desc( 'Downloads sources from git and removes unwanted files' );
pake_task( 'init' );

/// @todo ...
pake_desc( 'Builds the cms. Options: --skip-init' );
pake_task( 'build', 'init' );

pake_desc( 'Removes the build/ directory' );
pake_task( 'clean' );

pake_desc( 'Creates tarball(s) of the build' );
pake_task( 'dist' );

pake_desc( 'Removes the dist/ directory' );
pake_task( 'dist-clean' );

pake_desc( 'Builds the cms and generates the tarball' );
pake_task( 'all', 'build', 'dist' );

pake_desc( 'Removes the build/ and dist/ directories' );
pake_task( 'clean-all', 'clean', 'dist-clean' );


/*
pake_desc( 'Updates ezinfo.php and extension.xml with correct version numbers and licensing info' );
pake_task( 'update-ezinfo' );

pake_desc( 'Update license headers in source code files (php, js, css)' );
pake_task( 'update-license-headers' );

pake_desc( 'Updates extra files with correct version numbers and licensing info' );
pake_task( 'update-extra-files' );

pake_desc( 'Generates the documentation of the extension, if created in RST format in the doc/ folder, plus optionally API docs via doxygen. Options: --doxygen=/path/to/doxygen' );
pake_task( 'generate-documentation' );

//pake_desc( 'Checks PHP code coding standard, requires PHPCodeSniffer' );
//pake_task( 'coding-standards-check' );

pake_desc( 'Generates a share/filelist.md5 file with md5 checksums of all source files' );
pake_task( 'generate-md5sums' );

pake_desc( 'Checks if a schema.sql / cleandata.sql is available for all supported databases' );
pake_task( 'check-sql-files' );

pake_desc( 'Checks for presence of LICENSE and README files' );
pake_task( 'check-gnu-files' );

pake_desc( 'Generates an XML filelist definition for packaged extensions' );
pake_task( 'generate-package-filelist' );

pake_desc( 'Updates information in package.xml file used by packaged extensions' );
pake_task( 'update-package-xml' );

pake_desc( 'Build dependent extensions' );
pake_task( 'build-dependencies' );

pake_desc( 'Creates an ezpackage tarball.' );
pake_task( 'generate-package-tarball', 'update-package-xml', 'generate-package-filelist' );
*/

pake_desc( 'Checks if a newer version of the tool is available online' );
pake_task( 'tool-upgrade-check' );

pake_desc( 'Upgrades to the latest version of the tool available online' );
pake_task( 'tool-upgrade' );


}

?>
