<?php

namespace App\Utils\Cdn;

use Hyperf\HttpMessage\Upload\UploadedFile;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Processing\PersistentFop;

class QiniuCdn extends ResourcesCdn {

    public static $uploadUrl = 'http://up-z2.qiniu.com';
    public static $accessKey = 'fcJJ_cIBdKudnO1P3BsXjnEpWDysyqc7jFfgMW2Q';
    public static $secretKey = 'esorL4VbeHRrKPGtXz-sWZRBEU4sBCkDPHxd8lnI';
    public static $ext = ""; // 图片样式 -shop
    public static $bucket = '-cube-resources'; //'us-cube-resources'; //'cn-cube-resources'; //'buguniao-shop-cn'; //cube国内华南bucket
    public static $allBucket = [
        'us' => 'us-cube-resources',
        'asia' => 'asia-cube-resources',
        'cn' => 'cn-cube-resources',
    ]; //'us-cube-resources'; //'cn-cube-resources'; //'buguniao-shop-cn'; //cube国内华南bucket
    //public static $uriPrefix = "QiniuCdn/cube/resources/"; //图片路径前缀
    public static $uriPrefix = [
        1 => "QiniuCdn/mall/resources/img/", //图片路径前缀
        2 => "QiniuCdn/mall/resources/video/", //视频路径前缀
        3 => "QiniuCdn/mall/jscss/", //js路径前缀
        4 => "QiniuCdn/mall/jscss/", //css路径前缀
    ]; //资源路径前缀
    public static $cdnDomains = [
        'us' => [
            1 => ['http://us-cube-img.stosz.com'], // 图片域名
            2 => ['http://us-cube-video.stosz.com'], //视频域名
            3 => ['http://us-cube-js-css.stosz.com'], // js域名
            4 => ['http://us-cube-js-css.stosz.com'], //css域名
        ],
        'asia' => [
            1 => ['http://asia-cube-img.stosz.com'], // 图片域名
            2 => ['http://asia-cube-video.stosz.com'], //视频域名
            3 => ['http://asia-cube-js-css.stosz.com'], // js域名
            4 => ['http://asia-cube-js-css.stosz.com'], //css域名
        ],
        'cn' => [
            1 => ['http://cn-cube-cdn.stosz.com'], // 图片域名
            2 => ['http://cn-cube-cdn.stosz.com'], //视频域名
            3 => ['http://cn-cube-cdn.stosz.com'], // js域名
            4 => ['http://cn-cube-cdn.stosz.com'], //css域名
        ],
    ];
    //public static $file = null; //文件对象

//    public static $imgUrlCn = 'http://imgcn.stosz.com'; //国内
//    public static $videoUrl = 'http://cdn.bgnht.com'; //vedio加速域名
//    public static $imgUrl = 'http://img.stosz.com'; //国外
//    public static $imgDomain = ['http://img.stosz.com', 'http://awscdn.szcuckoo.net']; // 图片域名
//    public static $videoDomain = ['http://cdn.bgnht.com']; //视频域名 http://cdn.bgnht.com
//    public static $cssDomain = ['http://us-cube-js-css.stosz.com', 'http://us-cube-js-css1.stosz.com', 'http://us-cube-js-css2.stosz.com', 'http://us-cube-js-css3.stosz.com', 'http://us-cube-js-css5.stosz.com']; //css域名 http://cdn.bgnht.com
//    public static $jsDomain = ['http://us-cube-js-css1.stosz.com', 'http://us-cube-js-css2.stosz.com', 'http://us-cube-js-css3.stosz.com', 'http://us-cube-js-css5.stosz.com', 'http://us-cube-js-css.stosz.com',]; // js域名
//    public static $imgDomain = ['http://us-cube-img.stosz.com', 'http://us-cube-img1.stosz.com', 'http://us-cube-img2.stosz.com', 'http://us-cube-img3.stosz.com', 'http://us-cube-img5.stosz.com']; // 图片域名
//    public static $videoDomain = ['http://us-cube-video.stosz.com', 'http://us-cube-video1.stosz.com', 'http://us-cube-video2.stosz.com']; //视频域名 http://cdn.bgnht.com

    /**
     * 获取七牛 bucket
     * @param string $bucket
     * @return string 七牛 bucket
     */
    public static function getBucket($bucket = "") {
        if (empty($bucket)) {
            $area = static::getArea();
            $bucket = isset(static::$allBucket[$area]) ? static::$allBucket[$area] : static::$allBucket['asia'];
        }
        return $bucket;
    }

