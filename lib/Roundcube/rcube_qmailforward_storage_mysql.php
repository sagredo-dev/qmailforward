<?php
/**
 * qmailforward storage class
 *
 * Class to handle the SQL work for qmailforward
 *
 * @author Philip Weir
 *         Modified by Roberto Puzzanghera htps://notes.sagredo.eu
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
class rcube_qmailforward_storage_mysql extends rcube_qmailforward_storage
{
    private $db;
    private $db_dsnw;
    private $db_persistent;
    private $table_name;
    private $alias_field;
    private $domain_field;
    private $valias_field;
    private $type_field;
    private $copy_field;
    private $defaultdelivery;
    private $defaultdelivery_enabled;

    /**
     * Object constructor
     *
     * @param mixed $config Roundcube config object
     */
    public function __construct($config)
    {
                $this->db_dsnw = $config->get('qmailforward_db_dsnw');
                $this->db_dsnr = $config->get('qmailforward_db_dsnr');
          $this->db_persistent = $config->get('qmailforward_db_persistent');
             $this->table_name = $config->get('qmailforward_sql_table_name');
            $this->alias_field = $config->get('qmailforward_sql_alias_field');
           $this->domain_field = $config->get('qmailforward_sql_domain_field');
           $this->valias_field = $config->get('qmailforward_sql_valias_field');
             $this->type_field = $config->get('qmailforward_sql_type_field');
             $this->copy_field = $config->get('qmailforward_sql_copy_field');
        $this->defaultdelivery = $config->get('qmailforward_defaultdelivery');

        // no need to write the lda to DB if the delivery is vdelivermail
        $this->defaultdelivery_enabled = (strpos($this->defaultdelivery, 'vdelivermail') === false) ? true : false;
    }

    /**
     * Retrieve user valiases
     *
     * @param string $user   mailbox username
     *        string $domain mailbox domain
     *
     * @return array [$key => $value, ...]
     */
    public function load($user, $domain)
    {
        $this->_db_connect('r');
        $valias = [];

        // type: 1=forwarder 0=lda information
        // copy: 0=redirect 1=copy&redirect
        $sql = "SELECT ".$this->valias_field.", ".$this->copy_field.
                " FROM ".$this->table_name.
               " WHERE ".$this->alias_field. " = '".$user."' ".
                  "AND ".$this->domain_field." = '".$domain."' ".
                  "AND ".$this->type_field.  " = 1";

        $sql_result = $this->db->query($sql);
        while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
            $valias[$this->copy_field]   = $sql_arr[$this->copy_field];
            $valias[$this->valias_field] = $sql_arr[$this->valias_field];
        }

        return $valias;
    }

    /**
     * Save POST data to database
     *
     * @param string $user      login username
     * @param array  $domain    login domain
     *
     * @return bool True on success, False on error
     */
    public function save($user, $domain)
    {
        $result = false;
        $this->_db_connect('w');
        $sql = '';

        switch ($_POST['forward_status']) {
            case 'on':
                // insert (if exist update)
                $what_to_do = 'w';
                break;
            case 'off':
                // delete
                $what_to_do = 'd';
                break;
            default:
                rcube::write_log('errors', 'qmailforward error: malformed "forward_status"');
                return false;
        }

        // determine valias_line
        $valias_line = !is_null($_POST['action_domain']??null) ?
               $_POST['action_target'].'@'.$_POST['action_domain'] :
               $_POST['action_target'];

        if ($what_to_do == 'd') {
            // clicked save when the status was off and no further valias was already present in db?
            // then don't do anything
            $sql = "SELECT NULL FROM ".$this->table_name." ".
                   "WHERE ".$this->alias_field. "='".$user.  "'".
                    " AND ".$this->domain_field."='".$domain."'".
                   "LIMIT 1";

            $this->db->query($sql);
            // if something was found go on and delete
            if ($this->db->affected_rows()) {
                // delete all existing rows belonging to the user@domain
                $sql = "DELETE FROM ".$this->table_name.
                       " WHERE ".$this->alias_field. "='".$user.  "'".
                         " AND ".$this->domain_field."='".$domain."'";

                $this->db->query($sql);
                $result = $this->db->affected_rows();
            }
            else $result = true;
        }
        else if ($what_to_do == 'w')
        {
            // simple redirect with no copy on mailbox
            if ($_POST['forward_action'] == 'redirect') {
                $sql = "INSERT INTO ".$this->table_name.
                       " SET ".$this->alias_field. "='".$user.  "', ".
                               $this->domain_field."='".$domain."', ".
                               $this->valias_field."='".$valias_line."', ".
                               $this->copy_field.  "=0 ".
                       "ON DUPLICATE KEY UPDATE ".
                               $this->alias_field. "='".$user.  "', ".
                               $this->domain_field."='".$domain."', ".
                               $this->valias_field."='".$valias_line."', ".
                               $this->copy_field.  "=0 ";

                $this->db->query($sql);
                if (!$this->db->affected_rows()) return false;

                // delete all eventually present lda records (valias_type=0)
                $sql = "DELETE FROM ".$this->table_name.
                       " WHERE ".$this->alias_field. "='".$user.  "'".
                         " AND ".$this->domain_field."='".$domain."'".
                         " AND ".$this->type_field."=0";

                $result = $this->db->query($sql);
            }
            // send copy and save to mailbox
            else if ($_POST['forward_action'] == 'copy') {
                // save the forward record
                $sql = "INSERT INTO ".$this->table_name.
                       " SET ".$this->alias_field. "='".$user.  "', ".
                               $this->domain_field."='".$domain."', ".
                               $this->valias_field."='".$valias_line."', ".
                               $this->copy_field.  "=1 ".
                       "ON DUPLICATE KEY UPDATE ".
                               $this->alias_field. "='".$user.  "', ".
                               $this->domain_field."='".$domain."', ".
                               $this->valias_field."='".$valias_line."', ".
                               $this->copy_field.  "=1 ";

                $this->db->query($sql);
                if (!$this->db->affected_rows()) return false;

                /*
                  Save the lda record
                  Skip if the LDA is vdelivermail to avoid vpopmail loop,
                  as vdelivermail is already in .qmail-default.
                 */
                if ($this->defaultdelivery_enabled) {
                    $sql = "INSERT INTO ".$this->table_name.
                           " SET ".$this->alias_field. "='".$user.  "', ".
                                   $this->domain_field."='".$domain."', ".
                                   $this->valias_field."=".$this->db->quote($this->defaultdelivery).", ".
                                   $this->type_field.  "=0 ".
                           "ON DUPLICATE KEY UPDATE ".
                                   $this->alias_field. "='".$user.  "', ".
                                   $this->domain_field."='".$domain."', ".
                                   $this->valias_field."=".$this->db->quote($this->defaultdelivery).", ".
                                   $this->type_field.  "=0";

                    $this->db->query($sql);
                    $result = $this->db->affected_rows();
                    return $result;
                }
            }
        }
        return true;
    }


    /**
     * Connect to appropriate database depending on the operation
     *
     * @param string $mode Connection mode (r|w)
     */
    private function _db_connect($mode)
    {
        if (!$this->db) {
            $this->db = rcube_db::factory($this->db_dsnw, $this->db_dsnr, $this->db_persistent);
        }

        $this->db->set_debug((bool) rcube::get_instance()->config->get('sql_debug'));
        $this->db->db_connect($mode);

        // check DB connections and exit on failure
        if ($err_str = $this->db->is_error()) {
            rcube::raise_error(['code' => 603, 'type' => 'db', 'message' => $err_str], false, true);
        }
    }
}
