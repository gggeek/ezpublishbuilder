<?php
/**
 * @author    G. Giunta
 * @author    N. Pastorino
 * @copyright (C) eZ Systems AS 2011-2013
 * @license   code licensed under the GNU GPL 2.0: see README file
 */

namespace eZPCPBuilder;

use pakeException;
use pakeYaml;

/**
 * Class implementing the core logic for our pake tasks
 */
class Builder
{
    static $options = null;
    protected static $options_dir = 'pake';
    const VERSION = '0.5.0-dev';
    const MIN_PAKE_VERSION = '1.7.4';
    static $projname = 'ezpublish';

    static function getOptionsDir()
    {
        return self::$options_dir;
    }

    static function setConfigDir( $cliopts = array() )
    {
        if ( isset( $cliopts['config-dir'] ) )
        {
            if( !is_dir( $cliopts['config-dir'] ) )
            {
                throw new PakeOption( "Could not find configuration-file directory {$cliopts['config-dir']}" );
            }
            self::$options_dir = $cliopts['config-dir'];
        }
    }

    static function getResourceDir()
    {
        return __DIR__ . '/../resources';
    }

    // leftover from ezextensionbuilder - currently hardcoded in the class
    static function getProjName()
    {
        return self::$projname;
    }

    /**
     * Long name starts with an optional "eZ Publish", then name from options, then suffix
     */
    static function getLongProjName( $withPrefix = false, $suffix='' )
    {
        return ( $withPrefix ? 'eZ Publish ': '' ) . ucfirst( str_replace( '_', ' ',  self::$options[self::$projname][self::$projname]['name'] . $suffix ) );
    }

    /**
     * File name starts with "ezpublish", then name from options, then version nr
     */
    static function getProjFileName()
    {
        $out = self::$projname;
        if ( self::$options[self::$projname][self::$projname]['name'] != '' )
        {
            $out .= '-' . str_replace( ' ', '_', self::$options[self::$projname][self::$projname]['name'] );
        }
        $out .= '-' . self::$options[self::$projname]['version']['alias'];
        return strtolower( $out );
    }

    /**
     * Loads build options from config file(s) and ommand line
     * @param array $opts the 1st option is the version to be built. If given, it overrides the one in the config file
     * @param array $cliopts optional parameters.
     *              If "config-file" is set, that will be used instead of pake/options-ezpublish.yaml.
     *              If "user-config-file" is set, that will be used instead of pake/options-ezpublish-user.yaml
     *              Also all cli options starting with "option." will be used to override config-file values
     * @return array all the options
     *
     * @todo remove support for a separate project name, as it is leftover from ezextensionbuilder
     */
    static function getOpts( $opts=array(), $cliopts=array() )
    {
        self::setConfigDir( $cliopts );

        $projname = self::getProjName();
        $projversion = @$opts[0];
        if ( isset( $cliopts['config-file'] ) )
        {
            $cfgfile = $cliopts['config-file'];
        }
        else
        {
            $cfgfile = self::getOptionsDir() . "/options-$projname.yaml";
        }

        if ( isset( $cliopts['user-config-file'] ) )
        {
            $usercfgfile = $cliopts['user-config-file'];
        }
        else
        {
            $usercfgfile = self::getOptionsDir() . "/options-$projname-user.yaml";
        }

        // command-line config options
        foreach( $cliopts as $opt => $val )
        {
            if ( substr( $opt, 0, 7 ) == 'option.')
            {
                unset( $cliopts[$opt] );

                // transform dotted notation in array structure
                $work = array_reverse( explode( '.', substr( $opt, 7 ) ) );
                $built = array( array_shift( $work ) => $val );
                foreach( $work as $key )
                {
                    $built = array( $key=> $built );
                }
                self::recursivemerge( $cliopts, $built );
            }
        }
        if ( !isset( self::$options[$projname] ) || !is_array( self::$options[$projname] ) )
        {
            self::loadConfiguration( $cfgfile, $usercfgfile, $projname, $projversion, $cliopts );
        }
        return self::$options[$projname];
    }