    /**
     * 获取上传凭证
     * @param  string $bucket 七牛服务器存储桶
     * @return string        上传凭证
     */
    public static function getUploadToken($bucket = "") {

        $bucket = static::getBucket($bucket);

        $auth = static::getAuth();

        return $auth->uploadToken($bucket);
    }

    /**
     * 上传文件
     * @param string $filePath 图片在服务器的绝对路径
     * @param string $resourceType 文件类型 1：图片 2：视频
     * @param string $vitualPath 七牛虚拟路径
     * @param boolean $is_del  是否删除原文件  false:否  true：是  默认:false 
     * @param boolean $isCn    是否使用国内cdn  false:否  true：是  默认:false
     * @return array 上传结果   array(
      "state" => 'SUCCESS',//状态：SUCCESS：成功  FAILED：失败
      Constant::FILE_URL => static::getResourceUrl($url, 1, $isCn), //国外cdn绝对地址 如 http://xxx.com/ddd.jpg
      Constant::FILE_TITLE => static::getResourceUrl($url, 1, $isCn), //国外cdn绝对地址 如 http://xxx.com/ddd.jpg
      Constant::DB_TABLE_TYPE => 'jpg',//图片类型
      Constant::RESPONSE_DATA_KEY => $info,//七牛接口响应数据
      )
     */
    public static function upload($filePath, $file = null, $vitualPath = '', $is_del = false, $isCn = false, $fileName = '', $resourceType = 0, $extData = Constant::PARAMETER_ARRAY_DEFAULT) {

        $info = ['state' => 'FAIL'];

        if ($file instanceof UploadedFile) {
            $filetype = $file->getMimeType();
            static::setFile($file);
        } else {
            $filetype = 'image';
        }

        //获取资源类型
//        var_dump($file->getMimeType());//根据文件内容获取文件类型
//        var_dump($file->extension());//根据文件内容获取文件后缀，这个比较准确
//        var_dump($file->guessExtension());
//        var_dump($file->getClientOriginalExtension());//根据文件名后缀获取文件后缀，这个不够准确
        $ext = static::$ext; //文件后缀
        if ($resourceType == 0) {//如果没有资源类型，就根据文件MimeType判断资源类型
            $resourceType = 1; //资源类型 1:图片 2:视频 3:js 4:css 默认:1
            if (strstr($filetype, 'video')) {
                $resourceType = 2;
            } else {
                $resourceTypeData = [
                    'js' => 3,
                    'css' => 4,
                ];

                $fileExt = $file->getClientOriginalExtension(); //获取文件后缀
                $fileExt = strtolower($fileExt ? $fileExt : '');
                $resourceType = isset($resourceTypeData[$fileExt]) ? $resourceTypeData[$fileExt] : $resourceType;
            }
        }

        switch ($resourceType) {
            case 2://视频
                $ext = '';
                break;

            default://非视频
                if (filesize($filePath) > 5 * 1024 * 1024) {
                    $info[Constant::RESPONSE_DATA_KEY] = [Constant::RESPONSE_CODE_KEY => 3, 'msg' => '上传图片超过5M'];
                    return $info;
                }
                $filetype = 'application/octet-stream';
                break;
        }

        $fileName = static::getUploadFileName($resourceType, $vitualPath, $ext, $fileName); // 上传到七牛后保存的文件名
        $uploadManager = new UploadManager(); // 初始化 UploadManager 对象并进行文件的上传。

        $token = static::getUploadToken(); //上传凭证
        $info = $uploadManager->putFile($token, $fileName, $filePath, null, $filetype); // 调用 UploadManager 的 putFile 方法进行文件的上传。
        list($ret, $err) = $info;
        $filename = ($err == null) ? $ret['key'] : '';
        $info = [
            "state" => 'FAILED',
            Constant::FILE_URL => '',
            Constant::FILE_TITLE => '',
            Constant::DB_TABLE_TYPE => $file->getExtension(),
            Constant::RESPONSE_DATA_KEY => $info,
            Constant::RESOURCE_TYPE => $resourceType, //资源类型 1:图片 2:视频 3:js 4:css 默认:1
        ];
        $info[Constant::RESPONSE_DATA_KEY][Constant::RESPONSE_CODE_KEY] = ($err == null) ? 2 : 3; //0未处理，1进行中，2推送成功，3推送失败
//        $areaBucket = static::getBucket();
//        foreach (static::$allBucket as $key => $bucket) {
//            if ($areaBucket !== $bucket) {
//                $token = static::getUploadToken($bucket); //上传凭证
//                $info = $uploadManager->putFile($token, $fileName, $filePath, null, $filetype); // 调用 UploadManager 的 putFile 方法进行文件的上传。
//            }
//        }
        //如果是视频要转码
        if ($resourceType == 2 && $err == null) {
            //判断是否需转编码
            $uri = static::getResourceUrl($filename, $resourceType, $isCn) . '?avinfo'; //国外cdn绝对地址 如 http://xxx.com/ddd.jpg
            $isChange = static::isVideoChange($uri);
            if ($isChange) {
                $ret = static::videoCodeChange($filename);
                $info[Constant::RESPONSE_DATA_KEY]['videoCodeChange'] = $ret;
                if ($ret[Constant::RESPONSE_CODE_KEY] === 0) {//如果转码成功，就使用转码后视频的地址
                    $filename = $ret['newName'];
                }
            }
        }

        if ($err == null) {
            $url = $filename . $ext;
            $info['state'] = 'SUCCESS';
            $url = static::getResourceUrl($url, $resourceType, $isCn); //国外cdn绝对地址 如 http://xxx.com/ddd.jpg
            $info[Constant::FILE_URL] = $url; //国外cdn绝对地址 如 http://xxx.com/ddd.jpg
            $info[Constant::FILE_TITLE] = $url; //国外cdn绝对地址 如 http://xxx.com/ddd.jpg
            $prefetchUrl = [$url];
            $prefetchResult = static::prefetch($prefetchUrl, $resourceType);
            $info[Constant::RESPONSE_DATA_KEY]['prefetchResult'] = $prefetchResult;
        }

        if ($is_del) {//如果要删除服务器预源文件，就删除
            unlink($filePath);
        }

        return $info;
    }

