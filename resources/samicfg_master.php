<?php

use Sami\Sami;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name( '*.php' )
    ->exclude( '//EXCLUDE//' )
    ->in( '//SOURCE//' )
    ;

return new Sami( $iterator, array(
    'title'               => '//TITLE//',
    'build_dir'           => '//OUTPUT//',
    'cache_dir'           => '//CACHEDIR//',
    'theme'               => 'piwik',
    'template_dirs'       => array( '//BASEDIR///resources/samithemes' ),
    //'include_parent_data' => false,
));