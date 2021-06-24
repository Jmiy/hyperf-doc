<?php

namespace App\Utils\Cdn;

use App\Constants\Constant;
use App\Utils\Context;
use App\Utils\Filesystem\Util;

class ResourcesCdn
{

    public static $uriPrefix = []; // 图片路径前缀
    public static $imgUrlCn = ''; //国内
    public static $cdnDomains = []; //css域名

    /**
     * @param int $storeId
     * @return mixed|null
     */
    public static function getContextKey($storeId = 1)
    {
        $key = md5(json_encode([static::class, $storeId]));
        return Context::storeData($key, function () use ($key) {
            return 'app.filesystem.' . $key;
        });
    }

    /**
     * 设置配置信息
     * @param int $storeId 商城id
     * @param array $configData 配置数据
     */
    public static function setConf($storeId = 1, $configData = [])
    {

    }

    /**
     * 获取属性值
     * @param int $storeId 商城id
     * @param array $configData 配置数据
     * @param null|sting|array $key 属性名称
     * @return array|mixed
     */
    public static function getAttribute($storeId = 1, $key = null, $configData = [])
    {
        $configData = static::setConf($storeId, $configData);
        return data_get($configData, $key);
    }

    /**
     * 获取服务器地区
     * @return string 服务器地区
     */

    public static function getArea()
    {
        $_area = explode('_', config('app.serverarea'));
        $area = end($_area);
        return $area;
    }

    /**
     * 获取资源域名
     * @param int $storeId 品牌id
     * @param int $resourceType 资源类型 0:所有 1:图片 2:视频 3:js 4:css 默认:1
     * @param array|null $domain cdn域名
     * @return array 图片cdn域名
     */
    public static function getResourceTypeDomain($storeId = 1, $resourceType = 0, $isCn = false, $domain = null)
    {

        $area = static::getArea();

        $cdnDomains = static::getAttribute($storeId, ($area ? $area . '_' : '') . 'url' . ($resourceType ? '_' . $resourceType : ''));//key组成规则: area_url_1
        $cdnDomains = explode(',', $cdnDomains);

        if (is_array($domain)) {
            $cdnDomains = array_merge($cdnDomains, $domain);
        }

        return array_values(array_filter(array_unique($cdnDomains)));
    }

    /**
     * 获取资源域名
     * @param int $storeId 品牌id
     * @param int $resourceType 资源类型 0:所有 1:图片 2:视频 3:js 4:css 默认:1
     * @param array|null $domain cdn域名
     * @return array 图片cdn域名
     */
    public static function getResourceDomain($storeId = 1, $resourceType = 1, $isCn = false, $domain = null)
    {

        //获取资源cdn数据
        $cdnData = static::getResourceTypeDomain($storeId, $resourceType, $isCn, $domain);

        $num = count($cdnData);
        $contextId = static::getContextKey($storeId) . '.resourceKey';
        $_num = Context::getOrSet($contextId, $num);
        Context::set($contextId, $_num + 1);

        $key = $_num % $num;
        return $cdnData[$key];
    }

    /**
     * 获取资源地址
     * @param int $storeId 品牌id
     * @param string $resourceUrl 资源地址
     * @param int $resourceType 资源类型 0:所有 1:图片 2:视频 3:js 4:css 默认:0
     * @param boolean $isCn 是否使用国内cdn  false:否  true:是 默认：false
     * @param string $wh 宽*高
     * @param int $mode 缩略模式：0-5 详情：https://developer.qiniu.com/dora/manual/1279/basic-processing-images-imageview2
     * @return string
     */
    public static function getResourceUrl($storeId = 1, $resourceUrl = '', $resourceType = 0, $isCn = false, $wh = '', $mode = '0')
    {

        if (empty($resourceUrl)) {
            return '';
        }

        $resourceUrl = parse_url($resourceUrl, PHP_URL_PATH);
        $cdnData = static::getResourceDomain($storeId, $resourceType, $isCn);

        $url = rtrim($cdnData, '/') . '/' . ltrim($resourceUrl, '/');
        $urlParam = static::getUrlParam($wh, $mode);

        return $url . $urlParam;
    }

