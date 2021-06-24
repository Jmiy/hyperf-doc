# GeoIP for Hyperf

[torann/geoip](https://github.com/Torann/laravel-geoip)

## 安装

```
composer require jmiy/geoip
```

## 配置

创建配置文件

```shell
php bin/hyperf.php vendor:publish jmiy/geoip
```

配置如下

```php
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

    'cache_expires' => 30,

    /*
    |--------------------------------------------------------------------------
    | Default Location
    |--------------------------------------------------------------------------
    |
    | Return when a location is not found.
    |
    */

    'default_location' => [
        'ip' => '127.0.0.0',
        'iso_code' => 'US',
        'country' => 'United States',
        'city' => 'New Haven',
        'state' => 'CT',
        'state_name' => 'Connecticut',
        'postal_code' => '06510',
        'lat' => 41.31,
        'lon' => -72.92,
        'timezone' => 'America/New_York',
        'continent' => 'NA',
        'default' => true,
        'currency' => 'USD',
    ],

];

```

## 使用

文档地址 https://learnku.com/courses/laravel-package/2019/get-the-corresponding-geo-location-information-through-ip-toranngeoip/2024

### 助手函数

本组件实现了与 torann/geoip 一模一样的助手函数，可以按照以下方式

## 使用 
torann/geoip 使用起来非常方便，它已经提供了辅助方法和 Facade：

```php
<?php
//上面两种方式效果相同，都会根据传入的 IP 返回 \Torann\GeoIP\Location 对象。这个对象包含了对应的位置信息。
geoip($ip);
GeoIp::getLocation($ip);
```

Determine the geographical location and currency of website visitors based on their IP addresses.
- [GeoIP for Hyperf on GitHub](https://github.com/Jmiy/geoip) 

## Contributions

Many people have contributed to project since its inception.

Thanks to:
- [Hyperf Group](https://github.com/hyperf/hyperf)
- [limingxinleo](https://github.com/limingxinleo/i-cache)
- [Dwight Watson](https://github.com/dwightwatson)
- [nikkiii](https://github.com/nikkiii)
- [jeffhennis](https://github.com/jeffhennis)
- [max-kovpak](https://github.com/max-kovpak)
- [dotpack](https://github.com/dotpack)
- [Jess Archer](https://github.com/jessarcher)
