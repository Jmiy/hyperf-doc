#!/usr/bin/env sh

/opt/swoole/script/php/swoole_php /opt/swoole/node-agent/src/node.php &

php /opt/www/bin/hyperf.php start

