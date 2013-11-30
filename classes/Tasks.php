<?php
/**
 * @author    G. Giunta
 * @author    N. Pastorino
 * @copyright (C) eZ Systems AS 2011-2013
 * @license   code licensed under the GNU GPL 2.0: see README file
 */

namespace eZPCPBuilder;

use pakeException;
use pakeFinder;
use pakeGit;

/**
 * The class implementing all pake tasks so far
 *
 * @todo split it into different subclasses related to different tasks such as docs, ci-server integration, ... ?
 */
class Tasks extends Builder
{

    /**
     * Shows help message
     */
    public static function run_default()
    {
        pake_echo ( "eZ Publish Community Project Builder ver." . self::VERSION . "\n" .
            "Syntax: ezpublishbuilder  [--\$pake-options] \$task [--\$general-options] [--\$task-options].\n" .
            "  General options:\n" .
            "    --config-dir=\$dir             to be used instead of ./pake\n" .
            "    --config-file=\$file           to be used instead of ./pake/options-\$ext.yaml\n" .
            "    --user-config-file=\$file      to be used instead of ./pake/options-user.yaml\n" .
            "    --option.\$option.\$name=\$value to override any configuration setting\n" .

            "  Run: \"ezpublishbuilder --tasks\" to list available tasks,\n" .
            "       \"ezpublishbuilder -P\" to list tasks dependencies,\n" .
            "       \"ezpublishbuilder help \$task\" to learn more on one specific task, or\n" .
            "       \"ezpublishbuilder help\" to learn about the options for pake." );
    }

