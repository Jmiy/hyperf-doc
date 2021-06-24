<?php

namespace App\Utils\Cdn;

use App\Services\DictStoreService;
use App\Utils\Context;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\Utils\ApplicationContext;
use Illuminate\Support\Facades\Storage;
use App\Constants\Constant;

class UploadCdn extends ResourcesCdn {

    public static function uploadBase64File($file, $vitualPath, $fileName, $resourceType, $extData = Constant::PARAMETER_ARRAY_DEFAULT) {
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

        $_fileName = static::getUploadFileName($resourceType, $vitualPath, $fileExtension, $fileName);

        //获取 aws s3 文件系统对象
        $factory = ApplicationContext::getContainer()->get(FilesystemFactory::class);
        $filesystem = $factory->get('local');
        $isUploaded = $filesystem->write(
            $_fileName,
            $fileContents
        );

        if (empty($isUploaded)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '上传失败');
            return $rs;
        }

        $path = config('file.storage.local.root');
        $_fileName = '/' . $_fileName;
        data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_TYPE, $fileExtension);
        data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_URL, $_fileName);
        data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, $path . $_fileName);

        return $rs;
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
    public static function upload($filePath = null, $files = null, $vitualPath = '/upload/file/', $is_del = false, $isCn = false, $fileName = '', $resourceType = 1, $extData = Constant::PARAMETER_ARRAY_DEFAULT) {

        $_data = [
            Constant::RESOURCE_TYPE => $resourceType, //资源类型 1:图片 2:视频 3:js 4:css 默认:1
        ];
        $rs = static::getDefaultResponseData(Constant::ORDER_STATUS_SHIPPED_INT, Constant::PARAMETER_STRING_DEFAULT, $_data);

        $files = is_array($files) ? $files : [$filePath => $files];
        $uploadData = [];
        if (is_array($files)) {

            $path = config('file.storage.local.root');
            $distVitualPath = static::getDistVitualPath($resourceType, $vitualPath);
            $destinationPath = implode('/', [$path, $distVitualPath]);

            foreach ($files as $key => $file) {

                if (is_array($file)) {
                    data_set($uploadData, $key, static::upload(null, $file, $vitualPath, $is_del, $isCn, $fileName, $resourceType, $extData));
                    continue;
                }

                if (!($file instanceof UploadedFile)) {
                    //设置 file 到协程上下文，以便后续使用
                    static::setFile(null);
                    data_set($uploadData, $key, static::uploadBase64File($file, $vitualPath, $fileName, $resourceType, $extData));
                    continue;
                }

                if (!$file->isValid()) {
                    data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_URL, Constant::PARAMETER_STRING_DEFAULT);
                    data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, Constant::PARAMETER_STRING_DEFAULT);
                    data_set($rs, Constant::RESPONSE_CODE_KEY, 10031);
                    data_set($rs, Constant::RESPONSE_MSG_KEY, $file->getError());
                    $uploadData[$key] = $rs;
                    continue;
                }

                $filetype = $file->getMimeType();

                //设置 file 到协程上下文，以便后续使用
                static::setFile($file);
                $_fileName = static::getFileName();

                $url = '/' . static::getUploadFileName($resourceType, $vitualPath, '', $_fileName);

                //$file->moveTo($destinationPath.'/'.$_fileName);

                $factory = ApplicationContext::getContainer()->get(FilesystemFactory::class);
                $filesystem = $factory->get('local');
                $path = static::normalizePath($url);
                $stream = fopen($file->getRealPath(), 'r+');
                $isUploaded = $filesystem->writeStream(
                    $path,
                    $stream
                );
                fclose($stream);

                data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_TYPE, $filetype);
                data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_URL, $url);
                data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, implode('/', [$destinationPath, $_fileName]));
                data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::ORDER_STATUS_SHIPPED_INT);
                data_set($rs, Constant::RESPONSE_MSG_KEY, Constant::PARAMETER_STRING_DEFAULT);

                data_set($uploadData, $key, $rs);
            }
        }

        return data_get($uploadData, $filePath, Constant::PARAMETER_ARRAY_DEFAULT);
    }

}
