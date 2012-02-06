<?php
/**
* eZPublishBuilder pakefile:
* a script to build & package the eZ Publish Community Project.
*
* Needs the Pake tool to run: https://github.com/indeyets/pake/wiki
* It can bootstrap, by downloading all required components from the web
*
* The steps involved in the build process are described here:
* https://docs.google.com/a/ez.no/document/d/1h5n3aZdXbyo9_iJoDjoDs9a6GdFZ2G-db9ToK7J1Gck/edit?hl=en_GB
*
* @author    G. Giunta
* @copyright (C) G. Giunta 2011-2012
* @license   code licensed under the GNU GPL 2.0: see README file
* @version   $Id$
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

function run_show_properties( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );
    pake_echo ( print_r( $opts, true ) );
}

/**
* Downloads eZP from its source repository, ...
* @todo add a dependency on a check-updates task that updates script itself?
*/
function run_init( $task=null, $args=array(), $cliopts=array() )
{
    $skip_init = @$cliopts['skip-init'];
    $skip_init_fetch = @$cliopts['skip-init-fetch'] || $skip_init;

    if ( ! $skip_init )
    {
        $opts = eZPCPBuilder::getOpts( $args );
        pake_mkdirs( $opts['build']['dir'] );

        $destdir = $opts['build']['dir'] . '/source/' . eZPCPBuilder::getProjName();
    }

    if ( ! $skip_init_fetch )
    {
        pake_echo( 'Fetching code from GIT repository' );

        if ( @$opts['git']['url'] == '' )
        {
            throw new pakeException( "Missing source repo option git:url in config file" );
        }

        /// @todo test what happens if target dir is not empty?

        /// @todo to make successive builds faster - if repo exists already just
        ///       update it
        pakeGit::clone_repository( $opts['git']['url'], $destdir );

        if ( @$opts['git']['branch'] != '' )
        {
            pake_echo( "Using GIT branch {$opts['git']['branch']}" );
            pakeGit::checkout_repo( $destdir, $opts['git']['branch'] );
        }
    }
}

/**
 * Downloads the CI repo from its source repository
 */
function run_init_ci_repo( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );
    if ( @$opts['ci-repo']['local-path'] == '' )
    {
        throw new pakeException( "Missing option ci-repo:local-path in config file: can not download CI repo" );
    }
    $destdir = $opts['ci-repo']['local-path'];

    pake_echo( 'Fetching sources from CI git repository' );

    /// @todo test what happens if target dir is not empty?

    $repo = pakeGit::clone_repository( $opts['ci-repo']['git-url'], $destdir );
    // q: is this really needed?
    if ( $opts['ci-repo']['git-branch'] != '' )
    {
        $repo->checkout( $opts['ci-repo']['git-branch'] );
    }

    /// @todo set up username and password in $opts['ci-repo']['git-branch']/.git/config

    pake_echo( "The CI git repo has been set up in $destdir\n" .
        "You should now set up properly your git user account and make sure it has\n".
        "permissions to commit to it" );
}

/**
* We rely on the pake dependency system to do the real stuff
* (run pake -P to see tasks included in this one)
*/
function run_build( $task=null, $args=array(), $cliopts=array() )
{
}