    /**
     * Shows the properties from current configuration files and command-line options
     *
     * @todo also dump name of config file(s) in use
     */
    public static function run_show_properties( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );
        pake_echo ( print_r( $opts, true ) );
    }

    /**
     * Downloads eZP and other needed repos from their source repositories on github (needs to be run only once)
     *
     * Options: --skip-init, --skip-init-fetch
     * @todo add a dependency on a check-updates task that updates script itself?
     */
    public static function run_init( $task=null, $args=array(), $cliopts=array() )
    {
        $skip_init = @$cliopts['skip-init'];
        $skip_init_fetch = @$cliopts['skip-init-fetch'] || $skip_init;

        if ( ! $skip_init )
        {
            $opts = self::getOpts( $args, $cliopts );
            pake_mkdirs( $opts['build']['dir'] );

            $destdir = self::getSourceDir( $opts );
        }

        // if target dir is not empty, force user to run a "clean"
        if ( is_dir( $destdir ) )
        {
            $leftovers = pakeFinder::type( 'any' )->maxdepth( 1 )->in( $destdir );
            if ( count( $leftovers ) )
            {
                throw new pakeException( "Can not download eZ Publish sources into directory $destdir because it is not empty. Please run task 'clean' to wipe it then retry" );
            }
        }

        if ( ! $skip_init_fetch )
        {
            foreach( array( 'legacy', 'kernel', 'community' ) as $repo )
            {

                pake_echo( "Fetching code from eZ Publish $repo GIT repository" );

                if ( @$opts['git'][$repo]['url'] == '' )
                {
                    throw new pakeException( "Missing source repo option git:$repo:url in config file" );
                }

                /// @todo to make successive builds faster - if repo exists already just
                ///       update it
                $r = pakeGit::clone_repository( $opts['git'][$repo]['url'], self::getSourceDir( $opts, $repo ) );

                /// @todo set up username and password in /.git/config
                ///       from either config file or interactive mode, to later allow tagging

                if ( @$opts['git'][$repo]['branch'] != '' )
                {
                    pake_echo( "Using GIT branch {$opts['git'][$repo]['branch']}" );
                    $r->checkout( $opts['git'][$repo]['branch'] );
                }

            }
        }
    }

    /**
     * Downloads the CI repo sources from github (needs to be run only once)
     */
    public static function run_init_ci_repo( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        $skip_init = @$cliopts['skip-init'];
        if ( $skip_init )
        {
            return;
        }

        if ( @$opts['git']['ci-repo']['local-path'] == '' )
        {
            throw new pakeException( "Missing option git:ci-repo:local-path in config file: can not download CI repo" );
        }
        $destdir = $opts['git']['ci-repo']['local-path'];

        pake_echo( 'Fetching sources from CI git repository' );

        // if target dir is not empty, force user to run a "clean"
        if ( is_dir( $destdir ) )
        {
            $leftovers = pakeFinder::type( 'any' )->maxdepth( 1 )->in( $destdir );
            if ( count( $leftovers ) )
            {
                throw new pakeException( "Can not download CI repo into directory $destdir because it is not empty. Please run task clean-ci-repo to wipe it then retry" );
            }
        }

        /// @todo to make successive builds faster - if repo exists already just
        ///       update it
        $repo = pakeGit::clone_repository( $opts['git']['ci-repo']['url'], $destdir );

        // q: is this really needed?
        if ( $opts['git']['ci-repo']['branch'] != '' )
        {
            $repo->checkout( $opts['ci-repo']['branch'] );
        }

        /// @todo set up username and password in $opts['git']['ci-repo']['branch']/.git/config
        ///       from either config file or interactive mode

        pake_echo( "The CI git repo has been set up in $destdir\n" .
            "You should now set up properly your git user account and make sure it has\n".
            "permissions to commit to it" );
    }

    /**
     * Builds the cms; options: --skip-init, --skip-changelog-pause, --skip-before-jenkins-pause.
     *
     * We rely on the pake dependency system to do the real stuff
     * (run pake -P to see tasks included in this one)
     */
    public static function run_build( $task=null, $args=array(), $cliopts=array() )
    {
    }

    /**
     * Updates local eZP sources (git pull from github)
     */
    public static function run_update_source( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        $skip_update = @$cliopts['skip-update-source'];
        if ( $skip_update )
        {
            return;
        }

        $git = self::getTool( 'git', $opts );

        foreach( array( 'legacy', 'community', 'kernel' ) as $repo )
        {
            pake_echo( "Updating source code from eZ Publish $repo GIT repository" );

            $rootpath = self::getSourceDir( $opts, $repo );

            // 1. check if build dir is correctly linked to source git repo
            /// @todo use pakeGit::remotes() instead of this code
            $remotesArray = preg_split( '/(\r\n|\n\r|\r|\n)/', pake_sh( self::getCdCmd( $rootpath ) . " && $git remote -v" ) );
            foreach( $remotesArray as $key => $val )
            {
                if ( $val == '' )
                {
                    unset( $remotesArray[$key] );
                }
            }
            $originf = false;
            foreach( $remotesArray as $remote )
            {
                if ( strpos( $remote, $opts['git'][$repo]['url'] . ' (fetch)' ) !== false || (
                        strpos( $remote, $opts['git'][$repo]['url'] ) !== false && count( $remotesArray ) == 1 ) )
                {
                    $originf = explode( ' ', $remote );
                    $originf = $originf[0];
                }
            }
            if ( !$originf )
            {
                throw new pakeException( "Build dir $rootpath does not seem to be linked to git repo {$opts['git'][$repo]['url']}" );
            }

            // 2. pull and move to correct branch
            $repo = new pakeGit( $rootpath );

            /// @todo test that the branch switch does not fail
            if ( @$opts['git'][$repo]['branch'] != '' )
            {
                $repo->checkout( $opts['git'][$repo]['branch'] );
            }
            else
            {
                $repo->checkout( 'master' );
            }

            /// @todo test that the pull does not fail
            $repo->pull();

            /// remember to fetch all tags as well
            pake_sh( self::getCdCmd( $rootpath ) . " && $git fetch --tags" );

            pake_echo ( 'Last commit: ' . pake_sh( self::getCdCmd( $rootpath ) . " && git log -1 --format=\"%H\"" ) );
        }
    }

    /**
     * Displays current revision of local eZP sources
     */
    public static function run_display_source_revision( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        foreach( array( 'legacy', 'community', 'kernel' ) as $repo )
        {
            pake_echo( "eZ Publish $repo GIT repository" );

            $rootpath = self::getSourceDir( $opts, $repo );

            $commit = pake_sh( self::getCdCmd( $rootpath ) . " && git log -1 --format=\"%H\"" );
            $date = pake_sh( self::getCdCmd( $rootpath ) . " && git log -1 --format=\"%ci\"" );
            pake_echo ( 'Last commit: ' . trim(  $commit ) );
            pake_echo ( 'Merge date: ' . trim( $date ) . "\n" );
        }
    }

    /**
     * Finds (and displays) info about the previous release: git revision etc
     */
    public static function run_display_previous_release( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        $changelogEntries = array();
        foreach( self::getPreviousRevisions( array( 'legacy', 'community', 'kernel' ), $opts )  as $repo => $previousrev )
        {
            $rootpath = self::getSourceDir( $opts, $repo );

            $date = pake_sh( self::getCdCmd( $rootpath ) . " && git log -1 --format=\"%ci\" $previousrev" );
            pake_echo ( "Repository: $repo" );
            pake_echo ( "Commit: $previousrev" );
            pake_echo ( 'Merge date: ' . trim( $date ) . "\n" );
        }
    }

    /**
     * Generates a changelog file based on git commit logs; options: --skip-update-source
     *
     * The generated file is placed in the correct folder within doc/changelogs.
     * It should be reviewed/edited by hand, then committed with the task "update-ci-repo".
     */
    public static function run_generate_changelog( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        $changelogEntries = array();
        foreach( self::getPreviousRevisions( array( 'legacy', 'community', 'kernel' ), $opts ) as $repo => $previousrev )
        {
            $rootpath = self::getSourceDir( $opts, $repo );

            pake_echo ( "\nExtracting changelog entries from git log for $repo" );
            $changelogEntries[$repo] = self::extractChangelogEntriesFromRepo( $rootpath, $previousrev );
        }

        pake_echo ( "\nGenerating changelog in txt format" );
        foreach( array( 'legacy', 'kernel', 'community' ) as $repo )
        {

            /// @todo handle reverts ? Or process manually ?
            $header = self::getEzPublishHeader( $repo );
            $ezpublishHeader[$repo] = $header;
            $ezpublishHeader[$repo] .= "\n" . str_pad( '', strlen( $header ), '-' ) . "\n";
        }

        $out = '';
        foreach( array(
                     'bugfixesMatches' => 'Bugfixes',
                     'enhancementsMatches' => 'Enhancements',
                     'pullreqsMatches' => 'Pull requests',
                     'unmatchedEntries' => 'Miscellaneous'
                 ) as $type => $name )
        {
            $out .= "$name\n" . str_pad( '', strlen( $name ), '=' ) . "\n\n";
            foreach( array( 'community', 'legacy', 'kernel' )  as $repo )
            {
                $out .= $ezpublishHeader[$repo];
                $out .= join( "\n", self::gitLogMatchesAsEntries( $changelogEntries[$repo][$type] ) );
                $out .= "\n\n";
            }
        }

        $changelogdir = self::changelogDir( $opts );
        $filename = self::changelogFilename( $opts );
        pake_mkdirs( $changelogdir );
        pake_write_file( $changelogdir . '/' . $filename , $out, true );

        pake_echo( "\nNow go and edit file " . $changelogdir . '/' . $filename );

    }

    public static function run_wait_for_changelog( $task=null, $args=array(), $cliopts=array() )
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

    /**
     * Generate changelog files that can be copied and pasted into ezxml rich text; NOT FINISHED YET
     */
    public static function run_generate_html_changelog( $task=null, $args=array(), $cliopts=array() )
    {
        pake_echo( "WARNING - experimental" );

        $opts = self::getOpts( $args, $cliopts );
        $rootpath = self::getSourceDir( $opts,'legacy' );
        $changelogdir = self::changelogDir( $opts );
        $filename = self::changelogFilename( $opts );

        $file = pake_read_file( $changelogdir . '/' . $filename );
        $htmlfile = array();
        $mode = null;
        $githubMode = null;
        foreach( explode( "\n", $file ) as $line )
        {
            switch( $line )
            {
                case "Bugfixes":
                    $mode = 'wit-jira';
                    $htmlfile[] = '<h3>' . $line . "</h3>\n<ul>";
                    break;
                case "Enhancements":
                    $mode = 'wit-jira';
                    $htmlfile[] = "</ul>\n<h3>" . $line . "</h3>\n<ul>";
                    break;
                case "Pull requests":
                    $mode = 'github';
                    $htmlfile[] = "</ul>\n<h3>" . $line . "</h3>\n<ul>";
                    break;
                case self::getEzPublishHeader( 'community' ):
                    if ( $mode == "github" )
                    {
                        $githubMode = '5';
                    }
                    $htmlfile[] = "</ul>\n<h4>" . $line . "</h4>\n<ul>";
                    break;
                case self::getEzPublishHeader(  'legacy' ):
                    if ( $mode == "github" )
                    {
                        $githubMode = 'LS';
                    }
                    $htmlfile[] = "</ul>\n<h4>" . $line . "</h4>\n<ul>";
                    break;
                case self::getEzPublishHeader(  'kernel' ):
                    if ( $mode == "github" )
                    {
                        $githubMode = 'Api';
                    }
                    $htmlfile[] = "</ul>\n<h4>" . $line . "</h4>\n<ul>";
                    break;
                case "Miscellaneous":
                    $mode = null;
                    $htmlfile[] = "</ul>\n<h3>" . $line . "</h3>\n<ul>";
                    break;
                default:
                    if ( trim( $line ) == '' || preg_match( '/^=+$/', $line ) || preg_match( '/^-+$/', $line ) )
                    {
                        continue;
                    }
                    switch( $mode )
                    {
                        case 'wit-jira':
                            $line = preg_replace( '/^- /', '', $line );
                            $line = preg_replace( '/#(\d+):/', '<a target="_blank" href="http://issues.ez.no/$1">$1</a>:', htmlspecialchars( $line ) );
                            $line = preg_replace(  '/(EZP-\d+):/', '<a target="_blank" href="https://jira.ez.no/browse/$1">$1</a>:', $line );
                            break;
                        case 'github':
                            $line = preg_replace( '/^- /', '', $line );

                            switch ( $githubMode )
                            {
                                case '5':
                                    $repoUrlPart = "ezpublish-community";
                                    break;
                                case 'Api':
                                    $repoUrlPart = "ezpublish-kernel";
                                    break;
                                case 'LS':
                                    $repoUrlPart = "ezpublish-legacy";
                                    break;
                            }

                            $line = preg_replace( '/^(\d+):/', '<a target="_blank" href="https://github.com/ezsystems/' . $repoUrlPart . '/pull/$1">$1</a>:', htmlspecialchars( $line) );
                            break;
                        default;
                            $line = preg_replace( '/^- /', '', htmlspecialchars( $line ) );
                            break;

                    }
                    $htmlfile[] = '<li>' . $line . '</li>';
            }
        }
        $htmlfile[] = '</ul>';
        $htmlfile = implode( "\n", $htmlfile );

        pake_echo( $htmlfile );
    }

    /**
     * Generate changelog files that can be copied and pasted into ezxml rich text; NOT FINISHED YET
     */
    public static function run_generate_html_credits( $task=null, $args=array(), $cliopts=array() )
    {
        pake_echo( "WARNING - experimental" );

        $opts = self::getOpts( $args, $cliopts );
        $rootpath = self::getSourceDir( $opts, 'legacy' );
        $changelogdir = self::changelogDir( $opts );
        $filename = self::changelogFilename( $opts );

        $file = pake_read_file( $changelogdir . '/' . $filename );
        $htmlfile = array( '<h3>Many thanks for your pull-requests on the eZ Publish repositories, and on extensions :</h3>', '<ul>' );
        $mode = null;
        $uniqueContributors = array();
        foreach( explode( "\n", $file ) as $line )
        {
            switch( $line )
            {
                case "Bugfixes":
                case "Enhancements":
                    $mode = 'wit';
                    break;
                case "Pull requests":
                    $mode = 'github';
                    break;
                case "Miscellaneous":
                    $mode = null;
                    break;
                default:
                    if ( trim( $line ) == '' || preg_match( '/^=+$/', $line ) || preg_match( '/^-+$/', $line ) )
                    {
                        continue;
                    }
                    switch( $mode )
                    {
                        case 'github':
                            if ( preg_match( '#from (.+)#', $line, $matches ) )
                            {
                                /// @todo add link
                                $uniqueContributors[urlencode( $matches[1] )] = htmlspecialchars( $matches[1] );
                            }
                            break;
                        default;
                            break;
                    }
            }
        }
        foreach ( $uniqueContributors as $key => $contributor )
        {
            $htmlfile[] = '<li><a target="_blank" href="https://github.com/' . $key . '/">' . $contributor . '</a></li>';
        }

        $htmlfile[] = '</ul>';
        $htmlfile = implode( "\n", $htmlfile );

        pake_echo( $htmlfile );
    }

    /**
     * Updates the local copy of the CI repo (pull from github)
     */
    public static function run_update_ci_repo_source( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        $skip_update = @$cliopts['skip-update-ci-repo-source'];
        if ( $skip_update )
        {
            return;
        }

        pake_echo( "Updating source code from CI GIT repository" );

        $cipath = self::getSourceDir( $opts, 'ci-repo' );

        $git = self::getTool( 'git', $opts );

        // test that we're on the good git
        /// @todo use pakeGit::remotes() instead of this code
        $remotesArray = preg_split( '/(\r\n|\n\r|\r|\n)/', pake_sh( self::getCdCmd( $cipath ) . " && $git remote -v" ) );
        foreach( $remotesArray as $key => $val )
        {
            if ( $val == '' )
            {
                unset( $remotesArray[$key] );
            }
        }
        $originp = false;
        $originf = false;

        foreach( $remotesArray as $remote )
        {
            if ( strpos( $remote, $opts['git']['ci-repo']['url'] . ' (push)' ) !== false )
            {
                $originp = preg_split( '/[ \t]/', $remote );
                $originp = $originp[0];
                // dirty, dirty hack
                $GLOBALS['originp'] = $originp;
            }
            if ( strpos( $remote, $opts['git']['ci-repo']['url'] . ' (fetch)' ) !== false || (
                    strpos( $remote, $opts['git']['ci-repo']['url'] ) !== false && count( $remotesArray ) == 1 ) )
            {
                $originf = preg_split( '/[ \t]/', $remote );
                $originf = $originf[0];
            }
        }
        if ( !$originp || !$originf )
        {
            throw new pakeException( "CI repo dir $cipath does not seem to be linked to git repo {$opts['git']['ci-repo']['url']}" );
        }
        $repo = new pakeGit( $cipath );

        if ( $opts['git']['ci-repo']['branch'] != '' )
        {
            /// @todo test that the branch switch does not fail
            $repo->checkout( $opts['git']['ci-repo']['branch'] );
        }

        /// @todo test that the pull does not fail: do a git status and check for differences
        $repo->pull();
    }

    /**
     * Commits changelog to the "ci" git repo and updates in there other files holding version-related infos; options: skip-update-ci-repo-source
     * As part of this task, the local copy of the "ci" git repo is updated from upstream.
     *
     * The "ci" repo is used by the standard eZ Publish build process, driven by Jenkins.
     * It holds, amongs other things, patch files that are applied in order to build the
     * CP version instead of the Enterprise one
     */
    public static function run_update_ci_repo( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );
        $rootpath = self::getSourceDir( $opts, 'legacy' );

        // start work on the ci repo:

        $cipath = self::getSourceDir( $opts, 'ci-repo' );
        $git = self::getTool( 'git', $opts );

        // 1. update ci repo - moved to a separate task

        // dirty, dirty hack
        $originp = @$GLOBALS['originp'];

        $repo = new pakeGit( $cipath );

        if ( $opts['git']['ci-repo']['path'] != '' )
        {
            $cipath .= '/' . $opts['git']['ci-repo']['path'];
            $localcipath = $opts['git']['ci-repo']['path'] . '/';
        }
        else
        {
            $localcipath = '';
        }

        // 1b. check that there is no spurious stuff in changelog dir, or step 3 later will create bad patch files.
        //     we do this here to avoid adding ANY file to local copy of CI git repo and abort asap
        $changelogdir = 'doc/changelogs/Community_Project-' . $opts['version']['major'];
        $files = pakeFinder::type( 'file' )->maxdepth( 0 )->relative()->in( self::getSourceDir( $opts, 'legacy' ) . '/' . $changelogdir );
        if ( count( $files ) != 1 )
        {
            throw new pakeException( "More than one changelog file (or none) found in directory $changelogdir, can not generate patch file for CI repo" );
        }

        // 2. update 0002_2011_11_patch_fix_version.diff file

        $files1 = pakeFinder::type( 'file' )->name( '0002_2011_11_patch_fix_version.diff' )->maxdepth( 0 )->relative()->in( $cipath . '/patches' );
        $files2 = pakeFinder::type( 'file' )->name( '0003_2011_11_patch_fix_package_repository.diff' )->maxdepth( 0 )->relative()->in( $cipath . '/patches' );
        /// @todo what if those files are gone?
        $patchfile1 = $cipath . '/patches/' . $files1[0];
        $patchfile2 = $cipath . '/patches/' . $files2[0];

        // if a new major version has been released, the '0002_2011_11_patch_fix_version.diff' patch will not apply,
        // and the '0003_2011_11_patch_fix_package_repository.diff' file will have to be altered as well
        // we need thus to regenerate them (more details: https://docs.google.com/a/ez.no/document/d/1h5n3aZdXbyo9_iJoDjoDs9a6GdFZ2G-db9ToK7J1Gck/edit?hl=en_GB)

        // 1st, we test that the current patch file will apply cleanly (if it does, we assume no new EE release)
        $patch = self::getTool( 'patch', $opts );
        $patcherror = false;
        try
        {
            $patchResult = pake_sh( self::getCdCmd( $rootpath ) . " && $patch --dry-run -p0 < " . $patchfile1 );
        }
        catch( Exception $e )
        {
            $patcherror = $e->getMessage();
        }

        // then, we (try to) recover version info from existing patch files
        $patch1 = file_get_contents( $patchfile1 );
        $patch2 = file_get_contents( $patchfile2 );
        if (
            preg_match( '/^\- +const +VERSION_MAJOR += +(\d+);/m', $patch1, $m1 ) &&
            preg_match( '/^\- +const +VERSION_MINOR += +(\d+);/m', $patch1, $m2 ) &&
            preg_match( '/^\+RemotePackagesIndexURL=http:\/\/packages.ez.no\/ezpublish\/[^\/]+\/(.+)$/m', $patch2, $m3 )
        )
        {
            $oldNextVersionMajor = $m1[1];
            $oldNextVersionMinor = $m2[1];
            $currentVersion = $m3[1];
            // give some information to user
            pake_echo( "\nPackages available during setup wizard execution for this CP build will be the ones from eZP $currentVersion" );
            pake_echo( "The next build of eZ Publish EE is expected to be $oldNextVersionMajor.$oldNextVersionMinor\n" );

            // last, we try automated fixing or abort
            if ( $patcherror )
            {
                // try to gather enough information to fix automatically the patch files
                $currfile = file_get_contents( $rootpath . '/lib/version.php' );
                if (
                    preg_match( '/^\ +const +VERSION_MAJOR += +(\d+);/m', $currfile, $m1 ) &&
                    preg_match( '/^\ +const +VERSION_MINOR += +(\d+);/m', $currfile, $m2 ) )
                {
                    $newNextVersionMajor = $m1[1];
                    $newNextVersionMinor = $m2[1];
                    if ( $newNextVersionMajor == $oldNextVersionMajor && $newNextVersionMinor == $oldNextVersionMinor )
                    {
                        // patch does not apply but version number was not changed. Abort
                        throw new pakeException( "The diff file $patchfile1 does not apply correctly, build will fail. Please fix it, commit and push.\n Also check to fix if needed the url to packages repo in 0003_2011_11_patch_fix_package_repository.diff.\nError details:\n" . $patcherror );
                    }
                    pake_echo( "It seems that EE version $oldNextVersionMajor.$oldNextVersionMinor was released, next expected EE version is currently $newNextVersionMajor.$newNextVersionMinor" );
                    pake_echo( "This means that the diff file $patchfile1 does not apply correctly, the build will fail." );
                    pake_echo( "The script can fix this automatically, or you will have to fix patch files by hand (at least 2 of them)" );
                    pake_echo( "Proposed changes:" );
                    pake_echo( "Packages available during setup wizard execution for this CP build will be the ones from eZP $oldNextVersionMajor.$oldNextVersionMinor.0" );
                    $ok = pake_input( "Do you want to continue with automatic fixing? [y/n]", 'n' );
                    if ( $ok != 'y' )
                    {
                        throw new pakeException( "Please fix patch file by hand, commit and push.\nAlso remember to fix the url to packages repo in 0003_2011_11_patch_fix_package_repository.diff" );
                    }
                    else
                    {
                        pake_replace_regexp( $files1, $cipath . '/patches', array(
                                '/^- +const +VERSION_MAJOR += +\d+;/m' => "+    const VERSION_MAJOR = $newNextVersionMajor;",
                                '/^- +const +VERSION_MINOR += +\d+;/m' => "+    const VERSION_MINOR = $newNextVersionMinor;"
                            ) );
                        pake_replace_regexp( $files2, $cipath . '/patches', array(
                                '/^\+RemotePackagesIndexURL=http:\/\/packages.ez.no\/ezpublish\/([^\/]+)\/.*$/m' => "+RemotePackagesIndexURL=http://packages.ez.no/ezpublish/$oldNextVersionMajor.$oldNextVersionMinor/$oldNextVersionMajor.$oldNextVersionMinor.0"
                            ) );
                    }
                }
                else
                {
                    throw new pakeException( "The diff file $patchfile1 does not apply correctly, build will fail. Please fix it, commit and push.\n Also remember to fix the url to packages repo in 0003_2011_11_patch_fix_package_repository.diff.\nError details:\n" . $patcherror );
                }
            }

        }
        else
        {
            if ( $patcherror )
            {
                throw new pakeException( "The diff file $patchfile1 does not apply correctly, build will fail. Please fix it, commit and push.\n Also remember to fix the url to packages repo in 0003_2011_11_patch_fix_package_repository.diff.\nError details:\n" . $patcherror );
            }
            else
            {
                /// @todo waht to do here? warn user and give him a chance to abort...
            }
        }

        // finally, the changes which apply every time
        pake_replace_regexp( $files1, $cipath . '/patches', array(
                '/^\+ +const +VERSION_MAJOR += +\d+;/m' => "+    const VERSION_MAJOR = {$opts['version']['major']};",
                '/^\+ +const +VERSION_MINOR += +\d+;/m' => "+    const VERSION_MINOR = {$opts['version']['minor']};"
            ) );
        $repo->add( array( $localcipath . 'patches/0002_2011_11_patch_fix_version.diff' ) );

        // 3. generate changelog diff

        // get absolute path to build dir
        $absrootpath = pakeFinder::type( 'directory' )->name( self::getProjName() )->in( $opts['build']['dir'] . '/source' );
        $absrootpath = dirname( $absrootpath[0] );
        $difffile = $absrootpath . '/' . $opts['version']['alias'] . '_patch_fix_changelog.diff';

        pake_sh( self::getCdCmd( $rootpath ) . " && $git add " . escapeshellarg( $changelogdir ) );

        pake_sh( self::getCdCmd( $rootpath ) . " && $git diff --no-prefix --staged -- " . escapeshellarg( $changelogdir ) . " > " . escapeshellarg( $difffile ) );

        /// unstage the file
        pake_sh( self::getCdCmd( $rootpath ) . " && $git reset HEAD --" );

        // 4. add new changelog file
        /// calculate sequence nr.
        $max = 0;
        $files = pakeFinder::type( 'file' )->maxdepth( 0 )->in( $cipath . '/patches' );
        foreach( $files as $file )
        {
            $nr = (int)substr( basename( $file ), 0, 4 );
            if ( $nr > $max )
            {
                $max = $nr;
            }
        }
        $seqnr = str_pad( (  $max + 1 ), 4, '0', STR_PAD_LEFT );
        $newdifffile = $seqnr .'_' . str_replace( '.', '_', $opts['version']['alias'] ) . '_patch_fix_changelog.diff';

        // what if there is already a patch file in the ci repo which creates the changelog we are adding a patch file for?
        if ( preg_match_all( '/^\+\+\+ (.*)$/m', file_get_contents( $difffile ), $matches ) )
        {
            $added = $matches[1];
            //echo "adding: "; var_dump( $added );
            $patchfiles = pakeFinder::type( 'file' )->name( '*.diff' )->maxdepth( 0 )->in( $cipath . '/patches' );
            foreach( $patchfiles as $patchfile )
            {
                if ( preg_match_all( '/^\+\+\+ (.*)$/m', file_get_contents( $patchfile ), $matches ) )
                {
                    //echo "already added: "; var_dump( $matches[1] );
                    if ( array_intersect( $added, $matches[1] ) )
                    {
                        $cont = pake_select_input( "The patch file\n  $patchfile\nseems to already create the same files wew want to create using a new patch file:\n  $newdifffile\n".
                            "This is a sign of a probable error somewhere. Press '1' to continue the build task anyway. Otherwise press '2' to exit.",
                            array( 'Continue', 'Stop' ), 1 );
                        if ( $cont != 'Y' )
                        {
                            exit;
                        }
                    }
                }
            }
        }

        pake_copy( $difffile, $cipath . '/patches/' . $newdifffile, array( 'override' => true ) );
        $repo->add( array( $localcipath . 'patches/' . $newdifffile ) );

        // 5. update ezpublish-gpl.properties
        $files = pakeFinder::type( 'file' )->name( 'ezpublish-gpl.properties' )->maxdepth( 0 )->relative()->in( $cipath . '/properties' );
        pake_replace_regexp( $files, $cipath . '/properties', array(
                '/^ezp\.cp\.version\.major += +.+$/m' => "ezp.cp.version.major = {$opts['version']['major']}",
                '/^ezp\.cp\.version\.minor += +.+$/m' => "ezp.cp.version.minor = {$opts['version']['minor']}"
            ) );
        $repo->add( array( $localcipath . 'properties/ezpublish-gpl.properties' ) );

        // 5. commit changes and push to upstream
        $repo->commit( 'Prepare files for build of CP ' . $opts['version']['alias'] );

        /// @todo allow the user to specify this on the command line
        if ( $originp == '' )
        {
            pake_echo( "WARNING: Using \"origin\" as name of upstream repo as actual name not found" );
            $originp = "origin";
        }

        pake_sh( self::getCdCmd( $cipath ) . " && $git push $originp {$opts['git']['ci-repo']['branch']}:{$opts['git']['ci-repo']['branch']}" );
    }

    public static function run_wait_for_continue( $task=null, $args=array(), $cliopts=array() )
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

    /**
     * Executes the build project on Jenkins (ezp4)
     */
    public static function run_run_jenkins_build4( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        self::jenkinsBuild( $opts['jenkins']['jobs']['legacy'], $opts );
    }

    /**
     * Checks if the build of the 'ezpublish5-community' job on jenkins is fine
     */
    public static function run_check_jenkins_build5pre( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        pake_echo( "Checking status of jenkins job '{$opts['jenkins']['jobs']['kernel']}'" );
        $buildurl = 'job/' . $opts['jenkins']['jobs']['kernel'] . '/api/json';
        $out = self::jenkinsCall( $buildurl, $opts ); // true
        if ( !is_array( $out ) || !array_key_exists( 'builds', $out ) )
        {
            throw new pakeException( "Error in retrieving job status" );
        }
        foreach( $out['builds'] as $build )
        {
            $buildNr = $build['number'];
            pake_echo( "Found build $buildNr checking its status" );
            $buildurl = 'job/' . $opts['jenkins']['jobs']['kernel'] . '/' . $buildNr . '/api/json';
            $out = self::jenkinsCall( $buildurl, $opts );
            if ( !is_array( $out ) || !array_key_exists( 'result', $out ) )
            {
                throw new pakeException( "Error in retrieving build status" );
            }
            if ( $out['result'] != 'SUCCESS' )
            {
                throw new pakeException( "build was not succesful" );
            }

            // check if this build happened later than the last commit to the community repo
            $ts =  round( $out['timestamp'] / 1000 );

            $git = self::getTool( 'git', $opts );
            $rootpath = self::getSourceDir( $opts, 'community' );
            $lastCommit = trim( pake_sh( self::getCdCmd( $rootpath ) . " && $git log -1 --format=\"%ct\"" ) );

            if ( $ts < $lastCommit )
            {
                throw new pakeException( "build was done before last repo modification. Please run a new build, task: run-jenkins-build5pre" );
            }

            echo "Build timestamp $ts is later than repo timestamp $lastCommit : all ok\n";
            break;
        }
    }

    /**
     * Runs build of the 'ezpublish5-community' job on jenkins is fine
     */
    public static function run_run_jenkins_build5pre( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        self::jenkinsBuild( $opts['jenkins']['jobs']['kernel'], $opts, array( 'VERSION' => '' ) );
    }

    /**
     * Executes the build project on Jenkins (ezpublish5-full-community)
     */
    public static function run_run_jenkins_build5( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        self::jenkinsBuild( $opts['jenkins']['jobs']['community'], $opts, array( 'VERSION' => $opts['version']['alias'] ) );
    }

    /**
     * Pushes to github repositories a tag with the current version name. TO BE TESTED
     * @todo allow to tag older revisions than HEAD
     */
    public static function run_tag_github_repos( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        // $> git tag -a -m "Community Project build 2012.11" "2012.11"
        // $> git  push  --tags
        $git = self::getTool( 'git', $opts );

        foreach( array( 'legacy', 'kernel', 'community' ) as $repo )
        {
            pake_echo( "Adding tag to eZ Publish $repo GIT repository" );

            $rootpath = self::getSourceDir( $opts, $repo );

            pake_sh( self::getCdCmd( $rootpath ) . " && $git tag -a -m \"Community Project build {$opts['version']['alias']}\" \"" . self::tagNameFromVersionName( $opts['version']['alias'], $opts ) ."\" && $git push --tags " );
        }
    }

    /**
     * Pushes to Jenkins job builds a tag with the current version name. TO BE DONE
     */
    public static function run_tag_jenkins_builds( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        Throw new pakeException( "This task has yet to be implemented" );
    }

    /**
     * Generates php-api docs of the build, for the Legacy Stack (optionally can be run on pubsvn.ez.no)
     *
     * Prerequisite task: dist-init
     * Options:
     *   --sourcedir=<...> dir with eZ sources, defaults to build/release/ezpublish (from config. file)
     *   --docsdir=<...> dir where docs will be saved, default to build/apidocs/ezpublish/<tool>/ (from config. file)
     *
     * @todo warn user and abort if target directories for doc are not empty
     */
    public static function run_generate_apidocs_LS( $task=null, $args=array(), $cliopts=array() )
    {
        self::run_generate_apidocs_generic( 'LS', $task, $args, $cliopts );
    }

    /**
     * Generates php-api docs of the build, for the New Stack (optionally can be run on pubsvn.ez.no)
     *
     * Prerequisite task: dist-init
     * Options:
     *   --sourcedir=<...> dir with eZ sources, defaults to build/release/ezpublish (from config. file)
     *   --docsdir=<...> dir where docs will be saved, default to build/apidocs/ezpublish/<tool>/ (from config. file)
     */
    public static function run_generate_apidocs_NS( $task=null, $args=array(), $cliopts=array() )
    {
        self::run_generate_apidocs_generic( 'NS', $task, $args, $cliopts );
    }

    /**
     * Generates php-api docs of the build, for 4.X series (optionally can be run on pubsvn.ez.no)
     *
     * Prerequisite task: dist-init
     * Options:
     *   --sourcedir=<...> dir with eZ sources, defaults to build/release/ezpublish (from config. file)
     *   --docsdir=<...> dir where docs will be saved, default to build/apidocs/ezpublish/<tool>/ (from config. file)
     */
    public static function run_generate_apidocs_4X( $task=null, $args=array(), $cliopts=array() )
    {
        self::run_generate_apidocs_generic( '4X', $task, $args, $cliopts );
    }

    /**
     * @todo allow via CLI to specify dir for tarballs
     * @todo simplify management of title for docs: just get it whole from configs...
     * @todo split this monster in smaller pieces
     */
    public static function run_generate_apidocs_generic( $stack, $task=null, $args=array(), $cliopts=array() )
    {

        $opts = self::getOpts( $args, $cliopts );
        $sourcedir = @$cliopts['sourcedir'];
        $docsdir = @$cliopts['docsdir'];

        switch( $stack )
        {
            case 'LS':
                $excludedirs = $opts['docs']['exclude_dirs']['legacy_stack'];
                if ( $sourcedir == '' )
                {
                    $sourcedir = $opts['build']['dir'] . '/release/' . self::getProjName() . '/ezpublish_legacy';
                }
                else
                {
                    $sourcedir .= '/ezpublish_legacy';
                }
                if ( $docsdir == '' )
                {
                    $docsdir = $opts['build']['dir'] . '/apidocs/' . self::getProjName() . '/ezpublish_legacy';
                }
                $files = pakeFinder::type( 'file' )->name( 'autoload.php' )->maxdepth( 0 )->in( $sourcedir );
                $namesuffix = $opts['docs']['name_suffix']['legacy_stack'];
                break;
            case '4X':
                $excludedirs = $opts['docs']['exclude_dirs']['legacy_stack'];
                if ( $sourcedir == '' )
                {
                    $sourcedir = $opts['build']['dir'] . '/release/' . self::getProjName();
                }
                if ( $docsdir == '' )
                {
                    $docsdir = $opts['build']['dir'] . '/apidocs/' . self::getProjName();
                }
                $files = pakeFinder::type( 'file' )->name( 'runcronjobs.php' )->maxdepth( 0 )->in( $sourcedir );
                $namesuffix = $opts['docs']['name_suffix']['4x_stack'];
                break;
            default:
                $stack = 'NS';
                $excludedirs = $opts['docs']['exclude_dirs']['new_stack'];
                if ( $sourcedir == '' )
                {
                    $sourcedir = $opts['build']['dir'] . '/release/' . self::getProjName();
                }
                if ( $docsdir == '' )
                {
                    $docsdir = $opts['build']['dir'] . '/apidocs/' . self::getProjName() . '/ezpublish';
                }
                $files = pakeFinder::type( 'file' )->name( 'autoload.php' )->maxdepth( 0 )->in( $sourcedir . '/ezpublish' );
                // allow building from sources, not only from release
                if ( !count( $files ) )
                {
                    $files = pakeFinder::type( 'directory' )->name( 'eZ' )->maxdepth( 0 )->in( $sourcedir );
                }
                $namesuffix = $opts['docs']['name_suffix']['new_stack'];
        }

        if ( $opts['create']['doxygen_doc'] || $opts['create']['docblox_doc'] || $opts['create']['phpdoc_doc']  || $opts['create']['sami_doc'] )
        {
            if ( !count( $files ) )
            {
                throw new pakeException( "Can not generate documentation: no sources found in $sourcedir" );
            }
        }

        $excludedirs = explode( ' ', $excludedirs );

        if ( $namesuffix != '' )
        {
            $namesuffix = ' ' . $namesuffix;
        }

        if ( $opts['create']['doxygen_doc'] )
        {
            pake_echo( "Generating docs using Doxygen" );

            $doxygen = self::getTool( 'doxygen', $opts );

            if ( $opts['docs']['doxygen']['dir'] != '' )
            {
                $outdir = $opts['docs']['doxygen']['dir'];
            }
            else
            {
                $outdir = $docsdir . '/doxygen';
            }

            if ( $opts['docs']['doxygen']['zipdir'] != '' )
            {
                $zipdir = $opts['docs']['doxygen']['zipdir'];
            }
            else
            {
                $zipdir = $opts['dist']['dir'];
            }

            $doxyfile = $opts['build']['dir'] . '/doxyfile';
            $excludes = '';
            foreach( $excludedirs as $excluded )
            {
                $excludes .= "$sourcedir/$excluded ";
            }
            pake_copy( $opts['docs']['doxyfile_master'], $doxyfile, array( 'override' => true ) );
            file_put_contents( $doxyfile,
                "\nPROJECT_NAME = " . self::getLongProjName( true, $namesuffix ) .
                "\nPROJECT_NUMBER = " . $opts['version']['alias'] .
                "\nOUTPUT_DIRECTORY = " . $outdir .
                "\nINPUT = " . $sourcedir .
                "\nEXCLUDE = " . $excludes .
                "\nSTRIP_FROM_PATH = " . $sourcedir .
                "\nSOURCE_BROWSER = " . ( $opts['docs']['include_sources'] ? 'yes' : 'no') , FILE_APPEND );

            pake_mkdirs( $outdir );
            pake_remove_dir( $outdir . '/html' );
            $out = pake_sh( "$doxygen " . escapeshellarg( $doxyfile ) .
                ' > ' . escapeshellarg( $outdir . '/generate.log' ) );

            // test that there are any doc files created
            $files = pakeFinder::type( 'file' )->name( 'index.html' )->maxdepth( 0 )->in( $outdir . '/html' );
            if ( !count( $files ) )
            {
                throw new pakeException( "Doxygen did not generate index.html file in $outdir/html" );
            }

            // zip the docs
            pake_echo( "Creating tarballs of docs" );
            $filename = self::getProjFileName() . '-apidocs-doxygen-' . $stack;
            // get absolute path to dist dir
            $target = realpath( $zipdir ) . '/' . $filename;
            if ( $opts['docs']['create']['zip'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.zip', true );
            }
            if ( $opts['docs']['create']['tgz'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.tar.gz', true );
            }
            if ( $opts['docs']['create']['bz2'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.tar.bz2', true );
            }
        }

        if ( $opts['create']['sami_doc'] )
        {
            pake_echo( "Generating docs using Sami" );

            $sami = self::getTool( 'sami', $opts, true );

            if ( $opts['docs']['sami']['dir'] != '' )
            {
                $outdir = $opts['docs']['sami']['dir'];
            }
            else
            {
                $outdir = $docsdir . '/sami';
            }

            if ( $opts['docs']['sami']['zipdir'] != '' )
            {
                $zipdir = $opts['docs']['sami']['zipdir'];
            }
            else
            {
                $zipdir = $opts['dist']['dir'];
            }

            $samifile = $opts['build']['dir'] . '/samicfg.php';
            $excludes = array();
            foreach( $excludedirs as $excluded )
            {
                $excludes[] = str_replace( "'", "\'", $excluded );
            }
            $excludes = implode( "' )\n    ->exclude( '", $excludes );
            pake_copy( self::getResourceDir() . '/samicfg_master.php', $samifile, array( 'override' => true ) );
            pake_replace_tokens( 'samicfg.php', $opts['build']['dir'], '//', '//', array(
                'SOURCE' => str_replace( "'", "\'", $sourcedir ),
                'TITLE' => self::getLongProjName( true, $namesuffix ) . ' ' . $opts['version']['alias'],
                'EXCLUDE' => $excludes,
                'OUTPUT' => $outdir . '/html',
                'CACHEDIR' => $opts['build']['dir'] . '/sami_cache',
                'BASEDIR' => dirname( __DIR__ )
            ) );

            //clear sami cache, as sometimes it prevents docs from generating correctly
            pake_remove_dir( $opts['build']['dir'] . '/sami_cache' );

            pake_mkdirs( $outdir );
            pake_remove_dir( $outdir . '/html' );
            $php = self::getTool( 'php', $opts );
            $out = pake_sh( "$php $sami parse --force " . escapeshellarg( $samifile ) .
                ' > ' . escapeshellarg( $outdir . '/generate.log' ) );
            $out = pake_sh( "$php $sami render " . escapeshellarg( $samifile ) .
                ' >> ' . escapeshellarg( $outdir . '/generate.log' ) );

            // test that there are any doc files created
            $files = pakeFinder::type( 'file' )->name( 'index.html' )->maxdepth( 0 )->in( $outdir . '/html' );
            if ( !count( $files ) )
            {
                throw new pakeException( "Sami did not generate index.html file in $outdir/html" );
            }

            // zip the docs
            pake_echo( "Creating tarballs of docs" );
            $filename = self::getProjFileName() . '-apidocs-sami-' . $stack;
            // get absolute path to dist dir
            $target = realpath( $zipdir ) . '/' . $filename;
            if ( $opts['docs']['create']['zip'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.zip', true );
            }
            if ( $opts['docs']['create']['tgz'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.tar.gz', true );
            }
            if ( $opts['docs']['create']['bz2'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.tar.bz2', true );
            }
        }

        if ( $opts['create']['docblox_doc'] )
        {
            pake_echo( "Generating docs using Docblox" );

            $docblox = self::getTool( 'docblox', $opts );

            if ( $opts['docs']['docblox']['dir'] != '' )
            {
                $outdir = $opts['docs']['docblox']['dir'];
            }
            else
            {
                $outdir = $docsdir . '/docblox';
            }

            if ( $opts['docs']['docblox']['zipdir'] != '' )
            {
                $zipdir = $opts['docs']['docblox']['zipdir'];
            }
            else
            {
                $zipdir = $opts['dist']['dir'];
            }

            pake_mkdirs( $outdir );
            pake_remove_dir( $outdir . '/html' );
            $php = self::getTool( 'php', $opts );
            $out = pake_sh( "$php -d memory_limit=3000M $docblox" .
                ' -d ' . escapeshellarg( $sourcedir ) . ' -t ' . escapeshellarg( $outdir . '/html' ) .
                ' --title ' . escapeshellarg( self::getLongProjName( true, $namesuffix ) . ' ' . $opts['version']['alias'] ) .
                ' --ignore ' . escapeshellarg( implode( ',', $excludedirs ) ) .
                ( $opts['docs']['include_sources'] ? ' --sourcecode' : '' ) .
                ' > ' . escapeshellarg( $outdir . '/docblox/generate.log' ) );
            /// @todo sed -e "s,${checkoutpath},,g" ${doxydir}/generate.log > ${doxydir}/generate2.log

            // test that there are any doc files created
            $files = pakeFinder::type( 'file' )->name( 'index.html' )->maxdepth( 0 )->in( $outdir . '/html' );
            if ( !count( $files ) )
            {
                throw new pakeException( "Docblox did not generate index.html file in $outdir/html" );
            }
            // zip the docs
            pake_echo( "Creating tarballs of docs" );
            $filename = self::getProjFileName() . '-apidocs-docblox-' . $stack;
            $target = realpath( $zipdir ) . '/' . $filename;
            if ( $opts['docs']['create']['zip'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.zip', true );
            }
            if ( $opts['docs']['create']['tgz'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.tar.gz', true );
            }
            if ( $opts['docs']['create']['bz2'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.tar.bz2', true );
            }
        }

        if ( $opts['create']['phpdoc_doc'] )
        {
            pake_echo( "Generating docs using PhpDoc" );

            $phpdoc = self::getTool( 'phpdoc', $opts, true );

            if ( $opts['docs']['phpdoc']['dir'] != '' )
            {
                $outdir = $opts['docs']['phpdoc']['dir'];
            }
            else
            {
                $outdir = $docsdir . '/phpdoc';
            }

            if ( $opts['docs']['phpdoc']['zipdir'] != '' )
            {
                $zipdir = $opts['docs']['phpdoc']['zipdir'];
            }
            else
            {
                $zipdir = $opts['dist']['dir'];
            }

            pake_mkdirs( $outdir . '/html' );
            // we try to avoid deprecation errors from phpdoc
            $errcode =  30719; // php 5.3, 5.4
            if ( version_compare( PHP_VERSION, '5.3.0' ) < 0 )
            {
                $errcode =  6143;
            }
            // phpdoc uses A LOT of memory as well
            $php = self::getTool( 'php', $opts );
            $out = pake_sh( "$php -d error_reporting=$errcode -d memory_limit=3000M $phpdoc" .
                ' -t ' . escapeshellarg( $outdir . '/html' ) .
                ' -d ' . escapeshellarg( $sourcedir ) . ' --parseprivate' .
                ' --title ' . escapeshellarg( self::getLongProjName( true, $namesuffix ) . ' ' . $opts['version']['alias'] ).
                ' -i ' . escapeshellarg( implode( '/*,', $excludedirs ) . '/*' ) .
                ( $opts['docs']['include_sources'] ? ' --sourcecode' : '' ) .
                ' > ' . escapeshellarg( $outdir . '/generate.log' ) );
            /// @todo sed -e "s,${phpdocdir},,g" ${phpdocdir}/generate.log > ${phpdocdir}/generate2.log
            ///       sed -e "s,${checkoutpath},,g" ${phpdocdir}/generate2.log > ${phpdocdir}/generate3.log
            ///       sed -e "s,${phpdocinstall},,g" ${phpdocdir}/generate3.log > ${phpdocdir}/generate4.log
            // test that there are any doc files created
            $files = pakeFinder::type( 'file' )->name( 'index.html' )->maxdepth( 0 )->in( $outdir . '/html' );
            if ( !count( $files ) )
            {
                throw new pakeException( "Phpdoc did not generate index.html file in $outdir/phpdoc/html" );
            }

            // clear phpdoc cache, as it is generated in same folder as doc artifacts and there is apparently no otpion
            // to have it somewhere else
            foreach( PakeFinder::type( 'dir' )->maxdepth( 1 )->name( 'phpdoc-cache-*' )->in( $outdir . '/html' ) as $dir )
            {
                pake_remove_dir( $dir );
            }


            // zip the docs
            pake_echo( "Creating tarballs of docs" );
            $filename = self::getProjFileName() . '-apidocs-phpdoc-' . $stack;
            $target = realpath( $zipdir ) . '/' . $filename;
            if ( $opts['docs']['create']['zip'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.zip', true );
            }
            if ( $opts['docs']['create']['tgz'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.tar.gz', true );
            }
            if ( $opts['docs']['create']['bz2'] )
            {
                self::archiveDir( $outdir . '/html', $target . '.tar.bz2', true );
            }
        }
    }

    /**
     * Creates different versions of the build tarballs (the main tarballs are created
     * on Jenkins).
     *
     * We rely on the pake dependency system to do the real stuff
     * (run pake -P to see tasks included in this one)
     */
    public static function run_dist( $task=null, $args=array(), $cliopts=array() )
    {
    }

    /**
     * Downloads the build tarballs from Jenkins for further repackaging; options: --build=<buildnr>
     */
    public static function run_dist_init( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        $buildnr = @$cliopts['build'];
        if ( $buildnr == '' )
        {
            pake_echo( 'Fetching latest available build' );
            $buildnr = 'lastBuild';
        }

        // get list of files from the build
        $out = self::jenkinsCall( 'job/' . $opts['jenkins']['jobs']['community'] . '/' . $buildnr . '/api/json', $opts );
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
        //$buildurl = self::jenkinsUrl( 'job/' . $opts['jenkins']['jobs']['community'] . '/' . $buildnr, $opts );
        $fileurl = '';
        foreach( $out['artifacts'] as $artifact )
        {
            if ( substr( $artifact['fileName'], -4 ) == '.bz2' /*&& strpos( $artifact['fileName'], 'with_ezc' ) !== false*/ )
            {
                $fileurl = 'job/' . $opts['jenkins']['jobs']['community'] . '/' . $buildnr . '/artifact/' . $artifact['relativePath'];
                break;
            }
        }
        if ( $fileurl == '' )
        {
            pake_echo( "No artifacts available for build $buildnr" );
            return;
        }

        // clean up the 'release' dir
        $rootpath = $opts['build']['dir'] . '/release';
        /// @todo this method is a bit slow, should find a faster one
        pake_remove_dir( $rootpath );

        // download and unzip the file
        pake_mkdirs( $rootpath );
        $filename = $rootpath . '/' . $artifact['fileName'];
        pake_write_file( $filename, self::jenkinsCall( $fileurl, $opts, 'GET', null, false ), 'cpb' );

        // and unzip eZ into it - in a folder with a specific name
        $tar = self::getTool( 'tar', $opts );
        pake_sh( self::getCdCmd( $rootpath ) ." && $tar -xjf " . escapeshellarg( $artifact['fileName'] ) );

        $currdir = pakeFinder::type( 'directory' )->in( $rootpath );
        $currdir = $currdir[0];
        $finaldir = $rootpath . '/' . self::getProjName();
        pake_rename( $currdir, $finaldir );
        pake_echo( "dir+         " . $finaldir );
    }

    /**
     * Uploads dist artifacts to share.ez.no, pubsvn.rz.no; INCOMPLETE!
     *
     * We rely on the pake dependency system to do the real stuff
     * (run pake -P to see tasks included in this one)
     */
    public static function run_release( $task=null, $args=array(), $cliopts=array() )
    {

    }

    /**
     * Uploads dist material to share.ez.no and creates all pages: changelogs/releasenotes/credits/.... TO BE DONE
     */
    public static function run_update_share( $task=null, $args=array(), $cliopts=array() )
    {
        throw new pakeException( "Task to be implemented" );
    }

    /**
     * Updates the "eZ CP version history" document, currently hosted on pubsvn.ez.no.
     *
     * Options: --public-keyfile=<...> --private-keyfile=<...> --user=<...> --private-keypasswd=<...>
     *
     * @todo add support for getting ssl certs options in config settings
     */
    public static function run_update_version_history( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );

        $public_keyfile = @$cliopts['public-keyfile'];
        $private_keyfile = @$cliopts['private-keyfile'];
        $private_keypasswd = @$cliopts['private-keypasswd'];
        $user = @$cliopts['user'];

        // get file
        $srv = "http://pubsvn.ez.no";
        $filename = "/ezpublish_version_history/ez_history.csv";
        $fullfilename = $srv . $filename;
        $remotefilename = '/mnt/pubsvn.ez.no/www/' . $filename;
        $file = pake_read_file( $fullfilename );
        if ( "" == $file )
        {
            throw new pakeException( "Couldn't download {$fullfilename} file" );
        }

        // patch it
        /// @todo test that what we got looks at least a bit like what we expect
        $lines = preg_split( "/\r?\n/", $file );
        $lines[0] .= $opts['version']['alias']  . ',';
        $lines[1] .= strftime( '%Y/%m/%d' )  . ',' ;
        $file = implode( "\n", $lines );

        /// @todo: back up the original as well (upload 1st to srv the unpatched version with a different name, then the new version)

        // upload it: we use curl for sftp
        $randfile = tempnam( sys_get_temp_dir(), 'EZP' );
        pake_write_file( $randfile, $file, true );
        $fp = fopen( $randfile, 'rb' );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, str_replace( 'http://', 'sftp://@', $srv ) . $remotefilename );
        if ( $user != "" ) curl_setopt( $ch, CURLOPT_USERPWD, $user );
        if ( $public_keyfile != "" ) curl_setopt( $ch, CURLOPT_SSH_PUBLIC_KEYFILE, $public_keyfile );
        if ( $private_keyfile != "" ) curl_setopt( $ch, CURLOPT_SSH_PRIVATE_KEYFILE, $private_keyfile );
        if ( $private_keypasswd != "" ) curl_setopt( $ch, CURLOPT_KEYPASSWD, $private_keypasswd );
        curl_setopt( $ch, CURLOPT_UPLOAD, 1 );
        curl_setopt( $ch, CURLOPT_INFILE, $fp );
        // set size of the file, which isn't _mandatory_ but helps libcurl to do
        // extra error checking on the upload.
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize( $randfile ) );
        $ok = curl_exec( $ch );
        $errinfo = curl_error( $ch );
        curl_close( $ch );

        fclose( $fp );
        pake_unlink( $randfile );

        if ( !$ok )
        {
            throw new pakeException( "Couldn't write {$fullfilename} file: " . $errinfo );
        }

        pake_echo_action( "file+", $filename );
    }

    /**
     * Uploads php-api docs of the build to pubsvn.ez.no. TO BE CODED
     */
    public static function run_upload_apidocs( $task=null, $args=array(), $cliopts=array() )
    {
        throw new pakeException( "Task to be implemented" );
    }

    /**
     * Builds the cms and generates the tarballs.
     *
     * We rely on the pake dependency system to do the real stuff
     * (run pake -P to see tasks included in this one)
     */
    public static function run_all( $task=null, $args=array(), $cliopts=array() )
    {
    }

    /**
     * Removes the build/ directory
     */
    public static function run_clean( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );
        pake_remove_dir( $opts['build']['dir'] );
    }

    /**
     * Removes the dist/ directory (usually includes the apidocs directory)
     */
    public static function run_dist_clean( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );
        pake_remove_dir( $opts['dist']['dir'] );
    }

    /**
     * Removes the directory where the local copy of the CI repo is kept
     */
    public static function run_clean_ci_repo( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );
        if ( @$opts['ci-repo']['local-path'] == '' )
        {
            throw new pakeException( "Missing option ci-repo:local-path in config file: can not remove CI repo" );
        }
        pake_remove_dir( $opts['ci-repo']['local-path'] );
    }

    /**
     * Removes the build/ and dist/ directories (not the one for the CI repo source files).
     *
     * We rely on the pake dependency system to do the real stuff
     * (run pake -P to see tasks included in this one)
     */
    public static function run_clean_all( $task=null, $args=array(), $cliopts=array() )
    {
    }


    protected static function jenkinsBuild( $jobname, $opts, $params=array() )
    {

        pake_echo( "Triggering build of jenkins job '{$jobname}'" );
        /// @todo Improve this: jenkins gives us an http 302 => html page,
        ///       so far I have found no way to get back a json-encoded result
        if ( count( $params ) )
        {
            foreach( $params as $name => $value )
            {
                $urlParams[] = $name . '=' . urlencode( $value );
            }
            $urlParams = implode( '&', $urlParams );
            $out = self::jenkinsCall( 'job/' . $jobname . '/buildWithParameters?delay=0sec&' . $urlParams, $opts );
        }
        else
        {
            $out = self::jenkinsCall( 'job/' . $jobname . '/build?delay=0sec', $opts );
        }

        // "scrape" the number of the currently executing build, and hope it is the one we triggered.
        // example: <a href="lastBuild/">Last build (#506), 0.32 sec ago</a></li>
        $ok = preg_match( '/<a [^>].*href="lastBuild\/">Last build \(#(\d+)\)/', $out, $matches );
        if ( !$ok )
        {
            // example 2: <a href="/job/ezpublish-full-community/671/console"><img height="16" alt="In progress &gt; Console Output" width="16" src="/static/b50e0545/images/16x16/red_anime.gif" tooltip="In progress &gt; Console Output" /></a>
            $ok = preg_match( '/<a href="\/job\/' . $jobname . '\/(\d+)\/console"><img height="16" alt="In progress &gt; Console Output"/', $out, $matches );
            if ( !$ok )
            {
                throw new pakeException( "Build not started or unknown error. Jenkins page output:\n" . $out );
            }
        }
        $buildnr = $matches[1];

        /*
           $joburl = $opts['jenkins']['url'] . '/job/' . $opts['jenkins']['jobs']['legacy'] . '/api/json';
           $out = json_decode( pake_read_file( $buildurl ), true );
           // $buildnr = ...
        */

        pake_echo( "Build $buildnr triggered. Starting polling..." );
        $buildurl = 'job/' . $jobname . '/' . $buildnr . '/api/json';
        while ( true )
        {
            sleep( 5 );
            $out = self::jenkinsCall( $buildurl, $opts ); // true
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
            pake_echo( "Build $buildnr succesful" );
        }
        else
        {
            throw new pakeException( "Build failed or unknown status" );
        }
    }

    protected static function getPreviousRevisions( $repos, $opts )
    {
        $revisions = array();
        foreach( $repos as $repo )
        {
            $rootpath = self::getSourceDir( $opts, $repo );

            if ( isset( $opts['version']['previous'][$repo]['git-revision'] ) )
            {
                $previousrev = $opts['version']['previous'][$repo]['git-revision'];

                pake_echo ( "\nGit revision of previous release for repo $repo taken from config file: $previousrev" );
            }
            else
            {
                $prevname = self::previousVersionName( $opts );

                pake_echo ( "\nGetting git revision of previous release from GIT or Jenkins for repo $repo" );

                $previousrev = self::getPreviousRevision( $prevname, $repo, $opts );
                if ( $previousrev == "" )
                {
                    throw new pakeException( "Previous revision number of $repo not found in Git or Jenkins. Please set it manually in version:previous:$repo:git-revision" );
                }

                pake_echo ( "Git revision number of previous release for repo $repo is: $previousrev" );
            }

            $revisions[$repo] = $previousrev;
        }

        return $revisions;
    }
}
