<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Root
    |--------------------------------------------------------------------------
    |
    | The directory the app scans for media files. In production this is the
    | volume mounted into the container at /media. Overridable for local
    | development/testing so you can point it at a scratch directory.
    |
    */

    'root' => env('MEDIA_ROOT', '/media'),

];