/**
* Generates a changelog file based on git commit logs.
* The generated file is placed in the correct folder within doc/changelogs.
* It should be reviewed/edited by hand, then committed with the task "update-ci-repo".
*/
function run_generate_changelog( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );
    $rootpath = $opts['build']['dir'] . '/source/' . eZPCPBuilder::getProjName();

    if ( isset( $opts['version']['previous']['git-revision'] ) )
    {
        $previousrev = $opts['version']['previous']['git-revision'];
    }
    else
    {
        $prevname = eZPCPBuilder::previousVersionName( $opts );
        pake_echo( "Previous release assumed to be $prevname" );
        pake_echo( "Looking up corresponding build number in Jenkins" );
        // find git rev of the build of the previous release on jenkins
        $previousrev = '';
        $buildsurl = $opts['jenkins']['url'] . '/job/' . $opts['jenkins']['job'] . '/api/json?tree=builds[description,number,result,binding]';
        // pake_read_file throws exception on http errors, no need to check for it
        $out = json_decode( pake_read_file( $buildsurl ), true );
        if ( is_array( $out ) && isset( $out['builds'] ) )
        {
            $previousbuild = '';

            foreach( $out['builds'] as $build )
            {
                if ( strpos( $build['description'], $prevname ) !== false )
                {
                    $previousbuild = $build['number'];
                    break;
                }
            }

            if ( $previousbuild )
            {
                $buildurl = $opts['jenkins']['url'] . '/job/' . $opts['jenkins']['job'] . '/' . $previousbuild . '/api/json';
                $out = json_decode( pake_read_file( $buildurl ), true );
                if ( is_array( @$out['actions'] ) )
                {
                    foreach( $out['actions'] as $action )
                    {
                        if ( isset( $action['lastBuiltRevision'] ) )
                        {
                            $previousrev = $action['lastBuiltRevision']['SHA1'];
                            pake_echo( "Release $prevname found in Jenkins build $previousbuild, corresponding to git rev. $previousrev" );
                            break;
                        }
                    }
                    if ( $previousrev == '' )
                    {
                        pake_echo( "Git revision not found in builds description" );
                    }
                }
            }
            else
            {
                pake_echo( "Release not found in builds list" );
            }
        }
        else
        {
            pake_echo( "Cannot retrieve builds list" );
        }
    }

    if ( $previousrev != '' )
    {
        pake_echo( "Updating eZ Publish sources from git" );

        /// @todo move all of this in a specific function to be reused

            // 1. check if build dir is correctly linked to source git repo
            /// @todo test for git errors
            exec( 'cd ' . escapeshellarg( $rootpath ) . " && git remote -v", $remotesArray, $ok );
            $found = false;
            foreach( $remotesArray as $remote )
            {
                if ( strpos( $remote, $opts['git']['url'] . ' (fetch)' ) !== false )
                {
                    // q: should we check that remote is called 'origin'? since we later call 'pull' without params...
                    $found = true;
                }
            }
            if ( !$found )
            {
                throw new pakeException( "Build dir $rootpath does nto seem to be linked to git repo {$opts['git']['url']}" );
            }

            // 2. pull and move to correct branch
            $repo = new pakeGit( $rootpath );

            /// @todo test that the branch switch does not fail
            if ( @$opts['git']['branch'] != '' )
            {
                $repo->checkout( $opts['git']['branch'] );
            }
            else
            {
                $repo->checkout( 'master' );
            }

            /// @todo test that the pull does not fail
            $repo->pull();

        /// @todo check if given revision exists in git repo? We'll get an empty changelof if it does not...

        // pake's own git class does not allow usage of 'git log' yet
        /// @todo test for git errors
        exec( 'cd ' . escapeshellarg( $rootpath ) . " && git log --pretty=%s " . escapeshellarg( $previousrev ) . "..HEAD", $changelogArray, $ok );

        $changelogArray = array_map( 'trim', $changelogArray );
        $changelogText = implode( "\n", $changelogArray );

        // extract known wit issues
        preg_match_all( "/^[- ]?Fix(?:ed|ing)?(?: bug|for ticket)? #0?([0-9]+):? (.*)$/mi", $changelogText, $bugfixesMatches, PREG_PATTERN_ORDER );
        preg_match_all( "/^[- ]?Implement(?:ed)?(?: enhancement)? #0?([0-9]+):? (.*)$/mi", $changelogText, $enhancementsMatches, PREG_PATTERN_ORDER );
        preg_match_all( "/^Merge pull request #0?([0-9]+):? (.*)$/mi", $changelogText, $pullreqsMatches, PREG_PATTERN_ORDER );

        // remove all bugfixes & enhancements from the changelog to get unmatched items
        $unmatchedEntries = array_map(
            function( $item )
            {
                return ( substr( $item, 0, 2 ) != "- " ? "- $item" : $item );
            },
            array_diff(
                $changelogArray,
                $bugfixesMatches[0],
                $enhancementsMatches[0],
                $pullreqsMatches[0] )
        );

    }
    else
    {
        pake_echo( 'Can not determine the git tag of last version. Generating an empty changelog file' );

        $bugfixesMatches = array(array());
        $enhancementsMatches = array(array());
        $pullreqsMatches = array(array());
        $unmatchedEntries = array();
    }

    /// @todo handle reverts ? Or process manually ?

    $out = "Bugfixes\n========\n";
    $out .= join( "\n", eZPCPBuilder::gitLogMatchesAsEntries( $bugfixesMatches ) );
    $out .= "\n\n";

    $out .= "Enhancements\n============\n";
    $out .= join( "\n", eZPCPBuilder::gitLogMatchesAsEntries( $enhancementsMatches ) );
    $out .= "\n\n";

    $out .= "Pull requests\n=============\n";
    $out .= join( "\n", eZPCPBuilder::gitLogMatchesAsEntries( $pullreqsMatches ) );
    $out .= "\n\n";

    $out .= "Miscellaneous\n=============\n";
    $out .= join( "\n", $unmatchedEntries );

    $changelogdir = $rootpath . '/doc/changelogs/Community_Project-' . $opts['version']['major'];
    $filename = eZPCPBuilder::changelogFilename( $opts );
    pake_mkdirs( $changelogdir );
    pake_write_file( $changelogdir . '/' . $filename , $out, true );

}

