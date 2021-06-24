<?php

namespace App\Utils\Encryption;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Hyperf\Utils\Str;

class EncryptionServiceProvider
{

    // 实现一个 __invoke() 方法来完成对象的生产，方法参数会自动注入一个当前的容器实例
    public function __invoke(ContainerInterface $container)
    {
        $configInterface = $container->get(ConfigInterface::class);

        $config = $configInterface->get('app');

        // If the key starts with "base64:", we will need to decode the key before handing
        // it off to the encrypter. Keys may be base-64 encoded for presentation and we
        // want to make sure to convert them back to the raw bytes before encrypting.
        if (Str::startsWith($key = $this->key($config), 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return make(Encrypter::class, ['key' => $key, 'cipher' => $config['cipher']]);
    }

    /**
     * Extract the encryption key from the given configuration.
     *
     * @param array $config
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function key(array $config)
    {
        return tap($config['key'], function ($key) {
            if (empty($key)) {
                throw new RuntimeException(
                    'No application encryption key has been specified.'
                );
            }
        });
    }
}
