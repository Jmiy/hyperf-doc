<?php

namespace App\Utils\Cdn;

use App\Services\DictStoreService;
use App\Constants\Constant;
use App\Utils\Context;
use App\Utils\FunctionHelper;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\Utils\Str;
use League\Flysystem\AdapterInterface;

class AwsS3Cdn extends ResourcesCdn
{

    /**
     * 设置配置信息
     * @param int $storeId 商城id
     * @param array $configData 配置数据
     */
    public static function setConf($storeId = 1, $configData = []) {

        $key = static::getContextKey($storeId);

        return Context::storeData($key, function () use ($storeId, $configData) {
            //通过应用容器 获取配置类对象
            $config = getConfig();

            //获取 disk 名称
            $diskName = DictStoreService::getByTypeAndKey($storeId, 'filesystems.disks', 'aws', true);
            if (empty($diskName)) {
                return [];
            }

            $configKey = 'file.storage.' . $diskName;

//            $configData = $config->get($configKey, $configData);
//            if(!empty($configData)){
//                return $configData;
//            }

            //获取driver配置
            $diskConf = DictStoreService::getListByType($storeId, $diskName);
            if ($diskConf->isEmpty()) {
                return [
                    'diskName' => $diskName,
                ];
            }

            //设置配置
            //获取默认配置
            $defaultDiskConf = $config->get('file.storage.s3');
            data_set($defaultDiskConf,'diskName',$diskName);
//            [
//                'driver' => \Hyperf\Filesystem\Adapter\S3AdapterFactory::class,
//                'credentials' => [
//                    'key' => env('S3_KEY'),
//                    'secret' => env('S3_SECRET'),
//                ],
//                'region' => env('S3_REGION'),
//                'version' => 'latest',
//                'bucket_endpoint' => false,
//                'use_path_style_endpoint' => false,
//                'endpoint' => env('S3_ENDPOINT'),
//                'bucket_name' => env('S3_BUCKET'),
//            ];

            //更新配置
            foreach ($diskConf as $item) {
                $key = data_get($item, 'conf_key');

                if($key == 'driver'){
                    continue;
                }

                switch ($key) {
                    case 'key':
                    case 'secret':
                        $key = 'credentials.'.$key;

                        break;

                    case 'bucket':
                        $key = 'bucket_name';

                        break;

                    default:
                        break;
                }

                $value = data_get($item, 'conf_value', data_get($defaultDiskConf, $key, ''));
                data_set($defaultDiskConf, $key, $value);
            }

            $config->set($configKey, $defaultDiskConf);

            return $config->get($configKey, $configData);
        });
    }

    /**
     * 获取云存储对象
     * @param array $extData
     * @return array  云存储对象
     */
    public static function getDisk($extData)
    {

        $rs = static::getDefaultResponseData(Constant::ORDER_STATUS_SHIPPED_INT, Constant::PARAMETER_STRING_DEFAULT);

        $storeId = data_get($extData, Constant::DB_TABLE_STORE_ID, 0);
        if (empty($storeId)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 0);
            data_set($rs, Constant::RESPONSE_MSG_KEY, 'store ID 异常');
            return $rs;
        }

        $configData = self::setConf($storeId);
        $diskName = data_get($configData,'diskName');
        $driver = data_get($configData,'driver');
        if(empty($diskName)){
            data_set($rs, Constant::RESPONSE_CODE_KEY, 2);
            data_set($rs, Constant::RESPONSE_MSG_KEY, $storeId . ' s3 配置不存在');
            return $rs;
        }

        if(empty($driver)){
            data_set($rs, Constant::RESPONSE_CODE_KEY, 3);
            data_set($rs, Constant::RESPONSE_MSG_KEY, $storeId . ' '.$diskName.' s3 云存储配置不存在');
            return $rs;
        }

        //获取 aws s3 文件系统对象
        $factory = ApplicationContext::getContainer()->get(FilesystemFactory::class);
        $filesystem = $factory->get($diskName);

        data_set($rs, Constant::RESPONSE_DATA_KEY, $filesystem);