function run_wait_for_changelog( $task=null, $args=array(), $cliopts=array() )
{
    $skip_pause = @$cliopts['skip-changelog-pause'];
    if ( !$skip_pause )
    {
        $cont = pake_select_input( "Please review (edit by hand) the changelog file.\nOnce you are done, press '1' to continue the build task. Otherwise press '2' to exit.", array( 'Continue', 'Stop' ), 1 );
        if ( $cont != 'Y' )
        {
            exit;
        }
    }
}

/// @todo
function run_generate_html_changelog( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );
    $rootpath = $opts['build']['dir'] . '/source/' . eZPCPBuilder::getProjName();
    $changelogdir = $rootpath . '/doc/changelogs/Community_Project-' . $opts['version']['major'];
    $filename = eZPCPBuilder::changelogFilename( $opts );

    $file = pake_read_file( $changelogdir . '/' . $filename );

    pake_echo( 'TO DO: build html version of changelog' );
}

/**
* This tasks updates files in the "ci" git repository.
* The "ci" repo is used by the standard eZ Publish build process, driven by Jenkins.
* It holds, amongs other things, patch files that are applied in order to build the
* CP version instead of the Enterprise one
*/
function run_update_ci_repo( $task=null, $args=array(), $cliopts=array() )
{
    // needed on windows - unless a recent git version is used (1.7.9 is ok)
    // and a patched pakeGit class is used ( > pake 1.6.3)
    pakeGit::$needs_work_tree_workaround = true;

    $opts = eZPCPBuilder::getOpts( $args );
    $rootpath = $opts['build']['dir'] . '/source/' . eZPCPBuilder::getProjName();

    // 0. generate changelog diff
    $changelogdir = 'doc/changelogs/Community_Project-' . $opts['version']['major'];
    // get absolute path to build dir
    $absrootpath = pakeFinder::type( 'directory' )->name( eZPCPBuilder::getProjName() )->in( $opts['build']['dir'] . '/source' );
    $absrootpath = dirname( $absrootpath[0] );
    $difffile = $absrootpath . '/' . $opts['version']['alias'] . '_patch_fix_changelog.diff';

    /// @todo test for errors
    exec( 'cd ' . escapeshellarg( $rootpath ) . ' && git add ' . escapeshellarg( $changelogdir ), $out, $return );

    /// @todo test for errors
    exec( 'cd ' . escapeshellarg( $rootpath ) . ' && git diff --no-prefix --staged -- ' . escapeshellarg( $changelogdir ) . " > " . escapeshellarg( $difffile ), $out, $return );

    /// unstage the file
    /// @todo test for errors
    exec( 'cd ' . escapeshellarg( $rootpath ) . ' && git reset HEAD --' );

    // start work on the ci repo:

    // 1. update ci repo
    $cipath = $opts['ci-repo']['local-path'];

    // test that we're on the good git
    exec( 'cd ' . escapeshellarg( $cipath ) . " && git remote -v", $remotesArray, $ok );
    $found = false;
    foreach( $remotesArray as $remote )
    {
        if ( strpos( $remote, $opts['ci-repo']['git-url'] . ' (fetch)' ) !== false )
        {
            // q: should we check that remote is called 'origin'? since we later call 'pull' without params...
            $found = true;
        }
    }
    if ( !$found )
    {
        throw new pakeException( "Build dir $cipath does nto seem to be linked to git repo {$opts['ci-repo']['git-url']}" );
    }

    $repo = new pakeGit( $cipath );

    if ( $opts['ci-repo']['git-branch'] != '' )
    {
        /// @todo test that the branch switch does not fail
        $repo->checkout( $opts['ci-repo']['git-branch'] );
    }

    /// @todo test that the pull does not fail
    $repo->pull();

    if ( $opts['ci-repo']['git-path'] != '' )
    {
        $cipath .= '/' . $opts['ci-repo']['git-path'];
    }

    // 2. update 0002_2011_11_patch_fix_version.diff file

    /// @todo if a new major version has been released, the '0002_2011_11_patch_fix_version.diff' patch will not apply
    ///       we need thus to regenerate one (more details: https://docs.google.com/a/ez.no/document/d/1h5n3aZdXbyo9_iJoDjoDs9a6GdFZ2G-db9ToK7J1Gck/edit?hl=en_GB)

    $files = pakeFinder::type( 'file' )->name( '0002_2011_11_patch_fix_version.diff' )->maxdepth( 0 )->in( $cipath . '/patches' );
    pake_replace_regexp( $files, $cipath . '/patches', array(
        '/^\+ +const +VERSION_MAJOR += +\d+;/m' => "+    const VERSION_MAJOR = {$opts['version']['major']};",
        '/^\+ +const +VERSION_MINOR += +\d+;/m' => "+    const VERSION_MINOR = {$opts['version']['minor']};"
    ) );
    $repo->add( array( 'patches/0002_2011_11_patch_fix_version.diff' ) );

    // 3. add new changelog file
    /// calculate sequence nr.
    $max = 0;
    $files = pakeFinder::type( 'file' )->maxdepth( 0 )->in( $cipath . '/patches' );
    foreach( $files as $file )
    {
        echo "$file\n";
        $nr = (int)substr( basename( $file ), 0, 4 );
        if ( $nr > $max )
        {
            $max = $nr;
        }
    }
    $seqnr = str_pad( (  $max + 1 ), 4, '0', STR_PAD_LEFT );
    $newdifffile = $seqnr .'_' . str_replace( '.', '_', $opts['version']['alias'] ) . '_patch_fix_changelog.diff';
    pake_copy( $difffile, $cipath . '/patches/' . $newdifffile, array( 'override' => true ) );
    $repo->add( array( 'patches/' . $newdifffile ) );

    // 4. update ezpublish-gpl.properties
    $files = pakeFinder::type( 'file' )->name( 'ezpublish-gpl.properties' )->maxdepth( 0 )->in( $cipath . '/properties' );
    pake_replace_regexp( $files, $cipath . '/properties', array(
        '/^ezp\.cp\.version\.major += +.+$/m' => "ezp.cp.version.major = {$opts['version']['major']}",
        '/^ezp\.cp\.version\.minor += +.+$/m' => "ezp.cp.version.minor = {$opts['version']['minor']}"
    ) );
    $repo->add( array( 'properties/ezpublish-gpl.properties' ) );

    // 5. commit changes and push to upstream
    $repo->commit( 'Prepare files for build of CP ' . $opts['version']['alias'] );
    /// @todo test for errors
    exec( 'cd ' . escapeshellarg( $cipath ) . ' && git push', $ouput, $return );
}

