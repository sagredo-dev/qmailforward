<?php

/**
 * qmailforward base storage class
 *
 * @author Philip Weir
 *         Modified by Roberto Puzzanghera
 *
 * Copyright (C) Philip Weir
 *
 * This program is a Roundcube (https://roundcube.net) plugin.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Roundcube. If not, see https://www.gnu.org/licenses/.
 */
abstract class rcube_qmailforward_storage
{
    protected $config;

    /**
     * Object constructor
     *
     * @param mixed $config Roundcube config object
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Retrieve data from database
     *
     * @param string $user   mailbox username
     *        string $domain mailbox domain
     *
     * @return array [$key => $value, ...]
     */
    abstract public function load($user , $domain);

    /**
     * Save POST data to database
     *
     * @param string $user      login username
     * @param array  $domain    login domain
     *
     * @return bool True on success, False on error
     */
    abstract public function save($user, $domain);
}
