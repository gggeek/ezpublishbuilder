<?php
/**
 * @author    G. Giunta
 * @author    N. Pastorino
 * @copyright (C) eZ Systems AS 2011-2013
 * @license   code licensed under the GNU GPL 2.0: see README file
 */

namespace eZPCPBuilder;

use pakeFinder;

class MSWPITasks extends Builder
{
    /**
     * Creates the MS WPI
     */
    public static function run_dist_wpi( $task=null, $args=array(), $cliopts=array() )
    {
        $opts = self::getOpts( $args, $cliopts );
        if ( $opts['create']['mswpipackage'] )
        {
            pake_mkdirs( $opts['dist']['dir'] );
            $toppath = $opts['build']['dir'] . '/release';
            $rootpath = $toppath . '/' . self::getProjName();
            if ( $opts['create']['mswpipackage'] )
            {
                // add extra files to build
                /// @todo move this to another phase/task... ?

                /// @todo shall we check that there's no spurious file in $toppath?

                $resourcesPath = self::getResourceDir();
                pake_copy( $resourcesPath . '/wpifiles/install.sql', $toppath . '/install.sql', array( 'override' => true ) );

                /// @todo: if the $rootpath is different from "ezpublish", the manifest and parameters files need to be altered accordingly
                /// after copying them to their location
                pake_copy( $resourcesPath . '/wpifiles/manifest.xml', $toppath . '/manifest.xml', array( 'override' => true ) );
                pake_copy( $resourcesPath . '/wpifiles/parameters.xml', $toppath . '/parameters.xml', array( 'override' => true ) );

                // this one is overwritten
                pake_copy( $resourcesPath . '/wpifiles/kickstart.ini', $rootpath . '/kickstart.ini', array( 'override' => true ) );

                if ( is_file( $rootpath . '/web.config-RECOMMENDED' ) )
                {
                    pake_copy( $rootpath . '/web.config-RECOMMENDED', $rootpath . '/web.config', array( 'override' => true ) );
                }
                else if ( !is_file( $rootpath . '/web.config' ) )
                {
                    pake_copy( $resourcesPath . '/wpifiles/web.config', $rootpath . '/web.config', array( 'override' => true ) );
                }

                // create zip
                /// @todo if name is empty do not add an extra hyphen
                $filename = self::getProjFileName() . '-wpi.zip';
                $target = $opts['dist']['dir'] . '/' . $filename;
                self::archiveDir( $toppath, $target, true );

                // update feed file
                $feedfile = 'ezpcpmswpifeed.xml';
                pake_copy( $resourcesPath . '/wpifiles/' . $feedfile, $opts['dist']['dir'] . '/' . $feedfile );
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

} 