function run_wait_for_continue( $task=null, $args=array(), $cliopts=array() )
{
    $skip_pause = @$cliopts['skip-before-jenkins-pause'];
    if ( !$skip_pause )
    {
        $cont = pake_select_input( "Please verify the state of both eZ and CI github repositories.\nOnce you are done, press '1' to continue the build task. Otherwise press '2' to exit.", array( 'Continue', 'Stop' ), 1 );
        if ( $cont != 'Y' )
        {
            exit;
        }
    }
}

function run_run_jenkins_build( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );

    /// Use jenkins Remote Access Api
    /// @see https://wiki.jenkins-ci.org/display/JENKINS/Remote+access+API
    /// @see https://wiki.jenkins-ci.org/display/JENKINS/Authenticating+scripted+clients

    // trigger build
    /// @todo Improve this: jenkins gives us an http 302 => html page,
    ///       so far I have found no way to get back a json-encoded result
    $buildstarturl = $opts['jenkins']['url'] . '/job/' . $opts['jenkins']['job'] . '/build?delay=0sec';
    $out = pake_read_file( $buildstarturl );
    // "scrape" the number of the currently executing build, and hope it is the one we triggered.
    // example: <a href="lastBuild/">Last build (#506), 0.32 sec ago</a></li>
    $ok = preg_match( '/<a href="lastBuild\/">Last build \(#(\d+)\)/', $out, $matches );
    if ( !$ok )
    {
        throw new pakeException( "Build not started or unknown error" );
    }
    $buildnr = $matches[1];

    /*
       $joburl = $opts['jenkins']['url'] . '/job/' . $opts['jenkins']['job'] . '/api/json';
       $out = json_decode( pake_read_file( $buildurl ), true );
       // $buildnr = ...
    */

    pake_echo( "Build $buildnr triggered. Starting polling..." );
    $buildurl = $opts['jenkins']['url'] . '/job/' . $opts['jenkins']['job'] . '/' . $buildnr . '/api/json';
    while ( true )
    {
        sleep( 5 );
        $out = json_decode( pake_read_file( $buildurl ), true );
        if ( !is_array( $out ) || !array_key_exists( 'building', $out ) )
        {
            throw new pakeException( "Error in retrieving build status" );
        }
        else if ( $out['building'] == false )
        {
            break;
        }
        pake_echo( 'Polling...' );
    }

    if ( is_array( $out ) && @$out['result'] == 'SUCCESS' )
    {
        pake_echo( "Build succesful" );
    }
    else
    {
        throw new pakeException( "Build failed or unknown status" );
    }
}