    /**
     * 获取 Auth
     * @return Auth
     */
    public static function getAuth() {
        $auth = new Auth(self::$accessKey, self::$secretKey);
        return $auth;
    }

    /**
     * @param $url 文件路径
     * @return array
     */
    public static function videoCodeChange($url) {

        $auth = self::getAuth(); //获取 Auth

        $config = new \Qiniu\Config();
        //$config->useHTTPS=true;
        $pfop = new PersistentFop($auth, $config);

        //另存新文件
        $url_array = explode('/', $url);
        $lastExt = end($url_array);
        list($oldName, $ext) = explode('.', $lastExt);
        $newName = date("YmdHis") . rand(0, 100) . '.' . $ext;
        $name = str_replace($lastExt, $newName, $url); //转码后视频在七牛服务器的保存路径

        $pipeline = 'zhuanma'; //转码是使用的队列名称。 https://portal.qiniu.com/mps/pipeline
        $force = false;
        //$notifyUrl = 'http://375dec79.ngrok.com/notify.php';
        $notifyUrl = ''; //转码完成后通知到你的业务服务器。
        $bucket = static::getBucket();
        //要转码的文件所在的空间和文件名。
        $key = $url;

        //要进行转码的转码操作。 http://developer.qiniu.com/docs/v6/api/reference/fop/av/avthumb.html
        $fops = "avthumb/mp4/s/640x360/vb/1.4m|saveas/" . \Qiniu\base64_urlSafeEncode($bucket . ":" . $name);
        $data = $pfop->execute($bucket, $key, $fops, $pipeline, $notifyUrl, $force);
        list($id, $err) = $data;
        $msg = [
            'data' => $data,
            'id' => $id,
            'err' => $err,
            'bucket' => $bucket,
            'name' => $name,
            'fops' => $fops,
            'key' => $key,
            'pipeline' => $pipeline,
            'notifyUrl' => $notifyUrl,
            'force' => $force,
        ];
        if ($err != null) {//如果转码失败，就直接返回失败原因
            return [
                Constant::RESPONSE_CODE_KEY => 10,
                'msg' => $msg,
            ];
        }

        //转码成功 删除原有文件
        $ret = self::deleteFiles($url);
        if ($ret[Constant::RESPONSE_CODE_KEY] != 0) {
            //return ['ret'=>0,'msg'=>$err];
        }

        return [Constant::RESPONSE_CODE_KEY => 0, 'newName' => $name, 'msg' => $msg];
    }