        return $rs;
    }

    public static function uploadBase64File($file = null, $vitualPath = '', $is_del = false, $isCn = false, $fileName = '', $resourceType = 1, $extData = Constant::PARAMETER_ARRAY_DEFAULT)
    {

        $diskData = static::getDisk($extData);
        if (data_get($diskData, Constant::RESPONSE_CODE_KEY, 0) != 1) {
            return $diskData;
        }
        $filesystem = data_get($diskData, Constant::RESPONSE_DATA_KEY, null);

        $_data = [
            Constant::RESOURCE_TYPE => $resourceType, //资源类型 1:图片 2:视频 3:js 4:css 默认:1
        ];
        $rs = static::getDefaultResponseData(Constant::ORDER_STATUS_SHIPPED_INT, Constant::PARAMETER_STRING_DEFAULT, $_data);

        $fileExtension = '';
        if (strpos($file, 'data:image/png;base64') !== false) {
            $data = explode(',', $file); //data:image/png;base64,iVBORw0KGgoAAAANSUhEU
            $fileContents = base64_decode(end($data));

            $fileExtension = explode('/', $data[0]); //data:image/png;base64,
            unset($data);
            $fileExtension = explode(';', $fileExtension[1]);
            $fileExtension = '.' . $fileExtension[0];
        } else {
            $fileContents = base64_decode($file);
        }

        $path = static::getUploadFileName($resourceType, $vitualPath, $fileExtension, $fileName);

        $config = [
            'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
            //'mimetype'=>'',
        ];
        $isPut = $filesystem->write(
            $path,
            $fileContents,
            $config
        );

        if (empty($isPut)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 0);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '文件上传失败');

            return $rs;
        }

        $storeId = data_get($extData, Constant::DB_TABLE_STORE_ID, 0);
        $url = static::getResourceUrl($storeId, $path);

        data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_URL, $url);
        data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, $url);
        data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::ORDER_STATUS_SHIPPED_INT);
        data_set($rs, Constant::RESPONSE_MSG_KEY, Constant::PARAMETER_STRING_DEFAULT);

        return $rs;
    }

    /**
     * 上传文件
     * @param string $filePath 图片在服务器的绝对路径
     * @param string $resourceType 文件类型 1：图片 2：视频
     * @param string $vitualPath 云存储虚拟路径
     * @param boolean $is_del 是否删除原文件  false:否  true：是  默认:false
     * @param boolean $isCn 是否使用国内cdn  false:否  true：是  默认:false
     * @return array 上传结果
     */
    public static function upload($filePath, $files = null, $vitualPath = '', $is_del = false, $isCn = false, $fileName = '', $resourceType = 1, $extData = Constant::PARAMETER_ARRAY_DEFAULT)
    {

        $diskData = static::getDisk($extData);
        if (data_get($diskData, Constant::RESPONSE_CODE_KEY, 0) != 1) {
            return $diskData;
        }
        $filesystem = data_get($diskData, Constant::RESPONSE_DATA_KEY, null);

        $_data = [
            Constant::RESOURCE_TYPE => $resourceType, //资源类型 1:图片 2:视频 3:js 4:css 默认:1
        ];
        $rs = static::getDefaultResponseData(Constant::ORDER_STATUS_SHIPPED_INT, Constant::PARAMETER_STRING_DEFAULT, $_data);

        $files = is_array($files) ? $files : [$filePath => $files];
        $uploadData = [];
        $distVitualPath = static::getDistVitualPath($resourceType, $vitualPath);
        $storeId = data_get($extData, Constant::DB_TABLE_STORE_ID, 0);
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                data_set($uploadData, $key, static::upload(null, $file, $vitualPath, $is_del, $isCn, $fileName, $resourceType, $extData));
                continue;
            }

            if (!($file instanceof UploadedFile)) {

                static::setFile(null);
                $rs = static::uploadBase64File($file, $vitualPath, $is_del, $isCn, $fileName, $resourceType, $extData);

                data_set($uploadData, $key, $rs);
                continue;
            }

            if (!$file->isValid()) {
                data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_URL, Constant::PARAMETER_STRING_DEFAULT);
                data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, Constant::PARAMETER_STRING_DEFAULT);
                data_set($rs, Constant::RESPONSE_CODE_KEY, 10031);
                data_set($rs, Constant::RESPONSE_MSG_KEY, $file->getError());//
                $uploadData[$key] = $rs;
                continue;
            }

            static::setFile($file);
            $originalName = static::getName($file->getClientFilename());//原始文件名

            if (data_get($extData, 'use_origin_name', Constant::PARAMETER_INT_DEFAULT)) {//如果需要使用原始文件名，就获取客户原始文件名
                $fileName = $originalName;
            }else{
                $extension = $file->getExtension();
                $fileName = Str::random(10) . '.'.$extension;
            }

            $config = [
                'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
                //'mimetype'=>'',
            ];
            $path = static::normalizePath(implode('/', [$distVitualPath, $fileName]));
            $stream = fopen($file->getRealPath(), 'r+');
            $filesystem->writeStream(
                $path,
                $stream,
                $config
            );
            fclose($stream);

            $url = static::getResourceUrl($storeId, $path);

            data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_URL, $url);
            data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, $url);
            data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::ORDER_STATUS_SHIPPED_INT);
            data_set($rs, Constant::RESPONSE_MSG_KEY, Constant::PARAMETER_STRING_DEFAULT);
            data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'originalName', $originalName);

            data_set($uploadData, $key, $rs);
        }

        return data_get($uploadData, $filePath, Constant::PARAMETER_ARRAY_DEFAULT);
    }

}