/**
 * We rely on the pake dependency system to do the real stuff
 * (run pake -P to see tasks included in this one)
 */
function run_dist( $task=null, $args=array(), $cliopts=array() )
{
}

function run_dist_init( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );

    $buildnr = @$cliopts['build'];
    if ( $buildnr == '' )
    {
        pake_echo( 'Fetching latest available build' );
        $buildnr = 'lastBuild';
    }

    // get list of files from the build
    $buildurl = $opts['jenkins']['url'] . '/job/' . $opts['jenkins']['job'] . '/' . $buildnr;
    $out = json_decode( pake_read_file( $buildurl . '/api/json' ), true );
    if ( !is_array( $out ) || !is_array( @$out['artifacts'] ) )
    {
        pake_echo( 'Error in retrieving build description from Jenkins or no artifacts in build' );
        return;
    }
    else
    {
        if ( $buildnr == 'lastBuild' )
        {
            pake_echo( 'Found build ' . $out['number'] );
        }
    }

    // find the correct variant
    $fileurl = '';
    foreach( $out['artifacts'] as $artifact )
    {
        if ( substr( $artifact['fileName'], -4 ) == '.zip' && strpos( $artifact['fileName'], 'with_ezc' ) !== false )
        {
            $fileurl = $buildurl . '/artifact/' . $artifact['relativePath'];
            break;
        }
    }
    if ( $fileurl == '' )
    {
        pake_echo( "No artifacts aviable for build $buildnr" );
        return;
    }
    // download and unzip the file
    $filename = sys_get_temp_dir() . '/' .  $artifact['fileName'];
    pake_write_file( $filename, pake_read_file( $fileurl ), 'cpb' );
    if ( !class_exists( 'ezcArchive' ) )
    {
        throw new pakeException( "Missing Zeta Components: cannot unzip downloaded file. Use the environment var PHP_CLASSPATH" );
    }
    // clean up the 'release' dir
    pake_remove_dir( $opts['dist']['dir'] );
    // and unzip eZ into it - in a folder with a specific name
    $zip = ezcArchive::open( $filename, ezcArchive::ZIP );
    $rootpath = $opts['build']['dir'] . '/release';
    $zip->extract( $rootpath );
    $currdir = pakeFinder::type( 'directory' )->in( $rootpath );
    $currdir = $subdir[0];
    $finaldir = $rootpath . '/' . eZPCPBuilder::getProjName();
    pake_rename( $currdir, $finaldir );
    pake_echo( "dir+         " . $finaldir );
}