    /**
     * 获取图片 格式转换、缩略、剪裁 参数
     * @param string $wh 宽*高
     * @param int $mode 缩略模式：0-5 详情：https://developer.qiniu.com/dora/manual/1279/basic-processing-images-imageview2
     * @param string $quality 新图的图片质量  取值范围是[1, 100]，默认75。七牛会根据原图质量算出一个修正值，取修正值和指定值中的小值。
     *    注意：
     *    ● 如果图片的质量值本身大于90，会根据指定值进行处理，此时修正值会失效。
     *    ● 指定值后面可以增加 !，表示强制使用指定值，如100!。
     *    ● 支持图片类型：jpg。
     *    详情：https://developer.qiniu.com/dora/manual/1279/basic-processing-images-imageview2#1
     * @return string
     */
    public static function getUrlParam($wh = '', $mode = '0', $quality = '75')
    {
        $urlParam = '';

        if (empty($wh)) {
            return $urlParam;
        }

        $whData = explode('*', $wh);

        if ((isset($whData[0]) && $whData[0] != 0) || isset($whData[1]) && $whData[1] != 0) {
            $urlParam .= '?imageView2/' . $mode;
        }

        if (isset($whData[0]) && $whData[0] != 0) {
            $urlParam .= '/w/' . $whData[0];
        }

        if (isset($whData[1]) && $whData[1] != 0) {
            $urlParam .= '/h/' . $whData[1];
        }

        if ($urlParam) {
            $urlParam .= '/interlace/1/ignore-error/1/q/' . $quality;
        }

        return $urlParam;
    }

    /**
     * 获取上传到七牛所使用的文件URI
     * @param string $vitualPath 七牛虚拟路径
     * @param string $ext 文件后缀
     * @param string $fileName 文件名
     * @return string 文件URI
     */
    public static function getUploadFileName($resourceType = 1, $vitualPath = '', $ext = null, $fileName = '')
    {
        $fileName = static::getFileName($ext, $fileName);
        $filePath = static::getDistVitualPath($resourceType, $vitualPath);
        return implode('/', [$filePath, $fileName]);
    }

    /**
     * Returns locale independent base name of the given path.
     *
     * @param string $name The new file name
     *
     * @return string containing
     */
    public static function getName($name)
    {
        $originalName = str_replace('\\', '/', $name);
        $pos = strrpos($originalName, '/');
        $originalName = false === $pos ? $originalName : substr($originalName, $pos + 1);

        return $originalName;
    }

    public static function upload($filePath, $files = null, $vitualPath = '', $is_del = false, $isCn = false, $fileName = '', $resourceType = 1, $extData = Constant::PARAMETER_ARRAY_DEFAULT)
    {

    }

    /**
     * @param $url
     * @return array
     * 删除空间文件
     */
    public static function deleteFiles($url)
    {

    }

    /**
     * 获取默认的响应数据结构
     * @param int $code 响应状态码
     * @param string $msg 响应提示
     * @param array $data 响应数据
     * @return array $data
     */
    public static function getDefaultResponseData($code = Constant::PARAMETER_INT_DEFAULT, $msg = Constant::PARAMETER_STRING_DEFAULT, $data = Constant::PARAMETER_ARRAY_DEFAULT)
    {
        return CdnManager::getDefaultResponseData($code, $msg, $data);
    }

    /**
     * 获取目的云存储虚拟路径
     * @param int $resourceType 文件类型 1：图片 2：视频
     * @param string $vitualPath 云存储虚拟路径
     * @return string 目的云存储虚拟路径
     */
    public static function getDistVitualPath($resourceType = 1, $vitualPath = '')
    {
        $path = implode('/', [
            (isset(static::$uriPrefix[$resourceType]) && static::$uriPrefix[$resourceType] ? static::$uriPrefix[$resourceType] : ''),
            $vitualPath,
            date('YmdHis')
        ]);
        return static::normalizePath($path);
    }

    public static function setFile($file=null,$storeId=0){
        $contextId = static::getContextKey($storeId) . '.file';
        return Context::set($contextId, $file);
    }

    public static function getFile($storeId=0){
        $contextId = static::getContextKey($storeId) . '.file';
        return Context::get($contextId);
    }

    /**
     * 获取文件名
     * @param string $ext
     * @param string $fileName
     * @return string 文件名
     */
    public static function getFileName($ext = null, $fileName = '')
    {
        $fileExt = $ext;
        if(empty($fileExt)){
            $file = static::getFile();
            $fileExt =$file ? ('.' . $file->getExtension()) : '';
        }

        return $fileName ? $fileName : (time() . rand(100, 999) . $fileExt);
    }

    /**
     * Normalize path.
     *
     * @param string $path
     *
     * @return string
     * @throws LogicException
     *
     */
    public static function normalizePath($path)
    {
        return Util::normalizePath($path);
    }

}
