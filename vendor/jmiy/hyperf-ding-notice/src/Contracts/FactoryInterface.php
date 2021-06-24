<?php

namespace DingNotice\Contracts;

interface FactoryInterface
{

    /**
     * @param string $robot
     * @return $this
     */
    public function with($robot = 'default');

    /**
     * @param string $content
     * @return mixed
     */
    public function text($content = '');

    /**
     * @param $title
     * @param $text
     * @return mixed
     */
    public function action($title, $text);

    /**
     * @param array $mobiles
     * @param bool $atAll
     * @return $this
     */
    public function at($mobiles = [], $atAll = false);

    /**
     * @param $title
     * @param $text
     * @param $url
     * @param string $picUrl
     * @return mixed
     */
    public function link($title, $text, $url, $picUrl = '');

    /**
     * @param $title
     * @param $markdown
     * @return mixed
     */
    public function markdown($title, $markdown);

    /**
     * @param $title
     * @param $markdown
     * @param int $hideAvatar
     * @param int $btnOrientation
     * @return mixed
     */
    public function actionCard($title, $markdown, $hideAvatar = 0, $btnOrientation = 0);

    /**
     * @return mixed
     */
    public function feed();
}
