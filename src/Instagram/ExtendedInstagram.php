<?php


namespace App\Instagram;

use \InstagramAPI\Instagram;

/**
 * Class ExtendedInstagram
 * @package App\Instagram
 */
class ExtendedInstagram extends Instagram
{
    /**
     * @param $username
     * @param $password
     */
    public function changeUser( $username, $password ) {
        $this->_setUser( $username, $password );
    }
}