function run_dist_wpi( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );
    if ( $opts['create']['mswpipackage'] )
    {
        if ( !class_exists( 'ezcArchive' ) )
        {
            throw new pakeException( "Missing Zeta Components: cannot generate tar file. Use the environment var PHP_CLASSPATH" );
        }
        pake_mkdirs( $opts['dist']['dir'] );
        $toppath = $opts['build']['dir'] . '/release';
        $rootpath = $toppath . '/' . eZPCPBuilder::getProjName();
        if ( $opts['create']['mswpipackage'] )
        {
            // add extra files to build
            /// @todo move this to another phase/task... ?

            $pakepath = dirname( __FILE__ ) . '/pake';
            pake_copy( $pakepath . '/wpifiles/install.sql', $toppath . '/install.sql' );

            /// @todo: if the $rootpath is different from "ezpublish", the manifest and parameters files need to be altered accordingly
            /// after copying them to their location
            pake_copy( $pakepath . '/wpifiles/manifest.xml', $toppath . '/manifest.xml' );
            pake_copy( $pakepath . '/wpifiles/parameters.xml', $toppath . '/parameters.xml' );

            // this one is overwritten
            pake_copy( $pakepath . '/wpifiles/kickstart.ini', $rootpath . '/kickstart.ini', array( 'override' => true ) );

            if ( is_file( $rootpath . '/web.config-RECOMMENDED' ) )
            {
                pake_copy( $rootpath . '/web.config-RECOMMENDED', $rootpath . '/web.config' );
            }
            else if ( !is_file( $rootpath . '/web.config' ) )
            {
                pake_copy( $pakepath . '/wpifiles/web.config', $rootpath . '/web.config' );
            }

            // create zip
            /// @todo if name is empty do not add an extra hyphen
            $filename = 'ezpublish-' . $opts[eZPCPBuilder::getProjName()]['name'] . '-' . $opts['version']['alias'] . '-wpi.zip';
            $target = $opts['dist']['dir'] . '/' . $filename;
            eZPCPBuilder::archiveDir( $toppath, $target, ezcArchive::ZIP, true );

            // update feed file
            $feedfile = 'ezpcpmswpifeed.xml';
            pake_copy( $pakepath . '/wpifiles/' . $feedfile, $opts['dist']['dir'] . '/' . $feedfile );
            $files = pakeFinder::type( 'file' )->name( $feedfile )->maxdepth( 0 )->in( $opts['dist']['dir'] );
            //pake_replace_regexp( $files, $opts['dist']['dir'], array(
            //) );
            pake_replace_tokens( $files, $opts['dist']['dir'], '{', '}', array(
                '$update_date' => gmdate( 'c' ),
                '$version' => $opts['version']['alias'],
                '$sha1' => sha1_file( $target ),
                '$filename' => $filename,
                '$filesizeKB' => round( filesize( $target ) / 1024 )
            ) );

        }
    }
}

/**
 * We rely on the pake dependency system to do the real stuff
 * (run pake -P to see tasks included in this one)
 */
function run_all( $task=null, $args=array(), $cliopts=array() )
{
}

function run_clean( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );
    pake_remove_dir( $opts['build']['dir'] );
}

function run_dist_clean( $task=null, $args=array(), $cliopts=array() )
{
    $opts = eZPCPBuilder::getOpts( $args );
    pake_remove_dir( $opts['dist']['dir'] );
}

/**
 * We rely on the pake dependency system to do the real stuff
 * (run pake -P to see tasks included in this one)
 */
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