    public static function isVideoChange($url) {

        $ret = file_get_contents($url);

        $info = json_decode($ret, true);

        $code = $info['streams'][0]['codec_name'];

        if ($code == 'h264') {
            return false;
        }

        return true;
    }

    /**
     * @param $url
     * @return array
     * 删除空间文件
     */
    public static function deleteFiles($url) {
        $auth = self::getAuth();

        $config = new \Qiniu\Config();
        $bucketManager = new \Qiniu\Storage\BucketManager($auth, $config);
        $bucket = static::getBucket();
        $err = $bucketManager->delete($bucket, $url);

        return [Constant::RESPONSE_CODE_KEY => ($err ? 11 : 0), 'msg' => ($err ? '删除七牛服务器文件失败' : '')];
    }

    /**
     * 文件预取
     * @param array $urls 待预取的文件列表，文件列表最多一次100个，目录最多一次10个
     * @param int $resourceType  资源类型 1:图片 2:视频 3:js 4:css 默认:1
     * @return array 文件预取结果
     */
    public static function prefetch($urls = [], $resourceType = 1) {

        $auth = self::getAuth();
        //待预取的文件列表，文件列表最多一次100个，目录最多一次10个
        //参考文档：http://developer.qiniu.com/article/fusion/api/prefetch.html
//        $urls = array(
//            "http://phpsdk.qiniudn.com/qiniu.jpg",
//            "http://phpsdk.qiniudn.com/qiniu2.jpg",
//        );
        $cdnManager = new \Qiniu\Cdn\CdnManager($auth);

        $urls = static::getPrefetchUrls($urls, $resourceType);

        $pageSize = 100;
        $page = ceil(count($urls) / $pageSize);

        $rs = [];
        for ($i = 0; $i < $page; $i++) {
            $offset = $i * $pageSize;
            $_urls = array_slice($urls, $offset, $pageSize);

            list($prefetchResult, $prefetchErr) = $cdnManager->prefetchUrls($_urls);
            if ($prefetchErr != null) {

//                $prefetchErr = [
//                    "code" => 400033,
//                    "error" => "prefetch url limit error",
//                    "requestId" => "",
//                    "taskIds" => NULL,
//                    "invalidUrls" => NULL,
//                    "quotaDay" => 100,
//                    "surplusDay" => 49,
//                ];

                $rs[] = $prefetchErr;
            } else {
                /*
                 * array:7 [
                 *   "code" => 200
                 *   "error" => "success"
                 *   "requestId" => "5b8a6b83d308f60625650144"
                 *   "taskIds" => array:1 [
                 *     "http://us-cube-img.stosz.com/QiniuCdn/cube/resources/sxx/20180901/1535797948723.jpeg" => "5b8a6b83d308f60625650145"
                 *   ]
                 *   "invalidUrls" => null
                 *   "quotaDay" => 100
                 *   "surplusDay" => 93
                 * ]
                 */
                //dump($prefetchResult);
                $rs[] = $prefetchResult;
            }
        }

        return $rs;
    }

    /**
     * 
     * @param array $urls
     * @param int $resourceType  资源类型 1:图片 2:视频 3:js 4:css 默认:1
     * @return string
     */
    public static function getPrefetchUrls($urls = [], $resourceType = 1) {

        //获取资源cdn数据
        $cdnData = static::getResourceTypeDomain($resourceType);

        $prefetchUrl = [];
        foreach ($urls as $url) {
            $url = parse_url($url, PHP_URL_PATH);
            foreach ($cdnData as $domain) {
                $prefetchUrl[] = $domain . '/' . ltrim($url, '/');
            }
        }

        $resourcesPrefetchUrl = $prefetchUrl;

        //获取资源cdn数据
        switch ($resourceType) {
            case 1://1:图片
                $resourcesWh = config('resources');
                $resourcesWhData = \App\Utils\Resources::getResourcesWh($resourcesWh);
                foreach ($resourcesWhData as $wh) {
                    $urlParam = static::getUrlParam($wh, 0);
                    if ($urlParam) {
                        foreach ($prefetchUrl as $url) {
                            $resourcesPrefetchUrl[] = $url . $urlParam;
                        }
                    }
                }
                break;
            default:
                break;
        }

        return $resourcesPrefetchUrl;
    }

}