    /// @bug this only works as long as all defaults are 2 levels deep
    static protected function loadConfiguration ( $infile='', $useroptsfile='', $projname='', $projversion='', $overrideoptions=array() )
    {
        if ( $infile == '' )
        {
            $infile = self::getOptionsDir() . '/options' . ( $projname != '' ? "-$projname" : '' ) . '.yaml';
        }
        /// @todo review the list of mandatory options
        $mandatory_opts = array( /*'ezpublish' => array( 'name' ),*/ 'version' => array( 'major', 'minor', 'release' ) );
        $default_opts = array(
            'build' => array( 'dir' => 'build' ),
            'dist' => array( 'dir' => 'dist' ),
            'docs' => array( 'dir' => 'dist/docs' ),
            'create' => array( 'mswpipackage' => false, /*'tarball' => false, 'zip' => false, 'filelist_md5' => true,*/ 'doxygen_doc' => false, 'docblox_doc' => false, 'phpdoc_doc' => false, 'sami_doc' => false /*'ezpackage' => false, 'pearpackage' => false*/ ),
            //'version' => array( 'license' => 'GNU General Public License v2.0' ),
            //'releasenr' => array( 'separator' => '.' ),
            //'files' => array( 'to_parse' => array(), 'to_exclude' => array(), 'gnu_dir' => '', 'sql_files' => array( 'db_schema' => 'schema.sql', 'db_data' => 'cleandata.sql' ) ),
            /*'dependencies' => array( 'extensions' => array() )*/ );

        // load main config file
        /// @todo !important: test if !file_exists give a nicer warning than what we get from loadFile()
        $options = pakeYaml::loadFile( $infile );

        // merge data from local config file
        if ( $useroptsfile != '' && file_exists( $useroptsfile ) )
        {
            $useroptions = pakeYaml::loadFile( $useroptsfile );
            //var_dump( $useroptions );
            self::recursivemerge( $options, $useroptions );
        }

        // merge options from cli
        if ( count( $overrideoptions ) )
        {
            //var_dump( $overrideoptions );
            self::recursivemerge( $options, $overrideoptions );
        }

        // check if anything mandatory is missing
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

        // hardcoded overrides
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

        // merge default values
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
     * Creates an archive out of a directory.
     *
     * Uses command-lne tar as Zeta Cmponents do no compress well, and pake
     * relies on phar which is buggy/unstable on old php versions
     *
     * @param boolean $no_top_dir when set, $sourcedir directory is not packaged as top-level dir in archive
     * @todo for tar formats, fix the extra "." dir packaged
     */
    static function archiveDir( $sourcedir, $archivefile, $no_top_dir=false )
    {
        // please tar cmd on win - OH MY!

        $archivefile = str_replace( '\\', '/', $archivefile );
        $sourcedir = str_replace( '\\', '/', realpath( $sourcedir ) );

        if( $no_top_dir )
        {
            $srcdir = '.';
            $workdir = $sourcedir;
        }
        else
        {
            $srcdir = basename( $sourcedir );
            $workdir = dirname( $sourcedir );
        }
        $archivedir = dirname( $archivefile );
        $extra = '';

        $tar = self::getTool( 'tar' );

        if ( substr( $archivefile, -7 ) == '.tar.gz' || substr( $archivefile, -4 ) == '.tgz' )
        {
            $cmd = "$tar -z -cvf";
            $extra = "-C " . escapeshellarg( $workdir );
            $workdir = $archivedir;
            $archivefile = basename( $archivefile );
        }
        else if ( substr( $archivefile, -8 ) == '.tar.bz2' )
        {
            $cmd = "$tar -j -cvf";
            $extra = "-C " . escapeshellarg( $workdir );
            $workdir = $archivedir;
            $archivefile = basename( $archivefile );
        }
        else if ( substr( $archivefile, -4 ) == '.tar' )
        {
            $cmd = "$tar -cvf";
            $extra = "-C " . escapeshellarg( $workdir );
            $workdir = $archivedir;
            $archivefile = basename( $archivefile );
        }
        else if ( substr( $archivefile, -4 ) == '.zip' )
        {
            $zip = self::getTool( 'zip' );
            $cmd = "$zip -9 -r";
        }
        else
        {
            throw new pakeException( "Can not determine archive type from filename: $archivefile" );
        }

        pake_sh( self::getCdCmd( $workdir ) . " && $cmd $archivefile $extra $srcdir" );

        pake_echo_action( 'file+', $archivefile );
    }

    /// @todo move values to options file?
    public static function getEzPublishHeader( $repo )
    {
        $names = array(
            'legacy' => 'eZ Publish Legacy Stack (LS)',
            'kernel' => 'eZ Publish 5',
            'community' => 'eZ Publish Kernel & APIs'
        );
        return $names[$repo];
    }

    public static function getSourceDir( $opts, $repo = '' )
    {
        if ( isset( $opts['git'][$repo]['local-path'] ) )
        {
            return $opts['git'][$repo]['local-path'];
        }
        $dir = $opts['build']['dir'] . '/source/' . self::getProjName();
        if ( $repo != '' )
        {
            $dir .= "/$repo";
        }
        return $dir;
    }

    /**
     * Tries to find out the vendor dir of composer - should work both when ezextbuilder is main project and when it is
     * a dependency. Returns FALSE if not found
     *
     * @param string $vendorPrefix
     * @return string
     */
    static function getVendorDir( $vendorPrefix = 'vendor' )
    {
        if( is_dir( __DIR__ . "/../$vendorPrefix/composer" ) && is_file( __DIR__ . "/../$vendorPrefix/autoload.php" ) )
        {
            return realpath( __DIR__ . "/../$vendorPrefix" );
        }
        return false;
    }

    public static function getTool( $tool, $opts=false, $composerBinary=false )
    {
        // dirty workaround
        if ( $opts == false )
        {
            $opts = self::$options[self::$projname];
        }
        if ( isset( $opts['tools'][$tool] ) )
        {
            return escapeshellarg( $opts['tools'][$tool] );
        }
        else
        {
            if ( $composerBinary )
            {
                $vendorDir = self::getVendorDir();
                if ( file_exists( $vendorDir . "/bin/$tool" ) )
                {
                    $file = realpath( $vendorDir . "/bin/$tool" );
                    if ( strtoupper( substr( PHP_OS, 0, 3) ) === 'WIN' )
                    {
                        $file .= '.bat';
                    }
                    return escapeshellarg( $file );
                }
            }
            return escapeshellarg( pake_which( $tool ) );
        }
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

        if ( isset( $matches[1] ) and !is_array( $matches[1] ) )
        {
            // Handling the "Unmatched Entries"
            $entries = array_unique( $matches );
        }
        else
        {
            // Handling bug-fixes and improvements
            // move to an array( bugid ) => text for sorting
            for( $i = 0, $c = count( $matches[0] ); $i < $c; $i++ )
            {
                $indexedItems[$matches[1][$i]] = $matches[2][$i];
            }
            ksort( $indexedItems );

            // format
            foreach ( $indexedItems as $id => $text )
            {
                if ( substr( $text, 0, 2 ) == '- ' )
                {
                    $text = substr( $text, 2 );
                }
                $entries[] = "- $id: " . ltrim( $text );
            }
        }

        return $entries;
    }

    /// Path to dir where changelog file should reside
    static function changelogDir( $opts )
    {
        return self::getSourceDir( $opts, 'legacy' ) . '/doc/changelogs/Community_Project-' . $opts['version']['major'];
    }

    /// Generate name for changelog file
    static function changelogFilename( $opts )
    {
        return 'CHANGELOG-' . self::previousVersionName( $opts ) . '-to-' . $opts['version']['alias'] . '.txt';
    }

    /**
     * Returns the name of the previous version than the current one.
     * Assumes 2011.01 .. 2011.12 naming schema.
     * Partial support for 2012.01.2 schema (eg 2011.01.2 -> 2011.01.1 -> 2011.01 -> 20112.12)
     * User can define an alternative previous version in config file.
     * @bug what if previous of 2012.4 is 2012.3.9?
     * @return string
     */
    static function previousVersionName( $opts )
    {
        if ( isset( $opts['version']['previous']['name'] ) )
        {
            return  (string)$opts['version']['previous']['name'];
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
                return $opts['version']['major'] . '.' . ( $opts['version']['minor'] <= 10 ? '0' : '' ) . ( $opts['version']['minor'] - 1 );
            }
            else
            {
                return ( $opts['version']['major'] - 1 ) . '.12';
            }
        }
    }

