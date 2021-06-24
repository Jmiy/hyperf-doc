<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log settings for when a location is not found
    | for the IP provided.
    |
    */

    'log_failures' => true,

    /*
    |--------------------------------------------------------------------------
    | Include Currency in Results
    |--------------------------------------------------------------------------
    |
    | When enabled the system will do it's best in deciding the user's currency
    | by matching their ISO code to a preset list of currencies.
    |
    */

    'include_currency' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Service
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default storage driver that should be used
    | by the framework.
    |
    | Supported: "maxmind_database", "maxmind_api", "ipapi"
    |
    */

    'service' => 'ipapi',

    /*
    |--------------------------------------------------------------------------
    | Storage Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many storage drivers as you wish.
    |
    */

    'services' => [

        'maxmind_database' => [
            'class' => \Torann\GeoIP\Services\MaxMindDatabase::class,
            'database_path' => BASE_PATH . '/storage/app/geoip.mmdb',
            'update_url' => sprintf('https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=%s&suffix=tar.gz', env('MAXMIND_LICENSE_KEY')),
            'locales' => ['en'],
        ],

        'maxmind_api' => [
            'class' => \Torann\GeoIP\Services\MaxMindWebService::class,
            'user_id' => env('MAXMIND_USER_ID'),
            'license_key' => env('MAXMIND_LICENSE_KEY'),
            'locales' => ['en'],
        ],

        'ipapi' => [
            'class' => \Torann\GeoIP\Services\IPApi::class,
            'secure' => true,
            'key' => env('IPAPI_KEY'),
            'continent_path' => BASE_PATH . '/storage/app/continents.json',
            'lang' => 'en',
        ],

        'ipgeolocation' => [
            'class' => \Torann\GeoIP\Services\IPGeoLocation::class,
            'secure' => true,
            'key' => env('IPGEOLOCATION_KEY'),
            'continent_path' => BASE_PATH . '/storage/app/continents.json',
            'lang' => 'en',
        ],

        'ipdata' => [
            'class'  => \Torann\GeoIP\Services\IPData::class,
            'key'    => env('IPDATA_API_KEY'),
            'secure' => true,
        ],

        'ipfinder' => [
            'class'  => \Torann\GeoIP\Services\IPFinder::class,
            'key'    => env('IPFINDER_API_KEY'),
            'secure' => true,
            'locales' => ['en'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Cache Driver
    |--------------------------------------------------------------------------
    |
    | Here you may specify the type of caching that should be used
    | by the package.
    |
    | Options:
    |
    |  all  - All location are cached
    |  some - Cache only the requesting user
    |  none - Disable cached
    |
    */

    'cache' => 'all',

    /*
    |--------------------------------------------------------------------------
    | Cache Tags
    |--------------------------------------------------------------------------
    |
    | Cache tags are not supported when using the file or database cache
    | drivers in Laravel. This is done so that only locations can be cleared.
    |
    */

    'cache_tags' => ['torann-geoip-location'],

    /*
    |--------------------------------------------------------------------------
    | Cache Expiration
    |--------------------------------------------------------------------------
    |
    | Define how long cached location are valid.
    |
    */

    'cache_expires' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Default Location
    |--------------------------------------------------------------------------
    |
    | Return when a location is not found.
    |
    */

    'default_location' => [
        'ip' => '127.0.0.0', //127.0.0.0
        'iso_code' => in_array(env('APP_ENV', 'production'), ['production', 'pre-release']) ? '' : 'US', //US
        'country' => env('APP_ENV', 'production') != 'production' ? 'United States' : '',
        'city' => env('APP_ENV', 'production') != 'production' ? 'New Haven' : '',
        'state' => env('APP_ENV', 'production') != 'production' ? 'CT' : '',
        'state_name' => env('APP_ENV', 'production') != 'production' ? 'Connecticut' : '',
        'postal_code' => env('APP_ENV', 'production') != 'production' ? 06510 : '',
        'lat' => env('APP_ENV', 'production') != 'production' ? 41.31 : 0,
        'lon' => env('APP_ENV', 'production') != 'production' ? -72.92 : 0,
        'timezone' => env('APP_ENV', 'production') != 'production' ? 'America/New_York' : '',
        'continent' => env('APP_ENV', 'production') != 'production' ? 'NA' : '',
        'default' => env('APP_ENV', 'production') != 'production' ? true : false, //
        'currency' => env('APP_ENV', 'production') != 'production' ? 'USD' : '',
    ],

];