function run_help( $task=null, $args=array(), $cliopts=array() )
{
    if ( count( $args ) == 0 || $args[0] == 'help' )
    {
        echo "To get detailed description of a taks, run: pake help \$task\n";
        echo "To see list of available tasks, run: pake -T\n";
        echo "To see list of tasks dependencies, run: pake -P\n";
        echo "To see more available options, run: pake -H\n";
    }
    else
    {
        try
        {
            $task = pakeTask::get( $args[0] );
            if ( isset( $GLOBALS['pake_longdesc'][$args[0]] ) && $GLOBALS['pake_longdesc'][$args[0]] != '' )
            {
                echo $GLOBALS['pake_longdesc'][$args[0]];
            }
            else
            {
                echo $task->get_comment();
            }
        }
        catch( exception $e )
        {
            echo "The task '{$args[0]}' is not available";
        }

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
    static $version = '0.2';
    static $min_pake_version = '1.6.1';
    static $projname = 'ezpublish';

    // leftover from ezextensionbuilder
    static function getProjName()
    {
        return self::$projname;
    }

    /**
    * Loads build options from config file.
    * nb: when called with a custom project name, sets it as current for subsequent calls too
    * @param array $options the 1st option is the version to be built. If given, it overrides the one in the config file
    * @return array all the options
    *
    * @todo remove support for a separate project name, as it is leftover from ezextensionbuilder
    */
    static function getOpts( $opts=array() )
    {
        $projname = self::getProjName();
        $projversion = @$opts[0];
        if ( !isset( self::$options[$projname] ) || !is_array( self::$options[$projname] ) )
        {
            self::loadConfiguration( "pake/options-$projname.yaml", $projname, $projversion );
        }
        return self::$options[$projname];
    }

    /// @bug this only works as long as all defaults are 2 levels deep
    static protected function loadConfiguration ( $infile='pake/options.yaml', $projname='', $projversion='' )
    {
        /// @todo review the list of mandatory options
        $mandatory_opts = array( 'ezpublish' => array( 'name' ), 'version' => array( 'major', 'minor', 'release' ) );
        $default_opts = array(
            'build' => array( 'dir' => 'build' ),
            'dist' => array( 'dir' => 'dist' ),
            'create' => array( 'mswpipackage' => true, /*'tarball' => false, 'zip' => false, 'filelist_md5' => true, 'doxygen_doc' => false, 'ezpackage' => false, 'pearpackage' => false*/ ),
            //'version' => array( 'license' => 'GNU General Public License v2.0' ),
            //'releasenr' => array( 'separator' => '.' ),
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
        if ( $projversion != '' )
        {
            $projversion = explode( '.', $projversion );
            $options['version']['major'] = $projversion[0];
            $options['version']['minor'] = isset( $projversion[1] ) ? $projversion[1] : '0';
            $options['version']['release'] = isset( $projversion[2] ) ? $projversion[2] : '0';
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
    * @return string the version nr. or the new version of the file (ie. its contents) , depending on input param (false in case of error)
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
    * Creates an archive out of a directory.
    * Requires the Zeta Components
    */
    static function archiveDir( $sourcedir, $archivefile, $archivetype, $no_top_dir=false )
    {
        if ( substr( $archivefile, -3 ) == '.gz' )
        {
            $zipext = 'gz';
            $target = substr( $archivefile, 0, -3 );
        }
        else if ( substr( $archivefile, -4 ) == '.bz2' )
        {
            $zipext = 'bz2';
            $target = substr( $archivefile, 0, -4 );
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
            $compress = 'zlib';
            if ( $zipext == 'bz2' )
            {
                $compress = 'bzip2';
            }
            $fp = fopen( "compress.$compress://" . ( $zipext == 'ezpkg' ? substr( $target, 0, -4 ) : $target ) . ".$zipext", 'wb9' );
            /// @todo read file by small chunks to avoid memory exhaustion
            fwrite( $fp, file_get_contents( $target ) );
            fclose( $fp );
            unlink( $target );
        }
        pake_echo_action( 'file+', $archivefile );
    }

    /**
     * Converts the matched lines from git log to changelog lines.
     * NB: this function is only parked here as a kind of "namespace", might find a better place fort it
     * @param array $matches PREG_PATTERN_ORDER array
     * @return array( changelogEntries )
     */
    static function gitLogMatchesAsEntries( $matches )
    {
        $entries = array();
        $indexedItems = array();

        // move to an array( bugid ) => text for sorting
        for( $i = 0, $c = count( $matches[0] ); $i < $c; $i++ )
        {
            $indexedItems[$matches[1][$i]] = $matches[2][$i];
        }
        ksort( $indexedItems );

        // format
        foreach ( $indexedItems as $id => $text )
        {
            $entries[] = "- #$id: $text";
        }

        return $entries;
    }

    /// generate name for changelog file.
    static function changelogFilename( $opts )
    {
        return 'CHANGELOG-' . self::previousVersionName( $opts) . '-to-' . $opts['version']['alias'] . '.txt';
    }

    /**
    * Returns the name of the previous version than the current one.
    * Assumes 2011.1 .. 2011.12 naming schema.
    * Partial support for 2012.1.2 schema (eg 2011.1.2 -> 2011.1.1 -> 2011.1 -> 20112.12)
    * User can define an alternative previous version in config file.
    * @bug what if previous of 2012.4 is 2012.3.9?
    */
    static function previousVersionName( $opts )
    {
        if ( isset( $opts['version']['previous']['name'] ) )
        {
            return  $opts['version']['previous']['name'];
        }
        else
        {
            if ( $opts['version']['release'] > 1 )
            {
                return $opts['version']['major'] . '.' . $opts['version']['minor'] . '.' . ( $opts['version']['release'] - 1 );
            }
            if ( $opts['version']['release'] == 1 )
            {
                return $opts['version']['major'] . '.' . $opts['version']['minor'];
            }
            if ( $opts['version']['minor'] > 1 )
            {
                return  $opts['version']['major'] . '.' . ( $opts['version']['minor'] - 1 );
            }
            else
            {
                return ( $opts['version']['major'] - 1 ) . '.12';
            }
        }
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

if ( !function_exists( 'pake_longdesc' ) )
{
    $GLOBALS['pake_longdesc'] = array();
    /**
     * Allows the user to define a long description for tasks, besides what is
     * done via pake_desk.
     * @param, string $desc If $description is empty, phpdoc for the function is used
     */
    function pake_longdesc( $task, $desc='' )
    {
        $func = 'run_' . str_replace( '-', '_', $task );
        if ( $desc == '' && function_exists( $func ) )
        {
            $func = new ReflectionFunction( $func );
            $desc = $func->getDocComment();
        }
        $GLOBALS['pake_longdesc'][$task] = $desc;
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

pake_desc( 'Downloads eZ sources from git' );
pake_task( 'init' );

pake_desc( 'Downloads the CI repo sources from git (needs to be run only once)' );
pake_task( 'init-ci-repo' );

pake_desc( 'Builds the cms. Options: --skip-init, --skip-changelog-pause, --skip-before-jenkins-pause' );
pake_task( 'build', 'init', 'generate-changelog', 'wait-for-changelog', 'generate-html-changelog', 'update-ci-repo', 'wait-for-continue', 'run-jenkins-build' );

pake_desc( 'Generates a changelog file from git logs' );
pake_task( 'generate-changelog' );

pake_desc( 'Dummy task. Asks a question to the user and waits for an answer...' );
pake_task( 'wait-for-changelog' );

pake_desc( 'Generate changelog files that can be copied and pasted into ezxml rich text' );
pake_task( 'generate-html-changelog' );

pake_desc( 'Commits changelog to the "ci" git repo and updates in there other files holding version-related infos' );
pake_task( 'update-ci-repo' );

pake_desc( 'Dummy task. Asks a question to the user and waits for an answer...' );
pake_task( 'wait-for-continue' );

pake_desc( 'Executes the build projects on Jenkins' );
pake_task( 'run-jenkins-build' );

pake_desc( 'Downloads the build tarballs from Jenkins for further repackaging. Options: --build=<buildnr>' );
pake_task( 'dist-init' );

pake_desc( 'Creates the MS WPI' );
pake_task( 'dist-wpi' );

pake_desc( 'Creates different versions of the build tarballs' );
pake_task( 'dist', 'dist-init', 'dist-wpi' );

pake_desc( 'Builds the cms and generates the tarballs' );
pake_task( 'all', 'build', 'dist' );

pake_desc( 'Removes the build/ directory' );
pake_task( 'clean' );

pake_desc( 'Removes the dist/ directory' );
pake_task( 'dist-clean' );

pake_desc( 'Removes the build/ and dist/ directories' );
pake_task( 'clean-all', 'clean', 'dist-clean' );

pake_desc( 'Checks if a newer version of the tool is available online' );
pake_task( 'tool-upgrade-check' );

pake_desc( 'Upgrades to the latest version of the tool available online' );
pake_task( 'tool-upgrade' );

pake_desc( 'Returns detailed description of existing tasks. Usage: php pakefile.php help $task' );
pake_task( 'help' );

}

?>