    /**
     * Tries to infer name of git revision used for last build, by looking
     *   1st in git tags
     *   2nd in jenkins builds info
     * Note: assumes source dir is tied to correct git repo
     *
     * @param $prevName Previous build name. Ex: "2012.12"
     * @param $repo repo name
     * @param $opts The Pake options
     * @return string The previous revision (git SHA1) or an empty string on failure.
     */
    static function getPreviousRevision( $prevName, $repo, $opts )
    {
        $previousrev = '';
        pake_echo( "Previous release assumed to be $prevName" );

        pake_echo( "Looking up corresponding rev. number in git tags" );

        $rootpath = self::getSourceDir( $opts, $repo );
        $git = escapeshellarg( pake_which( 'git' ) );

        $tagArray = preg_split( '/(\r\n|\n\r|\r|\n)/', pake_sh( self::getCdCmd( $rootpath ) . " && $git show-ref --tags" ) );
        $previousbuild = false;
        foreach( $tagArray as $tagLine )
        {
            if ( strpos( $tagLine, $prevName ) !== false )
            {
                $previousbuild = explode( ' ', $tagLine );

                pake_echo( "Release $prevName found in GIT tag $previousbuild[1], corresponding to git rev. $previousbuild[0]" );

                return $previousbuild[0];
            }
        }

        if ( !isset ( $opts['jenkins']['jobs'][$repo] ) )
            return "";

        pake_echo( "Looking up corresponding build number in Jenkins" );
        // find git rev of the build of the previous release on jenkins

        $jenkinsJobsName = $opts['jenkins']['jobs'][$repo];

        $out = self::jenkinsCall( 'job/' . $jenkinsJobsName . '/api/json?tree=builds[description,number,result,binding]', $opts );
        if ( is_array( $out ) && isset( $out['builds'] ) )
        {
            $previousbuild = '';

            foreach( $out['builds'] as $build )
            {
                if ( strpos( $build['description'], $prevName ) !== false )
                {
                    $previousbuild = $build['number'];
                    break;
                }
            }

            if ( $previousbuild )
            {
                $out = self::jenkinsCall( 'job/' . $jenkinsJobsName . '/' . $previousbuild . '/api/json', $opts );
                if ( is_array( @$out['actions'] ) )
                {
                    foreach( $out['actions'] as $action )
                    {
                        if ( isset( $action['lastBuiltRevision'] ) )
                        {
                            $previousrev = $action['lastBuiltRevision']['SHA1'];
                            pake_echo( "Release $prevName found in Jenkins build $previousbuild, corresponding to git rev. $previousrev" );
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

        return $previousrev;
    }

    public static function tagNameFromVersionName( $versionName, $opts )
    {
        return $opts['git']['tag_prefix'] . $versionName . $opts['git']['tag_postfix'];
    }

    /**
     * Classifies all entries in git changelog as 4 types.
     * Each entry is returned starting with "- "
     * @return array the 1st-level elements are themselves matrixes, except for 'unmatchedEntries' which is a plain array
     */
    public static function extractChangelogEntriesFromRepo( $rootpath, $previousrev )
    {
        if ( $previousrev != '' )
        {
            /// @todo check if given revision exists in git repo? We'll get an empty changelog if it does not...

            /// @todo replace with pakegit::log
            $git = escapeshellarg( pake_which( 'git' ) );
            $changelogArray = preg_split( '/(\r\n|\n\r|\r|\n)/', pake_sh( self::getCdCmd( $rootpath ) . " && $git log --pretty=%s " . escapeshellarg( $previousrev ) . "..HEAD" ) );

            $changelogArray = array_map( 'trim', $changelogArray );
            foreach( $changelogArray as $i => $line )
            {
                if ( $line == '' )
                {
                    unset( $changelogArray[$i] );
                }
            }

            $changelogText = implode( "\n", $changelogArray );
            if ( $changelogText == '' )
            {
                pake_echo( "Git log returns an empty string - generating an empty changelog file. Please check if there is any problem with $rootpath" );
            }


            // Was: "extract and categorize issues using known patterns"
            // This proved not to be reliable!
            // We categorize all issues by looking at their type in the bug tracker instead
            /*preg_match_all( "/^[- ]?Fix(?:ed|ing)?(?: bug|issue|for ticket)? (EZP-[0-9]+):? (.*)$/mi", $changelogText, $bugfixesMatches, PREG_PATTERN_ORDER );
            preg_match_all( "/^[- ]?Implement(?:ed)?(?: enhancement|issue)? (EZP-[0-9]+):? (.*)$/mi", $changelogText, $enhancementsMatches, PREG_PATTERN_ORDER );*/
            preg_match_all( "!^Merge pull request #0?([0-9]+):? ([^/]*)(?:/.*)?$!mi", $changelogText, $pullreqsMatches, PREG_PATTERN_ORDER );

            // remove merge commits to get "unmatched" items
            $unmatchedEntries = array_diff(
                    $changelogArray,
                    /*$bugfixesMatches[0],
                    $enhancementsMatches[0],*/
                    $pullreqsMatches[0] );

            /// if we identify an issue number, look up its type in jira to determine its type
            $issueTypes = array();
            foreach( $unmatchedEntries as $i => $entry )
            {
                if ( preg_match( '/(EZP-[0-9]+):? (.*)$/i', $entry, $matches ) )
                {
                    if ( isset( $issueTypes[$matches[1]] ) )
                    {
                        $type = $issueTypes[$matches[1]];
                    }
                    else
                    {
                        $type = self::findIssueType( $matches[1] );
                        $issueTypes[$matches[1]] = $type;
                    }

                    switch ( $type )
                    {
                        case 'enhancement':
                            $enhancementsMatches[0][] = $matches[0];
                            $enhancementsMatches[1][] = $matches[1];
                            $enhancementsMatches[2][] = $matches[2];
                            unset( $unmatchedEntries[$i] );
                            break;
                        case 'bugfix':
                            $bugfixesMatches[0][] = $matches[0];
                            $bugfixesMatches[1][] = $matches[1];
                            $bugfixesMatches[2][] = $matches[2];
                            unset( $unmatchedEntries[$i] );
                            break;
                    }
                }
            }

            $unmatchedEntries = array_values( array_map(
                function( $item )
                {
                    return ( substr( $item, 0, 2 ) != "- " ? "- $item" : $item );
                },
                $unmatchedEntries
            ) );
        }
        else
        {
            pake_echo( 'Can not determine the git tag of last version. Generating an empty changelog file' );

            $bugfixesMatches = array(array());
            $enhancementsMatches = array(array());
            $pullreqsMatches = array(array());
            $unmatchedEntries = array();
        }

        return array(
            'bugfixesMatches'     => $bugfixesMatches,
            'enhancementsMatches' => $enhancementsMatches,
            'pullreqsMatches'     => $pullreqsMatches,
            'unmatchedEntries'    => $unmatchedEntries
        );
    }

    /**
     * Determines an issue type by looking it up in the bug tracker
     * @param $issue
     * @param $opts
     */
    static function findIssueType( $issue, $opts=false )
    {
        // dirty workaround
        if ( $opts == false )
        {
            $opts = self::$options[self::$projname];
        }

        $url = str_replace( '__ISSUE__', $issue, $opts['bugtracker']['url'] );
        $page = file_get_contents( $url );
        switch( $opts['bugtracker']['type'] )
        {
            case 'jira':
                $knowntypes = array(
                    '1' => 'bugfix',
                    '4' => 'enhancement',
                    '5' => 'enhancement', // task
                    '7' => 'enhancement', // story
                );
                $data = json_decode( $page, true );
                if ( isset( $data['fields']['issuetype']['id'] ) && isset( $knowntypes[$data['fields']['issuetype']['id']] ) )
                {
                    return $knowntypes[$data['fields']['issuetype']['id']];
                }
                break;
            default:
                pake_echo_error( "Can not determine issue type on bugtracker '{$opts['bugtracker']['type']}'" );
        }
        return 'unknown';
    }

    /**
     * Creates a full url to connect to Jenkins by adding in hostname and auth tokens
     */
    static function jenkinsUrl( $url, $opts )
    {
        // Token only used in auth headers
        /*if ( $opts['jenkins']['apitoken'] != '' )
        {
            if ( strpos( $url, '?' ) !== false )
            {
                $url .= "&token=" . $opts['jenkins']['apitoken'];
            }
            else
            {
                $url .= "?token=" . $opts['jenkins']['apitoken'];
            }
        }*/
        return $opts['jenkins']['url'] . str_replace( '//', '/', '/' . $url );
    }

    /**
     * Encapsulate calls to Jenkins
     * @see https://wiki.jenkins-ci.org/display/JENKINS/Remote+access+API
     */
    static function jenkinsCall( $url, $opts, $method='GET', $body=null, $decode=true )
    {
        $url = self::jenkinsUrl( $url, $opts );
        $headers = array();
        // basic auth tokens
        /// @see https://wiki.jenkins-ci.org/display/JENKINS/Authenticating+scripted+clients
        if ( $opts['jenkins']['user'] != '' )
        {
            $headers[] =  "Authorization: Basic " . base64_encode( $opts['jenkins']['user'] . ":" . $opts['jenkins']['apitoken'] );
        }
        if ( $method == 'POST' )
        {
            /// @todo only decode json if content-type response header says so
            $out = pakeHttp::post( $url, null, $body, $headers );
        }
        else
        {
            $out = pakeHttp::get( $url, null, $headers );
        }
        // pakeHttp throws exception on http errors, no need to check for it

        // we have no access to reponse headers, dumb way to tell apart plaintext from json
        if ( $decode )
        {
            $ret = json_decode( $out, true );
            return is_array( $ret ) ? $ret : $out;
        }
        return $out;
    }

    static function recursivemerge( &$a, $b )
    {
        //$a will be result. $a will be edited. It's to avoid a lot of copying in recursion
        foreach( $b as $child => $value )
        {
            if( isset( $a[$child] ) )
            {
                if( is_array( $a[$child] ) && is_array( $value ) )
                {
                    //merge if they are both arrays
                    self::recursivemerge( $a[$child], $value );
                }
                else
                {
                    // replace otherwise
                    $a[$child] = $value;
                }
            }
            else
            {
                $a[$child] = $value; //add if not exists
            }
        }
    }

    /**
     * Make "cd" work for all cases, even on win
     */
    static function getCdCmd( $dir )
    {
        if ( $dir[1] == ':' )
        {
            return 'cd /D ' . escapeshellarg( $dir );
        }
        return 'cd ' . escapeshellarg( $dir );
    }

}