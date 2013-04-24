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
    //'theme'               => 'enhanced',
    //'cache_dir'           => __DIR__.'/../cache/zf2',
    //'include_parent_data' => false,
));